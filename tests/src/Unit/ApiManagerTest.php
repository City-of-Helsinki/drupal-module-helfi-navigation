<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\ApiManager;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\ApiManager
 * @group helfi_navigation
 */
class ApiManagerTest extends UnitTestCase {

  use ApiTestTrait;

  /**
   * Constructs a new api manager instance.
   *
   * @param \GuzzleHttp\ClientInterface $client
   *   The http client.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   *
   * @return \Drupal\helfi_navigation\ApiManager
   *   The api manager instance.
   */
  private function getSut(ClientInterface $client, LoggerInterface $logger = NULL) : ApiManager {
    if (!$logger) {
      $logger = $this->prophesize(LoggerInterface::class)->reveal();
    }
    $environmentResolver = new EnvironmentResolver('', $this->getConfigFactoryStub([
      'helfi_api_base.environment_resolver.settings' => [
        'project_name' => Project::ASUMINEN,
        'environment_name' => 'local',
      ],
    ]));

    $cache = $this->prophesize(CacheBackendInterface::class);
    $cache->get(Argument::any())
      ->willReturn(FALSE);
    $cache->set(Argument::any(), Argument::any(), CacheBackendInterface::CACHE_PERMANENT, Argument::any())->shouldBeCalled();
    return new ApiManager($cache->reveal(), $client, $environmentResolver, $logger);
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
    $sut = $this->getSut($client);
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
    $sut = $this->getSut($client);
    $response = $sut->getExternalMenu('fi', 'main');
    $this->assertInstanceOf(\stdClass::class, $response);
    $this->assertInstanceOf(RequestInterface::class, $requests[0]['request']);
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
    $sut = $this->getSut($client);
    $response = $sut->getMainMenu('fi');
    $this->assertInstanceOf(\stdClass::class, $response);
    $this->assertInstanceOf(RequestInterface::class, $requests[0]['request']);
  }

  /**
   * Make sure we log the exception and then re-throw the same exception.
   *
   * @covers ::makeRequest
   * @covers ::getExternalMenu
   * @covers ::__construct
   */
  public function testRequestLoggingException() : void {
    $client = $this->createMockHttpClient([
      new RequestException('Test', $this->prophesize(RequestInterface::class)->reveal()),
    ]);
    $this->expectException(GuzzleException::class);
    $logger = $this->prophesize(LoggerInterface::class);
    $logger->error(Argument::any())
      ->shouldBeCalled();
    $sut = $this->getSut($client, $logger->reveal());
    $sut->getExternalMenu('fi', 'footer');
  }

}
