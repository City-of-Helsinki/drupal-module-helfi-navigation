<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_navigation\Menu\MainMenuBuilder;
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
   * @param \Drupal\helfi_navigation\Menu\MainMenuBuilder $mainMenuBuilder
   *   The menu builder.
   */
  public function __construct(
    private ConfigurableLanguageManagerInterface $languageManager,
    private ConfigFactoryInterface $config,
    private ApiManager $apiManager,
    private MainMenuBuilder $mainMenuBuilder,
  ) {
  }

  /**
   * Sends main menu tree to frontpage instance.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function syncMenu(string $langcode): bool {
    $siteName = $this->languageManager
      ->getLanguageConfigOverride($langcode, 'system.site')
      ->get('name');

    $response = $this->apiManager->update(
      $langcode,
      [
        'langcode' => $langcode,
        'site_name' => $siteName,
        'menu_tree' => $this->mainMenuBuilder->build(),
      ]
    );
    if (!isset($response->data->status)) {
      throw new \InvalidArgumentException('Failed to parse entity published state.');
    }
    return reset($response->data->status)->value;
  }

}
