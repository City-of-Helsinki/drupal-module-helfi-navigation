<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\File\Exception\FileNotExistsException;
use Drupal\helfi_api_base\Cache\CacheKeyTrait;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Utils;
use Psr\Log\LoggerInterface;

/**
 * Service class for global navigation-related functions.
 */
class ApiManager {

  public const GLOBAL_MENU_ENDPOINT = '/api/v1/global-menu';
  public const MENU_ENDPOINT = '/api/v1/menu';

  use CacheKeyTrait;

  /**
   * The previous exception.
   *
   * @var \Exception|null
   */
  private ?\Exception $previousException = NULL;

  /**
   * Whether to bypass cache or not.
   *
   * @var bool
   */
  private bool $bypassCache = FALSE;

  /**
   * Construct an instance.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   EnvironmentResolver helper class.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   * @param \Drupal\helfi_navigation\ApiAuthorization $apiAuthorization
   *   The API authorization service.
   * @param int $requestTimeout
   *   The request timeout.
   */
  public function __construct(
    private readonly TimeInterface $time,
    private readonly CacheBackendInterface $cache,
    private readonly ClientInterface $httpClient,
    private readonly EnvironmentResolverInterface $environmentResolver,
    private readonly LoggerInterface $logger,
    private readonly ApiAuthorization $apiAuthorization,
    private readonly int $requestTimeout,
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
    $instance->bypassCache = TRUE;
    return $instance;
  }

