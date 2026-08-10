<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;

/**
 * Provides block plugin definitions for custom menus.
 *
 * @see \Drupal\helfi_navigation\Plugin\Block\ExternalMenuBlock
 */
final class ExternalMenuBlock extends DeriverBase {

  /**
   * The external menus.
   *
   * @var array
   */
  protected array $externalMenus = [
    'footer-bottom-navigation',
    'footer-top-navigation',
    'footer-top-navigation-2',
    'header-top-navigation',
    'header-language-links',
  ];

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, mixed>|\Drupal\Component\Plugin\Definition\PluginDefinitionInterface $base_plugin_definition
   * @phpstan-return array<string, array<string, mixed>>
   */
  public function getDerivativeDefinitions($base_plugin_definition) : array {
    foreach ($this->externalMenus as $menu) {
      $admin_label = ucfirst(str_replace('-', ' ', $menu));
      $this->derivatives[$menu] = $base_plugin_definition;

      if (!is_array($this->derivatives[$menu])) {
        continue;
      }

      $this->derivatives[$menu]['admin_label'] = 'External - ' . $admin_label;
    }
    return $this->derivatives;
  }

}
