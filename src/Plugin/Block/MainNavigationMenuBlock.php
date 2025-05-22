<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an external menu block for global main navigation.
 */
#[Block(
  id: "external_menu_block_main_navigation",
  admin_label: new TranslatableMarkup("External menu block - Main global navigation"),
  category: new TranslatableMarkup("External menu"),
)]
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
