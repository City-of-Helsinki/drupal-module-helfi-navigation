<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Url;
use Drupal\helfi_navigation\ApiResponse;

/**
 * Provides a customisable external menu block for global main navigation.
 *
 * @Block(
 *   id = "customisable_external_menu_block_main_navigation",
 *   admin_label = @Translation("Customisable External menu block - Main global navigation"),
 *   category = @Translation("External menu"),
 * )
 */
final class CustomisableMainNavigationMenuBlock extends ExternalMenuBlockBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() : string {
    // TODO: Can this be main as well ?
    return 'main';
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

  public function build(): array
  {
    $build = parent::build();

    $tree = $this->buildMenuTree();

    // TODO: Add setting which allows to set the "custom" menu as first or last item of the menu.
    $build['#items'] = $tree + $build['#items'];

    return $build;
  }

  private function buildMenuTree() {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $sitename = $this->languageManager
      ->getLanguageConfigOverride($langcode,'system.site')
      ->get('name');

    // Fallback to default translation if site name is not translated to
    // given language.
    if (!$sitename) {
      $sitename = $this->config->get('system.site')
        ->getOriginal('name', FALSE);
    }

    if (!$sitename) {
      throw new \InvalidArgumentException('Missing "system.site[name]" configuration.');
    }

    $instanceUri = Url::fromRoute('<front>', options: [
      'language' => $this->languageManager->getLanguage($langcode),
    ]);

    return $this
      ->menuTreeBuilder
      ->build('main', $langcode, (object) [
        'id' => vsprintf('base:%s', [
          preg_replace('/[^a-z0-9_]+/', '_', strtolower($sitename)),
        ]),
        'name' => $sitename,
        'url' => $instanceUri,
      ]);
  }

}
