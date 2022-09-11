<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\helfi_navigation\Plugin\Derivative\ExternalMenuBlock;

/**
 * A service to prefetch all menu links.
 */
final class CacheWarmer {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $tempStoreFactory
   *   The temp store factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\helfi_navigation\ApiManager $apiManager
   *   The api manager.
   */
  public function __construct(
    private SharedTempStoreFactory $tempStoreFactory,
    private LanguageManagerInterface $languageManager,
    private ApiManager $apiManager,
  ) {
  }

  /**
   * Invalidate cache tags for given menu and language.
   *
   * @param mixed $data
   *   The data.
   * @param string $language
   *   The language.
   * @param string $menuName
   *   The menu name.
   */
  private function invalidateTags(mixed $data, string $language, string $menuName) : void {
    $key = sprintf('%s:%s', $language, $menuName);
    $storage = $this->tempStoreFactory->get('external_menu_hashes');

    $currentHash = $storage->get($key);
    $hash = hash('sha256', serialize($data));

    // Only invalidate tags if content has actually changed.
    if ($currentHash === $hash) {
      return;
    }
    $storage->set($key, $hash);
    Cache::invalidateTags(['config:system.menu.' . $menuName]);
  }

  /**
   * Warm caches for all available external menus.
   */
  public function warm() : void {
    $plugin = new ExternalMenuBlock();
    $derives = array_keys($plugin->getDerivativeDefinitions([]));

    foreach ($this->languageManager->getLanguages() as $language) {
      foreach ($derives as $name) {
        try {
          $response = $this->apiManager
            ->withBypassCache()
            ->getExternalMenu($language->getId(), $name);
          $this->invalidateTags($response->data, $language->getId(), $name);
        }
        catch (\Exception) {
        };
      }
      try {
        $response = $this->apiManager
          ->withBypassCache()
          ->getMainMenu($language->getId());
        $this->invalidateTags($response, $language->getId(), 'main');
      }
      catch (\Exception) {
      }
    }
  }

}
