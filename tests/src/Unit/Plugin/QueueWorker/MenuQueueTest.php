<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Unit\Plugin\QueueWorker;

use Drupal\helfi_api_base\Azure\PubSub\PubSubManagerInterface;
use Drupal\helfi_api_base\Cache\CacheTagInvalidator;
use Drupal\helfi_api_base\Cache\CacheTagInvalidatorInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\MainMenuManager;
use Drupal\helfi_navigation\Plugin\QueueWorker\MenuQueue;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests menu queue worker.
 *
 * @group helfi_navigation
 */
class MenuQueueTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Constructs a new menu queue object.
   *
   * @param \Prophecy\Prophecy\ObjectProphecy $menuManager
   *   The mocked menu manager.
   *
   * @return \Drupal\helfi_navigation\Plugin\QueueWorker\MenuQueue
   *   The menu queue object.
   */
  public function getSut(ObjectProphecy $menuManager) : MenuQueue {
    $container = new ContainerBuilder();
    $container->set(MainMenuManager::class, $menuManager->reveal());
    $pubSubManager = $this->prophesize(PubSubManagerInterface::class);
    $pubSubManager->sendMessage(Argument::any())->willReturn($pubSubManager->reveal());
    $cacheTagInvalidator = new CacheTagInvalidator($pubSubManager->reveal());
    $container->set('helfi_api_base.cache_tag_invalidator', $cacheTagInvalidator);
    return MenuQueue::create($container, [], '', []);
  }

  /**
   * Tests invalid data.
   */
  public function testInvalidData() : void {
    // Make sure sync is not called for invalid data.
    $menuManager = $this->prophesize(MainMenuManager::class);
    $menuManager->sync(Argument::any())
      ->shouldNotBeCalled();
    $this->getSut($menuManager)->processItem(NULL);
  }

  /**
   * Tests failed sync.
   */
  public function testQueueException() : void {
    // Make sure queue doesn't die if ::sync() throws an exception.
    $menuManager = $this->prophesize(MainMenuManager::class);
    $menuManager->sync(Argument::any())
      ->shouldBeCalled()
      ->willThrow(new \InvalidArgumentException());
    $this->getSut($menuManager)
      ->processItem(['menu' => 'main', 'language' => 'fi']);
  }

  /**
   * Tests cache invalidation.
   */
  public function testCacheInvalidator() : void {
    $container = new ContainerBuilder();
    $menuManager = $this->prophesize(MainMenuManager::class);
    $menuManager->sync('fi')
      ->shouldBeCalled();
    $container->set(MainMenuManager::class, $menuManager->reveal());
    $cacheTagInvalidator = $this->prophesize(CacheTagInvalidatorInterface::class);
    $cacheTagInvalidator->invalidateTags([
      'config:system.menu.main',
      'external_menu_block:main',
      'external_menu:main:fi',
    ])
      ->shouldBeCalled();
    $cacheTagInvalidator->invalidateTags([
      'config:rest.resource.helfi_global_menu_collection',
    ], [Project::ETUSIVU])
      ->shouldBeCalled();

    $container->set('helfi_api_base.cache_tag_invalidator', $cacheTagInvalidator->reveal());
    $sut = MenuQueue::create($container, [], '', []);
    $sut->processItem(['menu' => 'main', 'language' => 'fi']);
  }

}
