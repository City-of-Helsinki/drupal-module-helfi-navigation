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
use Drupal\helfi_api_base\Vault\AuthorizationToken;
use Drupal\helfi_api_base\Vault\VaultManager;
use Drupal\helfi_navigation\ApiAuthorization;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\ApiResponse;
use Drupal\helfi_navigation\CacheValue;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
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
        'helfi_api_base.environment_resolver.settings' => $this->environmentResolverConfiguration,
      ]));
    }
    return new ApiManager(
      $time,
      $this->cache,
      $client,
      $environmentResolver,
      $logger,
      new ApiAuthorization(
        $this->getConfigFactoryStub([]),
        new VaultManager([
          new AuthorizationToken(ApiAuthorization::VAULT_MANAGER_KEY, '123'),
        ])
      ),
      1,
    );
  }

  /**
   * Tests updateMainMenu().
   *
   * @covers ::update
   * @covers ::__construct
   * @covers ::makeRequest
   * @covers ::getRequestOptions
   * @covers ::getUrl
   * @covers ::hasAuthorization
   * @covers ::getAuthorization
   * @covers \Drupal\helfi_navigation\ApiResponse::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
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
    $sut->update('fi', ['key' => 'value']);

    $this->assertCount(1, $requests);
    // Make sure SSL verification is disabled on local.
    $this->assertFalse($requests[0]['options']['verify']);
    // Make sure Authorization header was set.
    $this->assertEquals('Basic 123', $requests[0]['request']->getHeader('Authorization')[0]);
  }

  /**
   * Tests getExternalMenu().
   *
   * @covers ::get
   * @covers ::__construct
   * @covers ::makeRequest
   * @covers ::cache
   * @covers ::getRequestOptions
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\CacheValue::hasExpired
   * @covers \Drupal\helfi_navigation\CacheValue::__construct
   * @covers \Drupal\helfi_navigation\ApiResponse::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
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
      $response = $sut->get('fi', 'main');
      $this->assertInstanceOf(ApiResponse::class, $response);
      $this->assertInstanceOf(RequestInterface::class, $requests[0]['request']);
    }
    // Make sure cache is used (request queue should be empty).
    $sut->get('fi', 'main');
  }

  /**
   * Tests main menu.
   *
   * @covers ::get
   * @covers ::__construct
   * @covers ::makeRequest
   * @covers ::cache
   * @covers ::getRequestOptions
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\CacheValue::hasExpired
   * @covers \Drupal\helfi_navigation\CacheValue::__construct
   * @covers \Drupal\helfi_navigation\ApiResponse::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
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
   * @covers ::makeRequest
   * @covers ::get
   * @covers ::__construct
   * @covers ::cache
   * @covers ::getRequestOptions
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\CacheValue::hasExpired
   * @covers \Drupal\helfi_navigation\CacheValue::__construct
   * @covers \Drupal\helfi_navigation\ApiResponse::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
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
      $time - (CacheValue::TTL + 10),
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
   * @covers ::makeRequest
   * @covers ::get
   * @covers ::__construct
   * @covers ::cache
   * @covers ::getRequestOptions
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\CacheValue::hasExpired
   * @covers \Drupal\helfi_navigation\CacheValue::__construct
   * @covers \Drupal\helfi_navigation\ApiResponse::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
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
    $response = $sut->get('en', 'main');
    $this->assertInstanceOf(ApiResponse::class, $response);
    // Make sure cache was updated.
    $this->assertEquals('value', $response->data->value);
    // Re-fetch the data to make sure we still get updated data and make sure
    // no further HTTP requests are made.
    $response = $sut->get('en', 'main');
    $this->assertEquals('value', $response->data->value);
  }

  /**
   * Make sure we log the exception and then re-throw the same exception.
   *
   * @covers ::makeRequest
   * @covers ::get
   * @covers ::__construct
   * @covers ::cache
   * @covers ::getRequestOptions
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
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
    $sut->get('fi', 'footer');
  }

  /**
   * Tests that file not found exception is thrown when no mock file exists.
   *
   * @covers ::makeRequest
   * @covers ::get
   * @covers ::__construct
   * @covers ::cache
   * @covers ::getRequestOptions
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
   */
  public function testMockFallbackException() : void {
    $this->expectException(FileNotExistsException::class);
    $response = $this->prophesize(ResponseInterface::class);
    $response->getStatusCode()->willReturn(403);
    $client = $this->createMockHttpClient([
      new ClientException(
        'Test',
        $this->prophesize(RequestInterface::class)->reveal(),
        $response->reveal(),
      ),
    ]);
    $sut = $this->getSut($client);
    // Test with non-existent menu to make sure no mock file exist.
    $sut->get('fi', 'footer');
  }

  /**
   * Tests that mock file used on local environment when GET request fails.
   *
   * @covers ::makeRequest
   * @covers ::get
   * @covers ::__construct
   * @covers ::cache
   * @covers ::getRequestOptions
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\CacheValue::__construct
   * @covers \Drupal\helfi_navigation\ApiResponse::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
   */
  public function testMockFallback() : void {
    // Use logger to verify that mock file is actually used.
    $logger = $this->prophesize(LoggerInterface::class);
    $logger->warning(Argument::containingString('Mock data is used instead.'))
      ->shouldBeCalled();
    $client = $this->createMockHttpClient([
      new ConnectException(
        'Test',
        $this->prophesize(RequestInterface::class)->reveal(),
      ),
    ]);
    $sut = $this->getSut(
      $client,
      logger: $logger->reveal(),
    );
    $response = $sut->get('fi', 'footer-bottom-navigation');
    $this->assertInstanceOf(ApiResponse::class, $response);
  }

  /**
   * Make sure subsequent requests are failed after one failed request.
   *
   * @covers ::makeRequest
   * @covers ::get
   * @covers ::__construct
   * @covers ::cache
   * @covers ::getRequestOptions
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
   */
  public function testFastRequestFailure() : void {
    // Override environment name so we don't fallback to mock responses.
    $this->environmentResolverConfiguration[EnvironmentResolver::ENVIRONMENT_NAME_KEY] = 'test';

    $client = $this->createMockHttpClient([
      new ConnectException(
        'Test',
        $this->prophesize(RequestInterface::class)->reveal(),
      ),
    ]);
    $sut = $this->getSut($client);

    $attempts = 0;
    // Make sure only one request is sent if the first request fails.
    // This should fail to \OutOfBoundsException from guzzle MockHandler
    // if more than one request is sent.
    for ($i = 0; $i < 50; $i++) {
      try {
        $sut->get('fi', 'footer-bottom-navigation');
      }
      catch (ConnectException) {
        $attempts++;
      }
    }
    $this->assertEquals(50, $attempts);
  }

  /**
   * Make sure cache can be bypassed when configured so.
   *
   * @covers ::makeRequest
   * @covers ::get
   * @covers ::__construct
   * @covers ::cache
   * @covers ::getRequestOptions
   * @covers ::withBypassCache
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\CacheValue::hasExpired
   * @covers \Drupal\helfi_navigation\CacheValue::__construct
   * @covers \Drupal\helfi_navigation\ApiResponse::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
   */
  public function testCacheBypass() : void {
    $requests = [];
    $client = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['value' => 1])),
      new Response(200, body: json_encode(['value' => 2])),
    ]);
    $sut = $this->getSut(
      $client,
    );
    // Make sure cache is used for all requests.
    for ($i = 0; $i < 3; $i++) {
      $response = $sut->get('en', 'main');
      $this->assertEquals(1, $response->data->value);
    }
    // Make sure cache is bypassed when configured so and the cached content
    // is updated.
    $response = $sut->withBypassCache()->get('en', 'main');
    $this->assertEquals(2, $response->data->value);

    // withBypassCache() method creates a clone of ApiManager instance to ensure
    // cache is only bypassed when explicitly told so.
    // We defined only two responses, so this should fail to OutOfBoundException
    // if cache was bypassed here.
    for ($i = 0; $i < 3; $i++) {
      $response = $sut->get('en', 'main');
      $this->assertEquals(2, $response->data->value);
    }
  }

}
