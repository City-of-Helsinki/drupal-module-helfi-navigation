<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\Core\Config\ConfigException;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\ApiResponse;
use Drupal\helfi_navigation\MenuUpdater;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\system\Entity\Menu;

/**
 * Tests navigation sync.
 *
 * @group helfi_navigation
 */
class MenuSyncTest extends KernelTestBase {

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
   * Gets the menu updater service.
   *
   * @return \Drupal\helfi_navigation\MenuUpdater
   *   The menu updater service.
   */
  private function getMenuUpdater() : MenuUpdater {
    return $this->container->get('helfi_navigation.menu_updater');
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

  /**
   * Make sure entity hooks triggers the menu sync.
   */
  public function testHooks() : void {
    $this->config('system.site')->set('name', 'Site name')->save();
    $this->config('helfi_navigation.api')->set('key', '123')->save();

    $menuUpdater = $this->prophesize(MenuUpdater::class);
    // Make sure syncMenu is called for:
    // - menu link update
    // - menu link insert
    // - menu link delete
    // - menu update (will fallback to english langcode).
    $menuUpdater->syncMenu('fi')->shouldBeCalledTimes(3);
    $menuUpdater->syncMenu('en')->shouldBeCalledTimes(1);
    $this->container->set('helfi_navigation.menu_updater', $menuUpdater->reveal());

    $menuLink = MenuLinkContent::create([
      'menu_name' => 'main',
      'title' => 'link',
      'langcode' => 'fi',
      'link' => ['uri' => 'internal:/test-page'],
    ]);
    $menuLink->save();
    $menuLink->set('title', '123')->save();
    $menuLink->delete();
    Menu::load('main')->save();
  }

}
