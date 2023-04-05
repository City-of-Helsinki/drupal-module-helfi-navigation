<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Drupal\helfi_navigation\Menu\MenuTreeBuilder;
use Drupal\language\ConfigurableLanguageManagerInterface;

/**
 * Menu manager service.
 */
class MainMenuManager {

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
   *   The menu tree builder.
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
   *
   * @param string $langcode
   *   The langcode.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function sync(string $langcode): bool {
    $response = $this->apiManager->update(
      $langcode,
      [
        'langcode' => $langcode,
        'site_name' => $this->getSiteName($langcode),
        'menu_tree' => $this->build($langcode),
      ]
    );
    if (!isset($response->data->status)) {
      throw new \InvalidArgumentException('Failed to parse entity published state.');
    }
    return reset($response->data->status)->value;
  }

  /**
   * Gets the site name.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return string
   *   The site name.
   */
  public function getSiteName(string $langcode) : string {
    $siteName = $this->languageManager
      ->getLanguageConfigOverride($langcode, 'system.site')
      ->get('name');

    // Fallback to default translation if site name is not translated to
    // given language.
    if (!$siteName) {
      $siteName = $this->config->get('system.site')
        ->getOriginal('name', FALSE);
    }

    if (!$siteName) {
      throw new \InvalidArgumentException('Missing "system.site[name]" configuration.');
    }
    return $siteName;
  }

  /**
   * Build local main menu tree.
   *
   * @param string $langcode
   *   Language code.
   *
   * @return mixed
   *   Menu tree.
   */
  public function build(string $langcode = NULL): array {
    $langcode = $langcode ?: $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $siteName = $this->getSiteName($langcode);

    $instanceUri = Url::fromRoute('<front>', options: [
      'language' => $this->languageManager->getLanguage($langcode),
    ]);

    return $this->menuTreeBuilder->build(
      'main',
      $langcode,
      (object) [
        'id' => vsprintf(
          'base:%s',
          [preg_replace('/[^a-z0-9_]+/', '_', strtolower($siteName))]
        ),
        'name' => $siteName,
        'url' => $instanceUri,
      ]
    );
  }

}
