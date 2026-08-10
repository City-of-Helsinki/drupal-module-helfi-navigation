<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel\Plugin\Derivative;

use Drupal\block\Entity\Block;
use Drupal\helfi_navigation\Plugin\Derivative\ExternalMenuBlock;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Entity\Menu;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ExternalMenuBlock deriver.
 */
#[Group('helfi_navigation')]
#[RunTestsInSeparateProcesses]
#[CoversClass(\Drupal\helfi_navigation\Plugin\Derivative\ExternalMenuBlock::class)]
final class ExternalMenuBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'block',
    'language',
    'helfi_api_base',
    'helfi_navigation',
  ];

  /**
   * Expected external menu derivative IDs.
   *
   * @var string[]
   */
  private const EXTERNAL_MENUS = [
    'footer-bottom-navigation',
    'footer-top-navigation',
    'footer-top-navigation-2',
    'header-top-navigation',
    'header-language-links',
  ];

  /**
   * Tests derivative definitions without menu config entities.
   */
  public function testDerivativeDefinitionsWithoutMenus(): void {
    $definitions = $this->getDerivativeDefinitions();

    $this->assertSame(self::EXTERNAL_MENUS, array_keys($definitions));

    foreach (self::EXTERNAL_MENUS as $menu) {
      $expected_label = 'External - ' . ucfirst(str_replace('-', ' ', $menu));
      $this->assertSame($expected_label, $definitions[$menu]['admin_label']);
      $this->assertArrayNotHasKey('config_dependencies', $definitions[$menu]);
    }
  }

  /**
   * Tests derivative definitions include config dependencies when menus exist.
   */
  public function testDerivativeDefinitionsWithMenus(): void {
    foreach (self::EXTERNAL_MENUS as $menu_id) {
      Menu::create([
        'id' => $menu_id,
        'label' => $menu_id,
      ])->save();
    }

    $definitions = $this->getDerivativeDefinitions();

    foreach (self::EXTERNAL_MENUS as $menu_id) {
      $this->assertSame(
        ['config' => ['system.menu.' . $menu_id]],
        $definitions[$menu_id]['config_dependencies']
      );
    }
  }

  /**
   * Tests that an external menu block declares a dependency on its menu.
   */
  public function testBlockConfigDependencies(): void {
    Menu::create([
      'id' => 'footer-bottom-navigation',
      'label' => 'Footer bottom navigation',
    ])->save();

    $this->container->get('plugin.manager.block')->clearCachedDefinitions();

    $block = Block::create([
      'id' => 'external_footer_bottom_navigation_test',
      'plugin' => 'external_menu_block:footer-bottom-navigation',
      'region' => 'content',
      'theme' => 'stark',
    ]);

    $dependencies = $block->calculateDependencies()->getDependencies();
    $this->assertContains('system.menu.footer-bottom-navigation', $dependencies['config']);
    $this->assertContains('helfi_navigation', $dependencies['module']);
  }

  /**
   * Returns derivative definitions from the deriver.
   *
   * @return array<string, array<string, mixed>>
   *   The derivative definitions.
   */
  private function getDerivativeDefinitions(): array {
    $deriver = ExternalMenuBlock::create($this->container, 'external_menu_block');
    return $deriver->getDerivativeDefinitions([
      'id' => 'external_menu_block',
      'provider' => 'helfi_navigation',
    ]);
  }

}
