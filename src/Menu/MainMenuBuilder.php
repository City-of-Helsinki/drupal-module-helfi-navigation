<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Menu;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\language\ConfigurableLanguageManagerInterface;

/**
 * Build main menu tree.
 */
class MainMenuBuilder {
  /**
   * Constructs a new instance.
   *
   * @param \Drupal\language\ConfigurableLanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   * @param \Drupal\helfi_navigation\Menu\MenuTreeBuilder $menuTreeBuilder
   *   The menu builder.
   */
  public function __construct(
    private ConfigurableLanguageManagerInterface $languageManager,
    private ConfigFactoryInterface $config,
    private MenuTreeBuilder $menuTreeBuilder,
  ) {
  }

  /**
   * Build local menu tree.
   *
   * @param string $menuId
   *   Menu id.
   * @param $langcode
   *   Language code.
   *
   * @return mixed
   *   Menu tree.
   */
  public function buildLocalMenuTree(string $menuId = 'main', string $langcode = NULL): array {
    $langcode = $langcode ?: $this->languageManager->getCurrentLanguage()->getId();
    $instanceUri = Url::fromRoute('<front>', options: [
      'language' => $this->languageManager->getLanguage($langcode),
    ]);

    $sitename = $this->languageManager
      ->getLanguageConfigOverride($langcode, 'system.site')
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

    return $this->menuTreeBuilder->build(
      $menuId,
      $langcode,
      (object) [
        'id' => vsprintf(
          'base:%s',
          [preg_replace('/[^a-z0-9_]+/', '_', strtolower($sitename))]
        ),
        'name' => $sitename,
        'url' => $instanceUri,
      ]
    );
  }

}
