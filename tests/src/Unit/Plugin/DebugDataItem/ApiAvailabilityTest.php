<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Unit\Plugin\DebugData;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\Plugin\DebugDataItem\ApiAvailability;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\Plugin\DebugDataItem\ApiAvailability
 * @group helfi_navigation
 */
class ApiAvailabilityTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Gets the SUT.
   *
   * @param bool $returnValue
   *   Expected return value for ApiManager::ping().
   *
   * @return \Drupal\helfi_navigation\Plugin\DebugDataItem\ApiAvailability
   *   The SUT.
   */
  public function getSut(bool $returnValue): ApiAvailability {
    $apiManager = $this->prophesize(ApiManager::class);
    $apiManager->ping()->willReturn($returnValue);

    $container = new ContainerBuilder();
    $container->set(ApiManager::class, $apiManager->reveal());
    return ApiAvailability::create($container, [], '', []);
  }

  /**
   * Test successful check().
   */
  public function testCheck(): void {
    $this->assertFalse($this->getSut(FALSE)->check());
  }

  /**
   * Tests failed check.
   */
  public function testFailedCheck(): void {
    $this->assertTrue($this->getSut(TRUE)->check());
  }

}
