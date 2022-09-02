<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
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
   * @param \Drupal\helfi_navigation\Menu\MenuTreeBuilder $menuTreeBuilder
   *   The menu builder.
   */
  public function __construct(
    private ConfigurableLanguageManagerInterface $languageManager,
    private ConfigFactoryInterface $config,
    private ApiManager $apiManager,
    private MenuTreeBuilder $menuTreeBuilder,
    private EnvironmentResolver $environmentResolver
  ) {
  }

  /**
   * Sends main menu tree to frontpage instance.
   */
  public function syncMenu(string $langcode): void {
    if (!$authKey = $this->config->get('helfi_navigation.api')->get('key')) {
      throw new ConfigException('Missing required "helfi_navigation.api" setting.');
    }

    $site_id = $this->environmentResolver->getActiveEnvironment()->getId();
    $tree = $this
      ->menuTreeBuilder
      ->buildMenuTree(Menu::MAIN_MENU, $langcode, $site_id);

    $siteName = $this->languageManager
      ->getLanguageConfigOverride($langcode, 'system.site')
      ->get('name');

    // Fallback to default translation if site name is not translated to
    // given language.
    if (!$siteName) {
      $siteName = $this->config->get('system.site')
        ->getOriginal('name', FALSE);
    }
    $instanceUri = Url::fromRoute('<front>', options: [
      'language' => $this->languageManager->getLanguage($langcode),
    ])->setAbsolute();

    $this->apiManager->updateMainMenu(
      $langcode,
      'Basic ' . $authKey,
      [
        'langcode' => $langcode,
        'site_name' => $siteName,
        'menu_tree' => [
          'name' => $siteName,
          'url' => $instanceUri->toString(),
          'external' => FALSE,
          'hasItems' => !(empty($tree)),
          'weight' => 0,
          'sub_tree' => $tree,
          'id' => $site_id,
        ],
      ]
    );
  }

}
