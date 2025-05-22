<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\helfi_navigation\ExternalMenuLazyBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\block\Entity\Block;

/**
 * Kernel test for the Main Navigation menu block.
 *
 * @group helfi_navigation
 */
final class MainNavigationMenuBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'block',
    'language',
    'helfi_navigation',
    'helfi_api_base',
  ];

  /**
   * Tests lazy builder structure and rendered output.
   */
  public function testMainNavigationBlockRender(): void {
    $block = Block::create([
      'id' => 'main_navigation_test',
      'plugin' => 'external_menu_block_main_navigation',
      'region' => 'content',
      'theme' => 'stark',
      'settings' => [
        'id' => 'external_menu_main',
        'depth' => 4,
        'level' => 0,
        'expand_all_items' => TRUE,
      ],
    ]);
    $block->save();

    $plugin = $block->getPlugin();
    $build = $plugin->build();

    // Basic structure checks.
    $this->assertIsArray($build);
    $this->assertArrayHasKey('#lazy_builder', $build);
    $this->assertEquals('menu__external_menu', $build['#theme']);
    $this->assertArrayHasKey('#cache', $build);
    $this->assertTrue($build['#create_placeholder']);

    // Lazy builder options test.
    [
      $menuId,
      $langcode,
      $requestOptions,
      $maxDepth,
      $startingLevel,
      $expandAllItems,
      $themeSuggestion,
    ] = $build['#lazy_builder'][1];

    $this->assertEquals('main', $menuId);
    $this->assertEquals('en', $langcode);
    $this->assertEquals('max-depth=4', $requestOptions);
    $this->assertEquals(4, $maxDepth);
    $this->assertEquals(0, $startingLevel);
    $this->assertTrue($expandAllItems);
    $this->assertEquals('menu__external_menu__main', $themeSuggestion);

    // Test the lazy builder.
    // The output should contain only the cache max-age value.
    $sut = $this->container->get(ExternalMenuLazyBuilder::class);
    $output = $sut->build($menuId, $langcode, $requestOptions, $maxDepth, $startingLevel, $expandAllItems, $themeSuggestion);
    $this->assertIsArray($output);
    $this->assertArrayHasKey('#cache', $output);
    $this->assertEquals(60, $output['#cache']['max-age']);
  }

}
