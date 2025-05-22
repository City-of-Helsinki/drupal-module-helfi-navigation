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
  protected function getRequestOptions() : string {
    return "max-depth={$this->getMaxDepth()}";
  }

}
