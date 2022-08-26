<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_navigation\Menu\Menu;
use Drupal\helfi_navigation\Menu\MenuTreeBuilder;
use Drupal\language\ConfigurableLanguageManagerInterface;

/**
 * Synchronizes global menu.
 */
class MenuUpdater {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\language\ConfigurableLanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory.
   * @param \Drupal\helfi_navigation\ApiManager $apiManager
   *   The api manager.
   * @param \Drupal\helfi_navigation\Menu\MenuTreeBuilder $menuTreeBuilder
   *   The menu builder.
   */
  public function __construct(
    private ConfigurableLanguageManagerInterface $languageManager,
    private ConfigFactoryInterface $config,
    private ApiManager $apiManager,
    private MenuTreeBuilder $menuTreeBuilder,
  ) {
  }

  /**
   * Sends main menu tree to frontpage instance.
   */
  public function syncMenu(string $langcode): void {
    if (!$authKey = $this->config->get('helfi_navigation.api')->get('key')) {
      throw new ConfigException('Missing required "helfi_navigation.api" setting.');
    }
    $tree = $this
      ->menuTreeBuilder
      ->buildMenuTree(Menu::MAIN_MENU, $langcode);

    $siteName = $this->languageManager
      ->getLanguageConfigOverride($langcode, 'system.site')
      ->get('name');

    // Fallback to default translation if site name is not translated to
    // given language.
    if (!$siteName) {
      $siteName = $this->config->get('system.site')
        ->getOriginal('name', FALSE);
    }

    $this->apiManager->updateMainMenu(
      $langcode,
      'Basic ' . $authKey,
      [
        'langcode' => $langcode,
        'site_name' => $siteName,
        'menu_tree' => [
          'name' => $siteName,
          'external' => FALSE,
          'hasItems' => !(empty($tree)),
          'weight' => 0,
          'sub_tree' => $tree,
        ],
      ]
    );
  }

}
