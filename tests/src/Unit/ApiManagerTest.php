<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\helfi_api_base\ApiClient\ApiResponse;
use Drupal\helfi_api_base\ApiClient\CacheValue;
use Drupal\helfi_api_base\ApiClient\VaultAuthorizer;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Vault\AuthorizationToken;
use Drupal\helfi_api_base\Vault\VaultManager;
use Drupal\helfi_navigation\ApiManager;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\ApiManager
 * @group helfi_navigation
 */
class ApiManagerTest extends UnitTestCase {

  use ApiTestTrait;
  use ProphecyTrait;

  /**
   * The cache.
   *
   * @var null|\Drupal\Core\Cache\CacheBackendInterface
   */
  private ?CacheBackendInterface $cache;

  /**
   * The default environment resolver config.
   *
   * @var array
   */
  private array $environmentResolverConfiguration = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->cache = new MemoryBackend();
    $this->environmentResolverConfiguration = [
      EnvironmentResolver::PROJECT_NAME_KEY => Project::ASUMINEN,
      EnvironmentResolver::ENVIRONMENT_NAME_KEY => 'local',
    ];
  }

  /**
   * Create a new time mock object.
   *
   * @param int $expectedTime
   *   The expected time.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The mock.
   */
  private function getTimeMock(int $expectedTime) : ObjectProphecy {
    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn($expectedTime);
    return $time;
  }

  /**
   * Constructs a new api manager instance.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The http client.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time prophecy.
   * @param \Psr\Log\LoggerInterface|null $logger
   *   The logger.
   * @param string|null $apiKey
   *   The api key.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface|null $environmentResolver
   *   The environment resolver.
   *
   * @return \Drupal\helfi_navigation\ApiManager
   *   The api manager instance.
   */
  private function getSut(
    ClientInterface $client,
    TimeInterface $time = NULL,
    LoggerInterface $logger = NULL,
    EnvironmentResolverInterface $environmentResolver = NULL,
  ) : ApiManager {

    if (!$time) {
      $time = $this->getTimeMock(time())->reveal();
    }

    if (!$logger) {
      $logger = $this->prophesize(LoggerInterface::class)->reveal();
    }
    if (!$environmentResolver) {
      $environmentResolver = new EnvironmentResolver('', $this->getConfigFactoryStub([
        'helfi_api_base.environment_resolver.settings' => $this->environmentResolverConfiguration,
      ]));
    }
    return new ApiManager(
      $client,
      $this->cache,
      $time,
      $environmentResolver,
      $logger,
      new VaultAuthorizer(
        $this->getConfigFactoryStub([]),
        new VaultManager([
          new AuthorizationToken('test_vault_key', '123'),
        ]),
        'test_vault_key'
      ),
    );
  }

  /**
   * Tests updateMainMenu().
   *
   * @covers ::update
   * @covers ::getUrl
   */
  public function testUpdateMainMenu() : void {
    $requests = [];
    $client = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);
    $sut = $this->getSut($client);
    $sut->update('fi', ['key' => 'value']);

    $this->assertCount(1, $requests);
    // Make sure SSL verification is disabled on local.
    $this->assertFalse($requests[0]['options']['verify']);
    // Make sure Authorization header was set.
    $this->assertNotEmpty($requests[0]['request']->getHeader('Authorization')[0]);
  }

  /**
   * Tests main menu.
   *
   * @covers ::get
   * @covers ::doGet
   * @covers ::getUrl
   * @covers ::getCacheMaxAge
   */
  public function testGetMainMenu() : void {
    $requests = [];
    $client = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode([])),
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);
    $sut = $this->getSut($client);
    // Test empty and non-empty response.
    for ($i = 0; $i < 2; $i++) {
      $response = $sut->get('fi', 'main');
      $this->assertInstanceOf(ApiResponse::class, $response);
      $this->assertInstanceOf(RequestInterface::class, $requests[0]['request']);
    }
    // Make sure cache is used (request queue should be empty).
    $sut->get('fi', 'main');
  }

  /**
   * Tests that stale cache will be returned in case request fails.
   *
   * @covers ::get
   * @covers ::doGet
   * @covers ::getUrl
   */
  public function testStaleCacheOnRequestFailure() : void {
    $requests = [];
    $client = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new RequestException('Test', $this->prophesize(RequestInterface::class)->reveal()),
    ]);

    $time = time();
    // Expired cache object.
    $cacheValue = new CacheValue(
      new ApiResponse((object) ['value' => 1]),
      $time - 10,
      [],
    );
    $this->cache->set('external_menu:main:fi', $cacheValue);

    $sut = $this->getSut(
      $client,
      $this->getTimeMock($time)->reveal(),
    );
    $response = $sut->get('fi', 'main');
    $this->assertInstanceOf(ApiResponse::class, $response);
  }

  /**
   * Tests that stale cache can be updated.
   *
   * @covers ::get
   * @covers ::doGet
   * @covers ::getUrl
   * @covers ::getCacheMaxAge
   */
  public function testStaleCacheUpdate() : void {
    $time = time();

    // Expired cache object.
    $cacheValue = new CacheValue(
      new ApiResponse((object) ['value' => 1]),
      $time - 10,
      [],
    );
    // Populate cache with expired cache value object.
    $this->cache->set('external_menu:main:en', $cacheValue);

    $requests = [];
    $client = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['value' => 'value'])),
    ]);
    $sut = $this->getSut(
      $client,
      $this->getTimeMock($time)->reveal(),
    );
    $response = $sut->get('en', 'main');
    $this->assertInstanceOf(ApiResponse::class, $response);
    // Make sure cache was updated.
    $this->assertInstanceOf(\stdClass::class, $response->data);
    $this->assertEquals('value', $response->data->value);
    // Re-fetch the data to make sure we still get updated data and make sure
    // no further HTTP requests are made.
    $response = $sut->get('en', 'main');
    $this->assertInstanceOf(\stdClass::class, $response->data);
    $this->assertEquals('value', $response->data->value);
  }

}
