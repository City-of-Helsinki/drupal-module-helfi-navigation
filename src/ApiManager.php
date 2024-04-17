<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigException;
use Drupal\helfi_api_base\ApiClient\ApiClient;
use Drupal\helfi_api_base\ApiClient\ApiResponse;
use Drupal\helfi_api_base\ApiClient\CacheValue;
use Drupal\helfi_api_base\Cache\CacheKeyTrait;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;

/**
 * Service class for global navigation-related functions.
 */
class ApiManager {

  public const GLOBAL_MENU_ENDPOINT = '/api/v1/global-menu';
  public const MENU_ENDPOINT = '/api/v1/menu';

  /**
   * Cache for one month.
   */
  public const TTL = 2629800;

  use CacheKeyTrait;

  /**
   * Construct an instance.
   *
   * @param \Drupal\helfi_api_base\ApiClient\ApiClient $client
   *   The HTTP client.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   EnvironmentResolver helper class.
   * @param \Drupal\helfi_navigation\ApiAuthorization $apiAuthorization
   *   The API authorization service.
   */
  public function __construct(
    private ApiClient $client,
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly ApiAuthorization $apiAuthorization,
  ) {
  }

  /**
   * Allow cache to be bypassed.
   *
   * @return $this
   *   The self.
   */
  public function withBypassCache() : self {
    $instance = clone $this;
    $instance->client = $this->client->withBypassCache();
    return $instance;
  }

  /**
   * Use cache to fetch an external menu from Etusivu instance.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $menuId
   *   The menu id to get.
   * @param array $options
   *   The request options.
   *
   * @return \Drupal\helfi_api_base\ApiClient\ApiResponse
   *   The JSON object representing the external menu.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function get(
    string $langcode,
    string $menuId,
    array $options = []
  ) : ApiResponse {
    $key = $this->getCacheKey(sprintf('external_menu:%s:%s', $menuId, $langcode), $options);

    // Try to fetch from cache.
    return $this->client->cache(
      $key,
      // Fetch an external menu from Etusivu instance.
      function () use ($langcode, $menuId, $options) {
        $endpoint = match ($menuId) {
          'main' => self::GLOBAL_MENU_ENDPOINT,
          default => sprintf('%s/%s', self::MENU_ENDPOINT, $menuId),
        };

        $url = $this->getUrl('api', $langcode, ['endpoint' => $endpoint]);

        // Fixture is used if requests fail on local environment.
        $fixture = vsprintf('%s/../fixtures/%s-%s.json', [
          __DIR__,
          str_replace('/', '-', ltrim($endpoint, '/')),
          $langcode,
        ]);

        $response = $this->client->makeRequestWithFixture($fixture, 'GET', $url, $options);

        return new CacheValue(
          $response,
          $this->client->cacheMaxAge(self::TTL),
          [sprintf('external_menu:%s:%s', $menuId, $langcode)],
        );
      }
    )->response;
  }

  /**
   * Updates the main menu for the currently active project.
   *
   * @param string $langcode
   *   The langcode.
   * @param array $data
   *   The JSON data to update.
   *
   * @return \Drupal\helfi_api_base\ApiClient\ApiResponse
   *   The JSON object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function update(string $langcode, array $data) : ApiResponse {
    if (!$this->hasAuthorization()) {
      throw new ConfigException('Missing "helfi_navigation.api" key setting.');
    }

    $endpoint = sprintf('%s/%s', static::GLOBAL_MENU_ENDPOINT, $this->environmentResolver->getActiveEnvironment()->getId());
    $url = $this->getUrl('api', $langcode, ['endpoint' => $endpoint]);

    return $this->client->makeRequest('POST', $url, [
      'json' => $data,
      'headers' => ['Authorization' => sprintf('Basic %s', $this->getAuthorization())],
    ]);
  }

  /**
   * Gets the url for given type and langcode.
   *
   * @param string $type
   *   The type.
   * @param string $langcode
   *   The langcode.
   * @param array $options
   *   The url options.
   *
   * @return string
   *   The URL.
   */
  public function getUrl(string $type, string $langcode, array $options = []) : string {
    $activeEnvironment = $this->environmentResolver
      ->getActiveEnvironment();
    $activeEnvironmentName = $activeEnvironment
      ->getEnvironmentName();
    $env = $this->environmentResolver
      ->getEnvironment(Project::ETUSIVU, $activeEnvironmentName);

    return match ($type) {
      'canonical' => $env->getUrl($langcode),
      'js' => sprintf(
        '%s/%s',
        $activeEnvironment->getUrl($langcode),
        ltrim($options['endpoint'], '/')
      ),
      'api' => sprintf(
        '%s/%s',
        $env->getInternalAddress($langcode),
        ltrim($options['endpoint'], '/')
      ),
    };
  }

  /**
   * Is the system authorized to use the secured api endpoints.
   *
   * @return bool
   *   Is the system authorized to use secured endpoints.
   */
  public function hasAuthorization(): bool {
    return (bool) $this->getAuthorization();
  }

  /**
   * Gets the authorization.
   *
   * @return string|null
   *   The authorization token.
   */
  public function getAuthorization() : ?string {
    return $this->apiAuthorization->getAuthorization();
  }

}
