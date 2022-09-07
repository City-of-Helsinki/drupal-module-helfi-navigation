<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Functional;

use Drupal\Tests\helfi_api_base\Functional\BrowserTestBase;

/**
 * Tests menu blocks.
 *
 * @group helfi_navigation
 */
class MenuBlockTest extends BrowserTestBase {

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
    $blocks = _helfi_navigation_get_block_configuration();
    foreach ($blocks as $block) {
      $this->drupalPlaceBlock($block['plugin'], [
        'label' => $block['settings']['label'],
      ]);
    }
    $this->drupalGet('<front>');

    foreach ($blocks as $block) {
      $this->assertSession()->pageTextContains($block['settings']['label']);
    }
  }

}
