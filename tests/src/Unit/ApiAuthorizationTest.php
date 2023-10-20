<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Unit;

use Drupal\helfi_api_base\Vault\AuthorizationToken;
use Drupal\helfi_api_base\Vault\VaultManager;
use Drupal\helfi_navigation\ApiAuthorization;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\ApiAuthorization
 * @group helfi_navigation
 */
class ApiAuthorizationTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::__construct
   * @covers ::getAuthorization
   */
  public function testVaultAuthorization() : void {
    $vaultManager = new VaultManager([
      new AuthorizationToken(ApiAuthorization::VAULT_MANAGER_KEY, '123'),
    ]);
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config */
    $config = $this->getConfigFactoryStub([]);
    $sut = new ApiAuthorization(
      $config,
      $vaultManager,
    );
    $this->assertEquals('123', $sut->getAuthorization());
  }

  /**
   * @covers ::__construct
   * @covers ::getAuthorization
   */
  public function testEmptyAuthorization() : void {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config */
    $config = $this->getConfigFactoryStub(['helfi_navigation.api' => []]);
    $sut = new ApiAuthorization(
      $config,
      new VaultManager([]),
    );
    $this->assertNull($sut->getAuthorization());
  }

  /**
   * @covers ::__construct
   * @covers ::getAuthorization
   */
  public function testFallbackConfigAuthorization() : void {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $config */
    $config = $this->getConfigFactoryStub(['helfi_navigation.api' => ['key' => '123']]);
    $sut = new ApiAuthorization(
      $config,
      new VaultManager([]),
    );
    $this->assertEquals('123', $sut->getAuthorization());
  }

}
