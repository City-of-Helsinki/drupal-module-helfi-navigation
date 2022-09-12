<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Block;

/**
 * Provides an external menu block for global main navigation.
 *
 * @Block(
 *   id = "external_menu_block_main_navigation",
 *   admin_label = @Translation("External menu block - Main global navigation"),
 *   category = @Translation("External menu"),
 * )
 */
final class MainNavigationMenuBlock extends ExternalMenuBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() : string {
    return 'main';
  }

  /**
   * {@inheritdoc}
   */
  protected function getTreeFromResponse(\stdClass $response): array {
    $tree = [];

    foreach ($response as $item) {
      if (!isset($item->menu_tree)) {
        continue;
      }
      $tree[] = reset($item->menu_tree);
    }

    return $tree;
  }

}
