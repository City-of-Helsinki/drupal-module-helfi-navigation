<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Unit\Plugin\QueueWorker;

use Drupal\helfi_api_base\Azure\PubSub\PubSubManagerInterface;
use Drupal\helfi_api_base\Cache\CacheTagInvalidator;
use Drupal\helfi_navigation\MainMenuManager;
use Drupal\helfi_navigation\Plugin\QueueWorker\MenuQueue;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\Plugin\QueueWorker\MenuQueue
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
    $container->set('helfi_navigation.menu_manager', $menuManager->reveal());
    $pubSubManager = $this->prophesize(PubSubManagerInterface::class);
    $pubSubManager->sendMessage(Argument::any())->willReturn($pubSubManager->reveal());
    $cacheTagInvalidator = new CacheTagInvalidator($pubSubManager->reveal());
    $container->set('helfi_api_base.cache_tag_invalidator', $cacheTagInvalidator);
    return MenuQueue::create($container, [], '', []);
  }

  /**
   * @covers ::create
   * @covers ::processItem
   */
  public function testInvalidData() : void {
    // Make sure sync is not called for invalid data.
    $menuManager = $this->prophesize(MainMenuManager::class);
    $menuManager->sync(Argument::any())
      ->shouldNotBeCalled();
    $this->getSut($menuManager)->processItem(NULL);
  }

  /**
   * @covers ::create
   * @covers ::processItem
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

}
