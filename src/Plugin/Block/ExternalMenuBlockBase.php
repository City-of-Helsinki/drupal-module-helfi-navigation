<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_navigation\ExternalMenuBlockInterface;
use Drupal\helfi_navigation\ExternalMenuTreeBuilder;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\ApiResponse;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
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
    $instance->apiManager = $container->get('helfi_navigation.api_manager');
    $instance->menuTreeBuilder = $container->get('helfi_navigation.external_menu_tree_builder');
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
   * Gets the external menu tree.
   *
   * @param \Drupal\helfi_navigation\ApiResponse $response
   *   The API response.
   *
   * @return mixed
   *   The external menu tree.
   */
  abstract protected function getTreeFromResponse(ApiResponse $response) : mixed;

  /**
   * {@inheritdoc}
   */
  public function build() : array {
    $build = [
      '#cache' => [
        'contexts' => $this->getCacheContexts(),
        'tags' => $this->getCacheTags(),
      ],
    ];

    $menuTree = NULL;

    // Languages without standard support should use fallback language in menu.
    $langcode = $this->defaultLanguageResolver->getCurrentOrFallbackLanguage();

    try {
      $menuId = $this->getDerivativeId();
      $response = $this->apiManager->get(
        $langcode,
        $menuId,
      );
      $menuTree = $this->menuTreeBuilder
        ->build($this->getTreeFromResponse($response), $this->getOptions());

      $build += [
        '#sorted' => TRUE,
        '#items' => $menuTree,
        '#theme' => 'menu__external_menu',
        '#menu_type' => $menuId,
      ];
    }
    catch (\Exception) {
    }
    if (!is_array($menuTree)) {
      // Cache for 60 seconds if request fails.
      $build['#cache']['max-age'] = 60;
    }

    return $build;
  }

}
