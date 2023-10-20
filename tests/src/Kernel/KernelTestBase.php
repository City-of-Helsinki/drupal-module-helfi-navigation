<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\helfi_api_base\Kernel\ApiKernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\LanguageManagerTrait;

/**
 * A base test class for all Kernel tests.
 */
abstract class KernelTestBase extends ApiKernelTestBase {

  use ApiTestTrait;
  use LanguageManagerTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'link',
    'user',
    'content_translation',
    'menu_link_content',
    'language',
    'path_alias',
    'path',
    'helfi_language_negotiator_test',
    'helfi_navigation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('path_alias');
    $this->installConfig([
      'system',
      'user',
      'path',
      'content_translation',
      'language',
    ]);
    $this->enableTranslation(['menu_link_content']);
    $this->setupLanguages();

    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => 'en', 'fi' => 'fi', 'sv' => 'sv'])
      ->save();

    $this->container->get('kernel')->rebuildContainer();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) : void {
    $container->setParameter('helfi_navigation.request_timeout', 1);

    parent::register($container);

    // Core's KernelTestBase removes service_collector tags from
    // path_alias.path_processor service. We need to add them back
    // to test them.
    // @see \Drupal\KernelTests\KernelTestBase::register().
    $container->getDefinition('path_alias.path_processor')
      ->addTag('path_processor_inbound')
      ->addTag('path_processor_outbound');
  }

  /**
   * Populates the required configuration.
   *
   * @param string $siteName
   *   The site name.
   * @param string $apiKey
   *   The api key.
   */
  protected function populateConfiguration(string $siteName = NULL, string $apiKey = '123') : void {
    $this->config('system.site')->set('name', $siteName)->save();
    $this->config('helfi_navigation.api')->set('key', $apiKey)->save();
  }

}
