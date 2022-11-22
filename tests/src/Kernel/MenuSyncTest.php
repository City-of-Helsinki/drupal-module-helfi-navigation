<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\Core\Config\ConfigException;
use Drupal\Core\Queue\QueueInterface;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\ApiResponse;
use Drupal\helfi_navigation\MenuUpdater;
use Drupal\Tests\helfi_navigation\Traits\MenuLinkTrait;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests navigation sync.
 *
 * @group helfi_navigation
 */
class MenuSyncTest extends KernelTestBase {

  use ProphecyTrait;
  use MenuLinkTrait;

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
   * Gets the menu updater service.
   *
   * @return \Drupal\helfi_navigation\MenuUpdater
   *   The menu updater service.
   */
  private function getMenuUpdater() : MenuUpdater {
    return $this->container->get('helfi_navigation.menu_updater');
  }

  /**
   * Make sure nothing gets queued when api key is not set.
   */
  public function testQueueNoApiKey() : void {
    $queue = $this->getQueue();

    _helfi_navigation_queue_item();
    $this->assertEquals(0, $queue->numberOfItems());
  }

  /**
   * Make sure items are queued only once.
   */
  public function testQueue() : void {
    $this->config('helfi_navigation.api')->set('key', '123')->save();
    $queue = $this->getQueue();

    _helfi_navigation_queue_item();
    $this->assertEquals(3, $queue->numberOfItems());
  }

  /**
   * Asserts number of items placed in queue.
   *
   * @param int $expected
   *   The expected queue count.
   * @param bool $clear
   *   Whether to clear queue or not.
   */
  public function assertQueueCount(int $expected, bool $clear = TRUE) : void {
    $queue = $this->getQueue();
    $this->assertEquals($expected, $queue->numberOfItems());

    if ($clear) {
      $queue->deleteQueue();
      $this->assertEquals(0, $queue->numberOfItems());
    }
  }

  /**
   * Tests entity insert/update/delete hooks.
   */
  public function testEntityHooks() : void {
    $this->config('helfi_navigation.api')->set('key', '123')->save();

    // Make sure entity insert queues items.
    $link = $this->createTestLink(['link' => ['uri' => 'internal:/']]);
    $this->assertQueueCount(3);

    // Make sure entity update queues items.
    $link->save();
    $this->assertQueueCount(3);

    // Make sure entity delete.
    $link->delete();
    $this->assertQueueCount(3);
  }

  /**
   * Tests syncMenu() without site name.
   */
  public function testSyncMenuMissingSiteName() : void {
    $this->expectException(\InvalidArgumentException::class);
    $this->getMenuUpdater()->syncMenu('fi');
  }

  /**
   * Tests syncMenu() without api key.
   */
  public function testSyncMenuMissingApiKey() : void {
    $this->config('system.site')->set('name', 'Site name')->save();
    $this->expectException(ConfigException::class);
    $this->getMenuUpdater()->syncMenu('fi');
  }

  /**
   * Make sure syncMenu() is called with correct values.
   *
   * @dataProvider configTranslationData
   */
  public function testConfigTranslation(string $langcode) : void {
    $siteName = 'Site name ' . $langcode;
    $this->populateConfiguration($siteName);

    $apiManager = $this->createMock(ApiManager::class);
    $apiManager->expects($this->once())
      ->method('update')
      // Capture arguments passed to syncMenu() so we can test them.
      ->will($this->returnCallback(function (string $langcode, array $data) use ($siteName) {
        $this->assertEquals($siteName, $data['site_name']);
        $this->assertEquals('base:site_name_' . $langcode, $data['menu_tree']['id']);
        $this->assertEquals($siteName, $data['menu_tree']['name']);
        $this->assertEquals($langcode, $data['langcode']);
        $this->assertStringStartsWith('http://', $data['menu_tree']['url']);

        return new ApiResponse((object) [
          'status' => [
            (object) ['value' => TRUE],
          ],
        ]);
      }));
    $this->container->set('helfi_navigation.api_manager', $apiManager);

    $this->getMenuUpdater()->syncMenu($langcode);
  }

  /**
   * A data provider for testConfigTranslation().
   *
   * @return \string[][]
   *   The data.
   */
  public function configTranslationData() : array {
    return [
      ['fi'],
      ['en'],
      ['sv'],
    ];
  }

  /**
   * Tests entity status when API returns an empty response.
   */
  public function testEmptyStatus() : void {
    $this->populateConfiguration('Test');
    $apiManager = $this->createMock(ApiManager::class);
    $apiManager->expects($this->once())
      ->method('update')
      ->willReturn(new ApiResponse([]));
    $this->container->set('helfi_navigation.api_manager', $apiManager);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Failed to parse entity published state.');
    $this->getMenuUpdater()->syncMenu('fi');
  }

}
