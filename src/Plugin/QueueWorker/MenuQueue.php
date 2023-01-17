<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
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
  private MainMenuManager $menuManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->menuManager = $container->get('helfi_navigation.menu_manager');
    return $instance;
  }

  /**
   * Process queue item.
   *
   * @param string $langcode
   *   Data of the processable language code.
   *
   * @throws \Exception
   *   Throws exception if language code is not set.
   */
  public function processItem($langcode) {
    if (!is_string($langcode)) {
      return;
    }
    try {
      $this->menuManager->sync($langcode);
    }
    catch (\Throwable) {
      // The failed sync will be logged by ApiManager.
    }
  }

}
