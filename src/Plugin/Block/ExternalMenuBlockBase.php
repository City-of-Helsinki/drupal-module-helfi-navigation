<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\ExternalMenuBlockInterface;
use Drupal\helfi_navigation\ExternalMenuLazyBuilder;
use Drupal\helfi_navigation\ExternalMenuTreeBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for creating external menu blocks.
 */
abstract class ExternalMenuBlockBase extends MenuBlockBase implements ExternalMenuBlockInterface {

  /**
   * The menu tree factory.
   *
   * @var \Drupal\helfi_navigation\ExternalMenuTreeBuilder
   */
  protected ExternalMenuTreeBuilder $menuTreeBuilder;

  /**
   * The global navigation service.
   *
   * @var \Drupal\helfi_navigation\ApiManager
   */
  protected ApiManager $apiManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Default language resolver.
   *
   * @var \Drupal\helfi_api_base\Language\DefaultLanguageResolver
   */
  protected DefaultLanguageResolver $defaultLanguageResolver;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->apiManager = $container->get(ApiManager::class);
    $instance->menuTreeBuilder = $container->get(ExternalMenuTreeBuilder::class);
    $instance->languageManager = $container->get('language_manager');
    $instance->defaultLanguageResolver = $container->get('helfi_api_base.default_language_resolver');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() : array {
    return Cache::mergeTags([
      sprintf('external_menu_block:%s', $this->getDerivativeId()),
    ], parent::getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() : array {
    return [
      'url.path',
      'languages:language_content',
    ];
  }

  /**
   * Gets the request options.
   *
   * @return array
   *   The request options.
   */
  protected function getRequestOptions() : array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function build() : array {
    $menuId = $this->getDerivativeId();
    // Languages without standard support should use fallback language in menu.
    $langcode = $this->defaultLanguageResolver->getCurrentOrFallbackLanguage();

    return [
      '#cache' => [
        'contexts' => $this->getCacheContexts(),
        'tags' => $this->getCacheTags(),
      ],
      '#lazy_builder' => [
        ExternalMenuLazyBuilder::class . ':build',
        [
          $menuId,
          $langcode,
          $this->getRequestOptions(),
          $this->getOptions(),
        ],
      ],
      '#sorted' => TRUE,
      '#items' => [],
      '#theme' => 'menu__external_menu',
      '#menu_type' => $menuId,
      '#create_placeholder' => TRUE,
      '#lazy_builder_preview' => ['#markup' => ''],
    ];
  }

}
