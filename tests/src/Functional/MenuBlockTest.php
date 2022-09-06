<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Functional;

use Drupal\Tests\helfi_api_base\Functional\BrowserTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\WebServerTestTrait;

/**
 * Tests menu blocks.
 *
 * @group helfi_navigation
 */
class MenuBlockTest extends BrowserTestBase {

  use ApiTestTrait;
  use WebServerTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'helfi_navigation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Make sure menu block can be placed.
   */
  public function testExternalMenuBlock() : void {
    $this->drupalPlaceBlock('external_menu_block_fallback', [
      'label' => 'External menu fallback',
    ]);
    $this->drupalGet('<front>');
  }

}
