<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\helfi_api_base\Cache\CacheKeyTrait;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;

/**
 * Service class for global navigation related functions.
 */
final class ApiManager {

  use CacheKeyTrait;

  /**
   * Construct an instance.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolver $environmentResolver
   *   EnvironmentResolver helper class.
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger channel.
   */
  public function __construct(
    private TimeInterface $time,
    private CacheBackendInterface $cache,
    private ClientInterface $httpClient,
    private EnvironmentResolver $environmentResolver,
    private LoggerInterface $logger,
  ) {
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
  private function cache(string $key, callable $callback) : ? MaybeCache {
    $exception = new TransferException('Failed to update cache.');
    $cache = $this->cache->get($key)?->data;
    $updateCache = $cache === NULL;

    if ($cache instanceof MaybeCache) {
      $updateCache = $cache->hasExpired($this->time->getRequestTime());
    }

    // Attempt to re-fetch the data in case cache does not exist or has
    // expired, but allow stale cache to be used in case data cannot be
    // updated.
    if ($updateCache) {
      try {
        $maybeCache = $callback();
        $this->cache->set($key, $maybeCache, tags: $maybeCache->tags);
        return $maybeCache;
      }
      catch (GuzzleException $e) {
        $exception = $e;
      }
    }
    return !$cache instanceof MaybeCache ? throw $exception : $cache;
  }

  /**
   * Makes a request to fetch external menu from Etusivu instance.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $menuId
   *   The menu id to get.
   * @param array $options
   *   The request options.
   *
   * @return object
   *   The JSON object representing external menu.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getExternalMenu(string $langcode, string $menuId, array $options = []) : object {
    $key = $this->getCacheKey(sprintf('external_menu:%s:%s', $menuId, $langcode), $options);

    return $this
      ->cache($key, function () use ($menuId, $langcode, $options) : MaybeCache {
        return new MaybeCache(
          $this->makeRequest('GET', "/jsonapi/menu_items/$menuId", $langcode, $options),
          $this->time->getRequestTime(),
          ['external_menu:%s:%s', $menuId, $langcode],
        );
      })
      ->data;
  }

  /**
   * Makes a request to fetch main menu from Etusivu instance.
   *
   * @param string $langcode
   *   The langcode.
   * @param array $options
   *   The request options.
   *
   * @return object
   *   The JSON object representing main menu.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getMainMenu(string $langcode, array $options = []) : object {
    $key = $this->getCacheKey(sprintf('external_menu:main:%s', $langcode), $options);

    return $this
      ->cache($key, function () use ($langcode, $options) : MaybeCache {
        return new MaybeCache(
          $this->makeRequest('GET', '/api/v1/global-menu', $langcode, $options),
          $this->time->getRequestTime(),
          ['external_menu:main:%s', $langcode]
        );
      })
      ->data;
  }

  /**
   * Updates the main menu for currently active project.
   *
   * @param string $langcode
   *   The langcode.
   * @param string $authorization
   *   The authorization header.
   * @param array $data
   *   The JSON data to update.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function updateMainMenu(string $langcode, string $authorization, array $data) : void {
    $endpoint = sprintf('/api/v1/global-menu/%s', $this->environmentResolver->getActiveEnvironment()->getId());
    $this->makeRequest('POST', $endpoint, $langcode, [
      'headers' => [
        'Authorization' => $authorization,
      ],
      'json' => $data,
    ]);
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
   * @return object
   *   The JSON object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  private function makeRequest(string $method, string $endpoint, string $langcode, array $options = []): object {
    $activeEnvironmentName = $this->environmentResolver
      ->getActiveEnvironment()
      ->getEnvironmentName();

    $baseUrl = $this->environmentResolver
      ->getEnvironment(Project::ETUSIVU, $activeEnvironmentName)
      ->getUrl($langcode);

    $url = sprintf('%s/%s', $baseUrl, ltrim($endpoint, '/'));

    // Disable SSL verification in local environment.
    if ($activeEnvironmentName === 'local') {
      $options['verify'] = FALSE;
    }

    try {
      $response = $this->httpClient->request($method, $url, $options);
      return \GuzzleHttp\json_decode($response->getBody()->getContents());
    }
    catch (\Exception $e) {
      // Log the error and re-throw the exception.
      $this->logger->error('Request failed with error: ' . $e->getMessage());
      throw $e;
    }
  }

}
