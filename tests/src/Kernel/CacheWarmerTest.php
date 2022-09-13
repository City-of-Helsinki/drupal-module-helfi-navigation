<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\CacheWarmer;
use Prophecy\Argument;

/**
 * Tests cache warmer.
 *
 * @coversDefaultClass \Drupal\helfi_navigation\CacheWarmer
 * @group helfi_navigation
 */
class CacheWarmerTest extends KernelTestBase {

  /**
   * The shared temp store factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory|null
   */
  protected ?SharedTempStoreFactory $sharedTempStore;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->populateConfiguration('Test');
    $config = $this->config('helfi_api_base.environment_resolver.settings');
    $config->set(EnvironmentResolver::ENVIRONMENT_NAME_KEY, 'local');
    $config->set(EnvironmentResolver::PROJECT_NAME_KEY, Project::ASUMINEN);
    $config->save();
    $this->sharedTempStore = $this->container->get('tempstore.shared');
  }

  /**
   * Constructs a new cache warmer instance.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $invalidator
   *   The cache invalidator service.
   *
   * @return \Drupal\helfi_navigation\CacheWarmer
   *   The cache warmer service.
   */
  private function getCacheWarmer(CacheTagsInvalidatorInterface $invalidator) : CacheWarmer {
    return new CacheWarmer(
      $this->sharedTempStore,
      $this->container->get('language_manager'),
      $invalidator,
      $this->container->get('helfi_navigation.api_manager'),
    );
  }

  /**
   * Tests cache warmer.
   */
  public function testCacheWarmer() : void {
    $invalidator = $this->prophesize(CacheTagsInvalidatorInterface::class);
    $invalidator->invalidateTags(Argument::cetera())
      ->shouldBeCalled();

    // Warm caches and make sure all tags are invalidated when nothing is
    // populated yet.
    $this->getCacheWarmer($invalidator->reveal())->warm();

    // Warming caches again should not invalidate anything since hash
    // hasn't changed.
    $invalidator = $this->prophesize(CacheTagsInvalidatorInterface::class);
    $invalidator->invalidateTags(Argument::cetera())
      ->shouldNotBeCalled();
    $this->getCacheWarmer($invalidator->reveal())->warm();

    // Reset hash for finnish main menu and make sure main menu block is
    // invalidated (once).
    $this->sharedTempStore->get(CacheWarmer::STORAGE_KEY)->set('fi:main', '');
    $invalidator = $this->prophesize(CacheTagsInvalidatorInterface::class);
    $invalidator->invalidateTags(['config:system.menu.main'])
      ->shouldBeCalledTimes(1);
    $this->getCacheWarmer($invalidator->reveal())->warm();
  }

}
