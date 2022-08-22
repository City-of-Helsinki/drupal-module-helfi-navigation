<?php

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\Core\Config\ConfigException;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\helfi_navigation\MenuUpdater;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests navigation sync.
 *
 * @group helfi_navigation
 */
class MenuSyncTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'link',
    'user',
    'menu_link_content',
    'helfi_api_base',
    'helfi_navigation',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');
  }

  /**
   * Gets the queue.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue.
   */
  private function getQueue() : QueueInterface {
    return $this->container->get('queue')->get('helfi_navigation_menu_queue');
  }

  /**
   * Throws the given exception on syncMenu().
   *
   * @param \Exception $exception
   *   The exception to throw.
   */
  private function menuUpdaterExceptionMock(\Exception $exception) {
    $mock = $this->getMockBuilder(MenuUpdater::class)
      ->disableOriginalConstructor()
      ->getMock();
    $mock->method('syncMenu')
      ->willThrowException($exception);
    $this->container->set('helfi_navigation.menu_updater', $mock);
  }

  /**
   * Make sure undefined language fallbacks to default language.
   */
  public function testLanguageFallback() : void {
    $this->menuUpdaterExceptionMock(new \Exception());
    $queue = $this->getQueue();

    // Make sure undefined language fallbacks to default language.
    // This also tests that item is queued on exception.
    _helfi_navigation_queue_item(LanguageInterface::LANGCODE_NOT_SPECIFIED);
    $this->assertEquals(1, $queue->numberOfItems());
    $this->assertEquals('en', $queue->claimItem()->data);
  }

  /**
   * Make sure item is not queued when API key is not set.
   */
  public function testConfigException() : void {
    $this->menuUpdaterExceptionMock(new ConfigException());
    $queue = $this->getQueue();

    _helfi_navigation_queue_item('fi');
    $this->assertEquals(0, $queue->numberOfItems());
  }

}
