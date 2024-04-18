<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\helfi_api_base\ApiClient\ApiResponse;

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
  protected function getRequestOptions() : array {
    return [
      'query' => 'max-depth=' . $this->getMaxDepth(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getTreeFromResponse(ApiResponse $response): array {
    $tree = [];

    foreach ($response->data as $item) {
      if (!isset($item->menu_tree)) {
        continue;
      }
      $tree[] = reset($item->menu_tree);
    }

    return $tree;
  }

}
