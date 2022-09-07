<?php

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\Core\Config\ConfigException;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\MenuUpdater;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;

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
    'language',
    'helfi_navigation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');
    $this->installConfig(['language']);

    foreach (['fi', 'sv'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
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
    $this->config('system.site')->set('name', $siteName)->save();
    $this->config('helfi_navigation.api')->set('key', '123')->save();

    $apiManager = $this->createMock(ApiManager::class);
    $apiManager->expects($this->once())
      ->method('updateMainMenu')
      // Capture arguments passed to syncMenu() so we can test them.
      ->will($this->returnCallback(function (string $langcode, array $data) use ($siteName) {
        $this->assertEquals($siteName, $data['site_name']);
        $this->assertEquals('base:site_name_' . $langcode, $data['menu_tree']['id']);
        $this->assertEquals($siteName, $data['menu_tree']['name']);
        $this->assertEquals($langcode, $data['langcode']);
        $this->assertStringStartsWith('http://', $data['menu_tree']['url']);
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

}
