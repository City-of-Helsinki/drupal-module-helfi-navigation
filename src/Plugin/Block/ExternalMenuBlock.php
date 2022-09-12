<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

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
  protected function getTreeFromResponse(\stdClass $response): array {
    $tree = [];
    // @todo Support more than one level.
    foreach ($response->data as $item) {
      $tree[] = (object) [
        'name' => $item->attributes->title,
        'url' => $item->attributes->url,
        'parentId' => $item->attributes->parent,
        'external' => $item->attributes->options->external ?? FALSE,
        'weight' => $item->attributes->weight,
        'id' => $item->id,
        'is_expanded' => $item->attributes->expanded,
      ];
    }
    return $tree;
  }

}
