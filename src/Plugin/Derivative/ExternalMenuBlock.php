<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides block plugin definitions for custom menus.
 *
 * @see \Drupal\helfi_navigation\Plugin\Block\ExternalMenuBlock
 */
final class ExternalMenuBlock extends DeriverBase implements ContainerDeriverInterface {

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
   * Constructs a new ExternalMenuBlock instance.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $menuStorage
   *   The menu storage.
   */
  public function __construct(
    protected readonly EntityStorageInterface $menuStorage,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) : static {
    return new static(
      $container->get('entity_type.manager')->getStorage('menu')
    );
  }

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

      // Add config dependency for the menu entity.
      $menu_entity = $this->menuStorage->load($menu);
      if ($menu_entity) {
        $this->derivatives[$menu]['config_dependencies']['config'] = [$menu_entity->getConfigDependencyName()];
      }
    }
    return $this->derivatives;
  }

}
