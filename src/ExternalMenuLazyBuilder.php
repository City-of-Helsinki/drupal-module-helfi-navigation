<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\helfi_api_base\ApiClient\ApiResponse;

/**
 * A lazy builder for external menus.
 */
final readonly class ExternalMenuLazyBuilder implements ExternalMenuLazyBuilderInterface, TrustedCallbackInterface {

  public function __construct(
    private ApiManager $apiManager,
    private ExternalMenuTreeBuilderInterface $treeBuilder,
  ) {
  }

  /**
   * Parses the menu tree from given response.
   *
   * @param \Drupal\helfi_api_base\ApiClient\ApiResponse $response
   *   The API response.
   *
   * @return array
   *   The parsed response.
   */
  private function parseResponse(ApiResponse $response) : array {
    $type = key((array) $response->data);

    // We fetch data from two different endpoints; main and global menus.
    // Main menu endpoint returns links in a nested multidimensional array,
    // while global menu is just a flat array.
    // @see \Drupal\helfi_navigation\ApiManager::get()
    if (is_numeric($type)) {
      return (array) $response->data;
    }
    $tree = [];

    foreach ($response->data as $item) {
      if (!isset($item->menu_tree)) {
        continue;
      }
      $tree[] = reset($item->menu_tree);
    }
    return $tree;
  }

  /**
   * A lazy-builder callback for external menus.
   *
   * @param string $menuId
   *   The menu id to build.
   * @param string $langcode
   *   The language code.
   * @param string $requestOptions
   *   The request options.
   * @param int $maxDepth
   *   The maximum depth of menu levels.
   * @param int $startingLevel
   *   The starting level.
   * @param bool $expandAllItems
   *   Should all items be expanded.
   * @param string $themeSuggestion
   *   The theme suggestion.
   *
   * @return array
   *   The render array.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *
   * @see \Drupal\helfi_navigation\Plugin\Block\ExternalMenuBlockBase::build()
   */
  public function build(
    string $menuId,
    string $langcode,
    string $requestOptions,
    int $maxDepth,
    int $startingLevel,
    bool $expandAllItems,
    string $themeSuggestion,
  ): array {
    $build = [];
    $menuTree = NULL;

    $options = [
      'menu_type' => $menuId,
      'max_depth' => $maxDepth,
      'level' => $startingLevel,
      'expand_all_items' => $expandAllItems,
      'theme_suggestion' => $themeSuggestion,
    ];

    try {
      $response = $this->apiManager->get(
        $langcode,
        $menuId,
        $requestOptions ? ['query' => $requestOptions] : [],
      );
      $menuTree = $this->treeBuilder
        ->build($this->parseResponse($response), $options);

      $build += [
        '#sorted' => TRUE,
        '#items' => $menuTree,
        '#theme' => 'menu__external_menu',
        '#menu_type' => $menuId,
        '#attributes' => $options,
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

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() : array {
    return ['build'];
  }

}