  /**
   * Gets the cached data for given menu and language.
   *
   * @param string $key
   *   The  cache key.
   * @param callable $callback
   *   The callback to handle requests.
   *
   * @return object|null
   *   The cache or null.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function cache(string $key, callable $callback) : ? CacheValue {
    $exception = new TransferException();
    $value = ($cache = $this->cache->get($key)) ? $cache->data : NULL;

    // Attempt to re-fetch the data in case cache does not exist, cache has
    // expired, or bypass cache is set to true.
    if (
      ($value instanceof CacheValue && $value->hasExpired($this->time->getRequestTime())) ||
      $this->bypassCache ||
      $value === NULL
    ) {
      try {
        $value = $callback();
        $this->cache->set($key, $value, tags: $value->tags);
        return $value;
      }
      catch (GuzzleException $e) {
        // Request callback failed. Catch the exception, so we can still use
        // stale cache if it exists.
        $exception = $e;
      }
    }

    if ($value instanceof CacheValue) {
      return $value;
    }
    // We should only reach this if:
    // 1. Cache does not exist ($value is NULL).
    // 2. API request fails, and we cannot re-populate the cache (caught the
    // exception).
    throw $exception;
  }

  /**
   * Makes a request to fetch an external menu from Etusivu instance.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $menuId
   *   The menu id to get.
   * @param array $options
   *   The request options.
   *
   * @return \Drupal\helfi_navigation\ApiResponse
   *   The JSON object representing the external menu.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function get(
    string $langcode,
    string $menuId,
    array $options = []
  ) : ApiResponse {

    $endpoint = match ($menuId) {
      'main' => static::GLOBAL_MENU_ENDPOINT,
      default => sprintf('%s/%s', static::MENU_ENDPOINT, $menuId),
    };

    $key = $this->getCacheKey(sprintf('external_menu:%s:%s', $menuId, $langcode), $options);

    return $this->cache($key, fn() =>
        new CacheValue(
          $this->makeRequest('GET', $endpoint, $langcode, $options),
          $this->time->getRequestTime(),
          ['external_menu:%s:%s', $menuId, $langcode],
        )
    )->value;
  }

  /**
   * Updates the main menu for the currently active project.
   *
   * @param string $langcode
   *   The langcode.
   * @param array $data
   *   The JSON data to update.
   *
   * @return \Drupal\helfi_navigation\ApiResponse
   *   The JSON object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function update(string $langcode, array $data) : ApiResponse {
    if (!$this->hasAuthorization()) {
      throw new ConfigException('Missing "helfi_navigation.api" key setting.');
    }

    $endpoint = sprintf('%s/%s', static::GLOBAL_MENU_ENDPOINT, $this->environmentResolver->getActiveEnvironment()->getId());
    return $this->makeRequest('POST', $endpoint, $langcode, [
      'json' => $data,
      'headers' => ['Authorization' => sprintf('Basic %s', $this->getAuthorization())],
    ]);
  }

  /**
   * Gets the default request options.
   *
   * @param string $environmentName
   *   Environment name.
   * @param array $options
   *   The optional options.
   *
   * @return array
   *   The request options.
   */
  private function getRequestOptions(string $environmentName, array $options = []) : array {
    $default = [
      'timeout' => $this->requestTimeout,
      'curl' => [CURLOPT_TCP_KEEPALIVE => TRUE],
    ];

    if ($environmentName === 'local') {
      // Disable SSL verification in local environment.
      $default['verify'] = FALSE;
    }
    return array_merge_recursive($options, $default);
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
    $activeEnvironmentName = $this->environmentResolver
      ->getActiveEnvironment()
      ->getEnvironmentName();

    $env = $this->environmentResolver
      ->getEnvironment(Project::ETUSIVU, $activeEnvironmentName);

    return match ($type) {
      'canonical' => $env->getUrl($langcode),
      'js' => sprintf(
        '%s/%s',
        $this->environmentResolver->getActiveEnvironment()->getUrl($langcode),
        ltrim($options['endpoint'], '/')
      ),
      'api' => sprintf('%s/%s', $env->getInternalAddress($langcode), ltrim($options['endpoint'], '/')),
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

  /**
   * Makes a request based on parameters and returns the response.
   *
   * @param string $method
   *   Request method.
   * @param string $endpoint
   *   The endpoint in the instance.
   * @param string $langcode
   *   The langcode.
   * @param array $options
   *   Body for requests.
   *
   * @return \Drupal\helfi_navigation\ApiResponse
   *   The JSON object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function makeRequest(
    string $method,
    string $endpoint,
    string $langcode,
    array $options = []
  ): ApiResponse {
    $activeEnvironmentName = $this->environmentResolver
      ->getActiveEnvironment()
      ->getEnvironmentName();

    $url = $this->getUrl('api', $langcode, ['endpoint' => $endpoint]);

    $options = $this->getRequestOptions($activeEnvironmentName, $options);

    try {
      if ($this->previousException instanceof \Exception) {
        // Fail any further request instantly after one failed request, so we
        // don't block the rendering process and cause the site to time-out when
        // Etusivu instance is not reachable.
        throw $this->previousException;
      }
      $response = $this->httpClient->request($method, $url, $options);

      return new ApiResponse(Utils::jsonDecode($response->getBody()->getContents()));
    }
    catch (\Exception $e) {
      if ($e instanceof GuzzleException) {
        $this->previousException = $e;
      }
      // Serve mock data in local environments if requests fail.
      if (
        $method === 'GET' &&
        ($e instanceof ClientException || $e instanceof ConnectException) &&
        $activeEnvironmentName === 'local'
      ) {
        $this->logger->warning(
          sprintf('Menu request failed: %s. Mock data is used instead.', $e->getMessage())
        );

        $fileName = vsprintf('%s/../fixtures/%s-%s.json', [
          __DIR__,
          str_replace('/', '-', ltrim($endpoint, '/')),
          $langcode,
        ]);

        if (!file_exists($fileName)) {
          throw new FileNotExistsException(
            sprintf('[%s]. Attempted to use mock data, but the mock file "%s" was not found for "%s" endpoint.', $e->getMessage(), basename($fileName), $endpoint)
          );
        }
        return new ApiResponse(Utils::jsonDecode(file_get_contents($fileName)));
      }
      // Log the error and re-throw the exception.
      $this->logger->error('Request failed with error: ' . $e->getMessage());
      throw $e;
    }
  }

}
