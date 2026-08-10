<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel\Plugin\Derivative;

use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\helfi_navigation\Plugin\Derivative\ExternalMenuBlock;
use Drupal\KernelTests\KernelTestBase;
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
   * Tests derivative definitions with an array base plugin definition.
   */
  public function testDerivativeDefinitionsWithArray(): void {
    $deriver = new ExternalMenuBlock();
    $definitions = $deriver->getDerivativeDefinitions([
      'id' => 'external_menu_block',
      'provider' => 'helfi_navigation',
    ]);

    $this->assertSame(self::EXTERNAL_MENUS, array_keys($definitions));

    foreach (self::EXTERNAL_MENUS as $menu) {
      $expected_label = 'External - ' . ucfirst(str_replace('-', ' ', $menu));
      $this->assertSame($expected_label, $definitions[$menu]['admin_label']);
      $this->assertArrayNotHasKey('config_dependencies', $definitions[$menu]);
    }
  }

  /**
   * Tests derivative definitions with a PluginDefinitionInterface object.
   *
   * Object-based definitions are returned as-is; admin_label is not set.
   */
  public function testDerivativeDefinitionsWithPluginDefinitionObject(): void {
    $base_plugin_definition = $this->createMock(PluginDefinitionInterface::class);

    $deriver = new ExternalMenuBlock();
    $definitions = $deriver->getDerivativeDefinitions($base_plugin_definition);

    $this->assertSame(self::EXTERNAL_MENUS, array_keys($definitions));

    foreach (self::EXTERNAL_MENUS as $menu) {
      $this->assertSame($base_plugin_definition, $definitions[$menu]);
    }
  }

}
