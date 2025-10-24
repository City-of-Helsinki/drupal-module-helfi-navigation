<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Unit\Plugin\DebugData;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\helfi_api_base\ApiClient\ApiClient;
use Drupal\helfi_api_base\ApiClient\ApiResponse;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\Plugin\DebugDataItem\ApiAvailability;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Exception\TransferException;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\Plugin\DebugDataItem\ApiAvailability
 * @group helfi_navigation
 */
class ApiAvailabilityTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Gets the SUT.
   *
   * @param \Prophecy\Prophecy\ObjectProphecy $apiClient
   *   The API client prophecy.
   *
   * @return \Drupal\helfi_navigation\Plugin\DebugDataItem\ApiAvailability
   *   The SUT.
   */
  public function getSut(ObjectProphecy $apiClient) : ApiAvailability {
    $apiManager = $this->prophesize(ApiManager::class);
    $apiManager->getUrl(Argument::any(), Argument::any(), Argument::any())
      ->willReturn('https://localhost');

    $container = new ContainerBuilder();
    $container->set(ApiManager::class, $apiManager->reveal());
    $container->set('helfi_navigation.api_client', $apiClient->reveal());

    return ApiAvailability::create($container, [], '', []);
  }

  /**
   * Make sure check() fails on request failure.
   */
  public function testGuzzleException(): void {
    $apiClient = $this->prophesize(ApiClient::class);
    $apiClient->makeRequest('GET', 'https://localhost')
      ->shouldBeCalled()
      ->willThrow(new TransferException('Böö'));
    $sut = $this->getSut($apiClient);

    $this->assertFalse($sut->check());
  }

  /**
   * Test successful check().
   */
  public function testCheck(): void {
    $apiClient = $this->prophesize(ApiClient::class);
    $apiClient->makeRequest('GET', 'https://localhost')
      ->shouldBeCalled()
      ->willReturn(new ApiResponse(['boo']));
    $sut = $this->getSut($apiClient);

    $this->assertTrue($sut->check());
  }

}
