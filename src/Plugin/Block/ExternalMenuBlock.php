<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageInterface;

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
  public function getCacheTags() : array {
    return Cache::mergeTags([$this->getCacheKey()], parent::getCacheTags());
  }

  /**
   * Get cache key for the menu block.
   *
   * @return string
   *   Returns cache key as string.
   */
  protected function getCacheKey(): string {
    $menu_type = $this->getDerivativeId();
    return sprintf('external_menu_block:%s', $menu_type);
  }

  /**
   * {@inheritdoc}
   */
  protected function buildMenuTree(): array {
    $json = $this
      ->apiManager
      ->get(
        $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId(),
        $this->getDerivativeId()
      );
    $menu = [];
    // @todo Support more than one level.
    foreach ($json->data as $item) {
      $menu[] = (object) [
        'name' => $item->attributes->title,
        'url' => $item->attributes->url,
        'parentId' => $item->attributes->parent,
        'external' => $item->attributes->options->external ?? FALSE,
        'weight' => $item->attributes->weight,
        'id' => $item->id,
        'is_expanded' => $item->attributes->expanded,
      ];
    }
    return $menu;
  }

}
