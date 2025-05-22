<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides an external menu block.
 */
#[Block(
  id: "external_menu_block",
  admin_label: new TranslatableMarkup("External menu block"),
  category: new TranslatableMarkup("External menu"),
  deriver: "Drupal\helfi_navigation\Plugin\Derivative\ExternalMenuBlock",
)]
final class ExternalMenuBlock extends ExternalMenuBlockBase {
}
