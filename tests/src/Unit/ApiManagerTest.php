<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\File\Exception\FileNotExistsException;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\CacheValue;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\ApiManager
 * @group helfi_navigation
 */
class ApiManagerTest extends UnitTestCase {

  use ApiTestTrait;

  /**
   * The cache.
   *
   * @var null|\Drupal\Core\Cache\CacheBackendInterface
   */
  private ?CacheBackendInterface $cache;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->cache = new MemoryBackend();
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
    string $apiKey = NULL,
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
        'helfi_api_base.environment_resolver.settings' => [
          EnvironmentResolver::PROJECT_NAME_KEY => Project::ASUMINEN,
          EnvironmentResolver::ENVIRONMENT_NAME_KEY => 'local',
        ],
      ]));
    }
    return new ApiManager(
      $time,
      $this->cache,
      $client,
      $environmentResolver,
      $logger,
      $this->getConfigFactoryStub([
        'helfi_navigation.api' => [
          'key' => $apiKey,
        ],
      ]),
    );
  }

  /**
   * Tests updateMainMenu().
   *
   * @covers ::updateMainMenu
   * @covers ::__construct
   * @covers ::makeRequest
   */
  public function testUpdateMainMenu() : void {
    $requests = [];
    $client = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);
    $sut = $this->getSut(
      $client,
      apiKey: '123'
    );
    $sut->updateMainMenu('fi', ['key' => 'value']);

    $this->assertCount(1, $requests);
    // Make sure SSL verification is disabled on local.
    $this->assertFalse($requests[0]['options']['verify']);
    // Make sure Authorization header was set.
    $this->assertEquals('Basic 123', $requests[0]['request']->getHeader('Authorization')[0]);
  }

  /**
   * Tests getExternalMenu().
   *
   * @covers ::getExternalMenu
   * @covers ::__construct
   * @covers ::makeRequest
   * @covers ::cache
   * @covers \Drupal\helfi_navigation\CacheValue::hasExpired
   * @covers \Drupal\helfi_navigation\CacheValue::__construct
   */
  public function testGetExternalMenu() : void {
    $requests = [];
    $client = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode([])),
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);
    $sut = $this->getSut($client);

    // Test empty and non-empty response.
    for ($i = 0; $i < 2; $i++) {
      $response = $sut->getExternalMenu('fi', 'main');
      $this->assertInstanceOf(\stdClass::class, $response);
      $this->assertInstanceOf(RequestInterface::class, $requests[0]['request']);
    }
    // Make sure cache is used (request queue should be empty).
    $sut->getExternalMenu('fi', 'main');
  }

  /**
   * Tests getMainMenu().
   *
   * @covers ::getMainMenu
   * @covers ::__construct
   * @covers ::makeRequest
   * @covers ::cache
   * @covers \Drupal\helfi_navigation\CacheValue::hasExpired
   * @covers \Drupal\helfi_navigation\CacheValue::__construct
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
      $response = $sut->getMainMenu('fi');
      $this->assertInstanceOf(\stdClass::class, $response);
      $this->assertInstanceOf(RequestInterface::class, $requests[0]['request']);
    }
    // Make sure cache is used (request queue should be empty).
    $sut->getMainMenu('fi');
  }

  /**
   * Tests that stale cache will be returned in case request fails.
   *
   * @covers ::makeRequest
   * @covers ::getMainMenu
   * @covers ::__construct
   * @covers ::cache
   * @covers \Drupal\helfi_navigation\CacheValue::hasExpired
   * @covers \Drupal\helfi_navigation\CacheValue::__construct
   */
  public function testStaleCacheOnRequestFailure() : void {
    $requests = [];
    $client = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new RequestException('Test', $this->prophesize(RequestInterface::class)->reveal()),
    ]);

    $time = time();
    // Expired cache object.
    $cacheValue = new CacheValue(
      (object) ['value' => 1],
      $time - (CacheValue::TTL + 10),
      [],
    );
    $this->cache->set('external_menu:main:fi', $cacheValue);

    $sut = $this->getSut(
      $client,
      $this->getTimeMock($time)->reveal(),
    );
    $response = $sut->getMainMenu('fi');
    $this->assertInstanceOf(\stdClass::class, $response);
  }

  /**
   * Tests that stale cache can be updated.
   *
   * @covers ::makeRequest
   * @covers ::getMainMenu
   * @covers ::__construct
   * @covers ::cache
   * @covers \Drupal\helfi_navigation\CacheValue::hasExpired
   * @covers \Drupal\helfi_navigation\CacheValue::__construct
   */
  public function testStaleCacheUpdate() : void {
    $time = time();

    // Expired cache object.
    $cacheValue = new CacheValue(
      (object) ['value' => 1],
      $time - (CacheValue::TTL + 10),
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
    $response = $sut->getMainMenu('en');
    $this->assertInstanceOf(\stdClass::class, $response);
    // Make sure cache was updated.
    $this->assertEquals('value', $response->value);
    // Re-fetch the data to make sure we still get updated data and make sure
    // no further HTTP requests are made.
    $response = $sut->getMainMenu('en');
    $this->assertEquals('value', $response->value);
  }

  /**
   * Make sure we log the exception and then re-throw the same exception.
   *
   * @covers ::makeRequest
   * @covers ::getExternalMenu
   * @covers ::__construct
   * @covers ::cache
   */
  public function testRequestLoggingException() : void {
    $this->expectException(GuzzleException::class);

    $client = $this->createMockHttpClient([
      new RequestException('Test', $this->prophesize(RequestInterface::class)->reveal()),
    ]);
    $logger = $this->prophesize(LoggerInterface::class);
    $logger->error(Argument::any())
      ->shouldBeCalled();

    $sut = $this->getSut($client, logger: $logger->reveal());
    $sut->getExternalMenu('fi', 'footer');
  }

  /**
   * Tests that file not found exception is thrown when no mock file exists.
   */
  public function testMockFallbackException() : void {
    $this->expectException(FileNotExistsException::class);
    $client = $this->createMockHttpClient([
      new ClientException(
        'Test',
        $this->prophesize(RequestInterface::class)->reveal(),
        $this->prophesize(ResponseInterface::class)->reveal(),
      ),
    ]);
    $sut = $this->getSut($client);
    // Test with non-existent menu to make sure no mock file exist.
    $sut->getExternalMenu('fi', 'footer');
  }

  /**
   * Tests that mock file used on local environment when GET request fails.
   */
  public function testMockFallback() : void {
    // Use logger to verify that mock file is actually used.
    $logger = $this->prophesize(LoggerInterface::class);
    $logger->warning(Argument::containingString('Mock data is used instead.'))
      ->shouldBeCalled();
    $client = $this->createMockHttpClient([
      new ClientException(
        'Test',
        $this->prophesize(RequestInterface::class)->reveal(),
        $this->prophesize(ResponseInterface::class)->reveal(),
      ),
    ]);
    $sut = $this->getSut(
      $client,
      logger: $logger->reveal(),
    );
    $response = $sut->getExternalMenu('fi', 'footer-bottom-navigation');
    $this->assertInstanceOf(\stdClass::class, $response);
  }

}
