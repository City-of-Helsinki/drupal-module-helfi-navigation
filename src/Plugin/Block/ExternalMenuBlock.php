<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\helfi_api_base\ApiClient\ApiResponse;

/**
 * Provides an external menu block.
 *
 * @Block(
 *   id = "external_menu_block",
 *   admin_label = @Translation("External menu block"),
 *   category = @Translation("External menu"),
 *   deriver = "Drupal\helfi_navigation\Plugin\Derivative\ExternalMenuBlock"
 * )
 */
final class ExternalMenuBlock extends ExternalMenuBlockBase {

  /**
   * {@inheritdoc}
   */
  protected function getTreeFromResponse(ApiResponse $response) : array {
    return $response->data;
  }

}
