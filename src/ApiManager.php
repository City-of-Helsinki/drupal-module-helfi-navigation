<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\Exception\FileNotExistsException;
use Drupal\helfi_api_base\Cache\CacheKeyTrait;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;

/**
 * Service class for global navigation related functions.
 */
class ApiManager {

  use CacheKeyTrait;

  /**
   * The authorization token.
   *
   * @var null|string
   */
  private ?string $authorization;

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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    private TimeInterface $time,
    private CacheBackendInterface $cache,
    private ClientInterface $httpClient,
    private EnvironmentResolverInterface $environmentResolver,
    private LoggerInterface $logger,
    ConfigFactoryInterface $configFactory
  ) {
    $this->authorization = $configFactory->get('helfi_navigation.api')->get('key');
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

    // Attempt to re-fetch the data in case cache does not exist or has
    // expired.
    if (
      ($value instanceof CacheValue && $value->hasExpired($this->time->getRequestTime())) ||
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
  public function getExternalMenu(
    string $langcode,
    string $menuId,
    array $options = []
  ) : object {
    $key = $this->getCacheKey(sprintf('external_menu:%s:%s', $menuId, $langcode), $options);

    return $this->cache($key, fn() =>
        new CacheValue(
          $this->makeRequest('GET', "/jsonapi/menu_items/$menuId", $langcode, $options),
          $this->time->getRequestTime(),
          ['external_menu:%s:%s', $menuId, $langcode],
        )
    )->value;
  }

  /**
   * Makes a request to fetch main menu from Etusivu instance.
   *
   * @param string $langcode
   *   The langcode.
   * @param array $options
   *   The request options.
   *
   * @return \Drupal\helfi_navigation\CacheValue
   *   The JSON object representing main menu.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getMainMenu(string $langcode, array $options = []) : object {
    $key = $this->getCacheKey(sprintf('external_menu:main:%s', $langcode), $options);

    return $this->cache($key, fn() =>
      new CacheValue(
        $this->makeRequest('GET', '/api/v1/global-menu', $langcode, $options),
        $this->time->getRequestTime(),
        ['external_menu:main:%s', $langcode]
      )
    )->value;
  }

  /**
   * Updates the main menu for currently active project.
   *
   * @param string $langcode
   *   The langcode.
   * @param array $data
   *   The JSON data to update.
   *
   * @return object
   *   The JSON object.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function updateMainMenu(string $langcode, array $data) : object {
    if (!$this->authorization) {
      throw new ConfigException('Missing "helfi_navigation.api" key setting.');
    }
    $endpoint = sprintf('/api/v1/global-menu/%s', $this->environmentResolver->getActiveEnvironment()->getId());
    return $this->makeRequest('POST', $endpoint, $langcode, [
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
  private function makeRequest(
    string $method,
    string $endpoint,
    string $langcode,
    array $options = []
  ): object {
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

    if ($this->authorization !== NULL) {
      $options['headers']['Authorization'] = sprintf('Basic %s', $this->authorization);
    }

    try {
      $response = $this->httpClient->request($method, $url, $options);
      $data = \GuzzleHttp\json_decode($response->getBody()->getContents());

      return $data instanceof \stdClass ? $data : new \stdClass();
    }
    catch (\Exception $e) {
      // Serve mock data in local environments if requests fail.
      if (
        $method === 'GET' &&
        ($e instanceof ClientException || $e instanceof ConnectException) &&
        $activeEnvironmentName === 'local'
      ) {
        $this->logger->warning(
          sprintf('Global menu request failed: %s. Mock data is used instead.', $e->getMessage())
        );

        $fileName = vsprintf('%s/../fixtures/%s-%s.json', [
          __DIR__,
          str_replace('/', '-', ltrim($endpoint, '/')),
          $langcode,
        ]);

        if (!file_exists($fileName)) {
          throw new FileNotExistsException(
            sprintf('[%s]. Attempted to use mock data, but the mock file was not found for "%s" endpoint.', $e->getMessage(), $endpoint)
          );
        }
        return \GuzzleHttp\json_decode(file_get_contents($fileName));
      }
      // Log the error and re-throw the exception.
      $this->logger->error('Request failed with error: ' . $e->getMessage());
      throw $e;
    }
  }

}
