<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigException;
use Drupal\helfi_api_base\ApiClient\ApiClientBase;
use Drupal\helfi_api_base\ApiClient\ApiResponse;
use Drupal\helfi_api_base\ApiClient\CacheValue;
use Drupal\helfi_api_base\Environment\Project;

/**
 * Service class for global navigation-related functions.
 */
class ApiManager extends ApiClientBase {

  public const GLOBAL_MENU_ENDPOINT = '/api/v1/global-menu';
  public const MENU_ENDPOINT = '/api/v1/menu';
  public const TTL = 180;

  /**
   * Max age for cache.
   */
  private function getCacheMaxAge() : int {
    return $this->time->getRequestTime() + self::TTL;
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

    return $this->cache(
      $key,
      fn() => $this->doGet($langcode, $menuId, $options)
    )->response;
  }

  /**
   * Performs a request to fetch an external menu from Etusivu instance.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $menuId
   *   The menu id to get.
   * @param array $options
   *   The request options.
   *
   * @return \Drupal\helfi_api_base\ApiClient\CacheValue
   *   Cacheable request object.
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function doGet(string $langcode, string $menuId, array $options): CacheValue {
    $endpoint = match ($menuId) {
      'main' => self::GLOBAL_MENU_ENDPOINT,
      default => sprintf('%s/%s', self::MENU_ENDPOINT, $menuId),
    };

    $url = $this->getUrl('api', $langcode, ['endpoint' => $endpoint]);

    $response = $this->makeRequest(
      'GET',
      $url,
      $options,
      // Fixture if requests fail on local environment:
      fixture: vsprintf('%s/../fixtures/%s-%s.json', [
        __DIR__,
        str_replace('/', '-', ltrim($endpoint, '/')),
        $langcode,
      ]),
    );

    return new CacheValue(
      $response,
      $this->getCacheMaxAge(),
      [sprintf('external_menu:%s:%s', $menuId, $langcode)],
    );
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

    return $this->makeRequest('POST', $url, [
      'json' => $data,
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

}
