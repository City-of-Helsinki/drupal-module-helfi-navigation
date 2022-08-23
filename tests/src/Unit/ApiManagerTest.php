<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Unit;

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
  private function getSut(ClientInterface $client, LoggerInterface $logger) : ApiManager {
    $environmentResolver = new EnvironmentResolver('', $this->getConfigFactoryStub([
      'helfi_api_base.environment_resolver.settings' => [
        'project_name' => Project::ASUMINEN,
        'environment_name' => 'local',
      ],
    ]));

    return new ApiManager($client, $environmentResolver, $logger);
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
    $sut = $this->getSut($client, $this->prophesize(LoggerInterface::class)->reveal());
    $sut->updateMainMenu('fi', '123', ['key' => 'value']);

    $this->assertCount(1, $requests);
    // Make sure SSL verification is disabled on local.
    $this->assertFalse($requests[0]['options']['verify']);
    // Make sure Authorization header was set.
    $this->assertEquals('123', $requests[0]['request']->getHeader('Authorization')[0]);
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
