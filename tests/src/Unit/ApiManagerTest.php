<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Config\ConfigException;
use Drupal\helfi_api_base\ApiClient\ApiClient;
use Drupal\helfi_api_base\ApiClient\ApiResponse;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_api_base\Vault\AuthorizationToken;
use Drupal\helfi_api_base\Vault\VaultManager;
use Drupal\helfi_navigation\ApiAuthorization;
use Drupal\helfi_navigation\ApiManager;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
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
   * Create a new api client mock object.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The http client.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time prophecy.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface|null $environmentResolver
   *   The environment resolver.
   *
   * @return \Drupal\helfi_api_base\ApiClient\ApiClient
   *   The api client.
   */
  private function getApiClientMock(
      ClientInterface $httpClient,
      TimeInterface $time = NULL,
      EnvironmentResolverInterface $environmentResolver = NULL,
  ): ApiClient {
    if (!$time) {
      $time = $this->getTimeMock(time())->reveal();
    }

    if (!$environmentResolver) {
      $environmentResolver = new EnvironmentResolver('', $this->getConfigFactoryStub([
        'helfi_api_base.environment_resolver.settings' => $this->environmentResolverConfiguration,
      ]));
    }

    $logger = $this->prophesize(LoggerInterface::class)->reveal();

    return new ApiClient(
      $httpClient,
      $this->cache,
      $time,
      $environmentResolver,
      $logger,
    );
  }

  /**
   * Constructs a new api manager instance.
   *
   * @param \Drupal\helfi_api_base\ApiClient\ApiClient $client
   *   The http client.
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface|null $environmentResolver
   *   The environment resolver.
   * @param string|null $apiKey
   *   The api key.
   *
   * @return \Drupal\helfi_navigation\ApiManager
   *   The api manager instance.
   */
  private function getSut(
    ApiClient $client,
    EnvironmentResolverInterface $environmentResolver = NULL,
    ?string $apiKey = '123',
  ) : ApiManager {

    if (!$environmentResolver) {
      $environmentResolver = new EnvironmentResolver('', $this->getConfigFactoryStub([
        'helfi_api_base.environment_resolver.settings' => $this->environmentResolverConfiguration,
      ]));
    }
    return new ApiManager(
      $client,
      $environmentResolver,
      new ApiAuthorization(
        $this->getConfigFactoryStub(),
        new VaultManager($apiKey ? [
          new AuthorizationToken(ApiAuthorization::VAULT_MANAGER_KEY, $apiKey),
        ] : []),
      ),
    );
  }

  /**
   * Tests missing api key.
   *
   * @covers ::__construct
   * @covers ::update
   * @covers ::getUrl
   * @covers ::hasAuthorization
   * @covers ::getAuthorization
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
   */
  public function testMissingApiKey() : void {
    $this->expectException(ConfigException::class);
    $httpClient = $this->createMockHttpClient([]);
    $sut = $this->getSut($this->getApiClientMock($httpClient), apiKey: NULL);
    $sut->update('fi', ['key' => 'value']);
  }

  /**
   * Tests updateMainMenu().
   *
   * @covers ::__construct
   * @covers ::update
   * @covers ::getUrl
   * @covers ::hasAuthorization
   * @covers ::getAuthorization
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   * @covers \Drupal\helfi_navigation\ApiAuthorization::getAuthorization
   */
  public function testUpdateMainMenu() : void {
    $requests = [];
    $httpClient = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);

    $sut = $this->getSut($this->getApiClientMock($httpClient));
    $sut->update('fi', ['key' => 'value']);

    $this->assertCount(1, $requests);
    // Make sure Authorization header was set.
    $this->assertEquals('Basic 123', $requests[0]['request']->getHeader('Authorization')[0]);
  }

  /**
   * Tests main menu.
   *
   * @covers ::__construct
   * @covers ::get
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   */
  public function testGetMainMenu() : void {
    $requests = [];
    $httpClient = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode([])),
      new Response(200, body: json_encode(['key' => 'value'])),
    ]);
    $sut = $this->getSut($this->getApiClientMock($httpClient));
    // Test empty and non-empty response.
    for ($i = 0; $i < 2; $i++) {
      $response = $sut->get('fi', 'main');
      $this->assertInstanceOf(ApiResponse::class, $response);
    }
    // Make sure cache is used (request queue should be empty).
    $sut->get('fi', 'main');
  }

  /**
   * Make sure cache can be bypassed when configured so.
   *
   * @covers ::get
   * @covers ::__construct
   * @covers ::withBypassCache
   * @covers ::getUrl
   * @covers \Drupal\helfi_navigation\ApiAuthorization::__construct
   */
  public function testCacheBypass() : void {
    $requests = [];
    $httpClient = $this->createMockHistoryMiddlewareHttpClient($requests, [
      new Response(200, body: json_encode(['value' => 1])),
      new Response(200, body: json_encode(['value' => 2])),
    ]);
    $sut = $this->getSut(
      $this->getApiClientMock($httpClient),
    );
    // Make sure cache is used for all requests.
    for ($i = 0; $i < 3; $i++) {
      $response = $sut->get('en', 'main');
      $this->assertInstanceOf(\stdClass::class, $response->data);
      $this->assertEquals(1, $response->data->value);
    }

    // Make sure cache is bypassed when configured so and the cached content
    // is updated.
    $response = $sut->withBypassCache()->get('en', 'main');
    $this->assertInstanceOf(\stdClass::class, $response->data);
    $this->assertEquals(2, $response->data->value);

    // withBypassCache() method creates a clone of ApiManager instance to ensure
    // cache is only bypassed when explicitly told so.
    // We defined only two responses, so this should fail to OutOfBoundException
    // if cache was bypassed here.
    for ($i = 0; $i < 3; $i++) {
      $response = $sut->get('en', 'main');
      $this->assertInstanceOf(\stdClass::class, $response->data);
      $this->assertEquals(2, $response->data->value);
    }
  }

}
