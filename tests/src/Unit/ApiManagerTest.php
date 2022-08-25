<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\CacheValue;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\RequestInterface;
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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time prophecy.
   * @param \GuzzleHttp\ClientInterface $client
   *   The http client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   *
   * @return \Drupal\helfi_navigation\ApiManager
   *   The api manager instance.
   */
  private function getSut(
    TimeInterface $time,
    ClientInterface $client,
    LoggerInterface $logger
  ) : ApiManager {
    $environmentResolver = new EnvironmentResolver('', $this->getConfigFactoryStub([
      'helfi_api_base.environment_resolver.settings' => [
        'project_name' => Project::ASUMINEN,
        'environment_name' => 'local',
      ],
    ]));
    return new ApiManager($time, $this->cache, $client, $environmentResolver, $logger);
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
      $this->getTimeMock(time())->reveal(),
      $client,
      $this->prophesize(LoggerInterface::class)->reveal()
    );
    $sut->updateMainMenu('fi', '123', ['key' => 'value']);

    $this->assertCount(1, $requests);
    // Make sure SSL verification is disabled on local.
    $this->assertFalse($requests[0]['options']['verify']);
    // Make sure Authorization header was set.
    $this->assertEquals('123', $requests[0]['request']->getHeader('Authorization')[0]);
  }

  /**
   * Tests getExternalMenu().
   *
   * @covers ::getExternalMenu
   * @covers ::__construct
   * @covers ::makeRequest
   */
  public function testGetExternalMenu() : void {
    $requests = [];
    $client = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);
    $sut = $this->getSut(
      $this->getTimeMock(time())->reveal(),
      $client,
      $this->prophesize(LoggerInterface::class)->reveal()
    );
    $response = $sut->getExternalMenu('fi', 'main');
    $this->assertInstanceOf(\stdClass::class, $response);
    $this->assertInstanceOf(RequestInterface::class, $requests[0]['request']);
    // Make sure cache is used (request queue should be empty).
    $sut->getExternalMenu('fi', 'main');
  }

  /**
   * Tests getMainMenu().
   *
   * @covers ::getExternalMenu
   * @covers ::__construct
   * @covers ::makeRequest
   */
  public function testGetMainMenu() : void {
    $requests = [];
    $client = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);
    $sut = $this->getSut(
      $this->getTimeMock(time())->reveal(),
      $client,
      $this->prophesize(LoggerInterface::class)->reveal()
    );
    $response = $sut->getMainMenu('fi');
    $this->assertInstanceOf(\stdClass::class, $response);
    $this->assertInstanceOf(RequestInterface::class, $requests[0]['request']);
    // Make sure cache is used (request queue should be empty).
    $sut->getMainMenu('fi');
  }

  /**
   * Tests that stale cache will be returned in case request fails.
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
      $this->getTimeMock($time)->reveal(),
      $client,
      $this->prophesize(LoggerInterface::class)->reveal()
    );
    $response = $sut->getMainMenu('fi');
    $this->assertInstanceOf(\stdClass::class, $response);
  }

  /**
   * Tests that stale cache can be updated.
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
      $this->getTimeMock($time)->reveal(),
      $client,
      $this->prophesize(LoggerInterface::class)->reveal()
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
   */
  public function testRequestLoggingException() : void {
    $this->expectException(GuzzleException::class);

    $client = $this->createMockHttpClient([
      new RequestException('Test', $this->prophesize(RequestInterface::class)->reveal()),
    ]);
    $logger = $this->prophesize(LoggerInterface::class);
    $logger->error(Argument::any())
      ->shouldBeCalled();

    $sut = $this->getSut(
      $this->getTimeMock(time())->reveal(),
      $client,
      $logger->reveal()
    );
    $sut->getExternalMenu('fi', 'footer');
  }

}
