<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\helfi_api_base\Cache\CacheTagInvalidator;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\MainMenuManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes menu sync tasks.
 *
 * @QueueWorker(
 *  id = "helfi_navigation_menu_queue",
 *  title = @Translation("Queue worker for menu synchronization"),
 *  cron = {"time" = 15}
 * )
 */
final class MenuQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The menu manager service.
   *
   * @var \Drupal\helfi_navigation\MainMenuManager
   */
  private MainMenuManager $mainMenuManager;

  /**
   * The cache tag invalidator service.
   *
   * @var \Drupal\helfi_api_base\Cache\CacheTagInvalidator
   */
  private CacheTagInvalidator $cacheTagInvalidator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->mainMenuManager = $container->get('helfi_navigation.menu_manager');
    $instance->cacheTagInvalidator = $container->get('helfi_api_base.cache_tag_invalidator');
    return $instance;
  }

  /**
   * Process queue item.
   *
   * @param array|mixed $data
   *   The queue data. Should contain 'menu' and 'language'.
   *
   * @throws \Exception
   *   Throws exception if language code is not set.
   */
  public function processItem($data) : void {
    if (!isset($data['menu'], $data['language'])) {
      return;
    }

    ['menu' => $menuName, 'language' => $language] = $data;

    if ($menuName === 'main') {
      try {
        $this->mainMenuManager->sync($language);
      }
      catch (\Throwable) {
        // The failed sync will be logged by ApiManager.
      }
    }

    $this->cacheTagInvalidator->invalidateTags([
      // These are used by the menu block itself and local Global mobile menu
      // REST API endpoint.
      sprintf('config:system.menu.%s', $menuName),
      sprintf('external_menu_block:%s', $menuName),
      // This is used by ApiManager service to cache the API response
      // locally.
      sprintf('external_menu:%s:%s', $menuName, $language),
    ]);
    $this->cacheTagInvalidator->invalidateTags([
      // This is used by REST API collection endpoint on Etusivu.
      'config:rest.resource.helfi_global_menu_collection',
    ], [Project::ETUSIVU]);

  }

}
