<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation;

/**
 * Provides an interface for the ExternalMenuLazyBuilder service.
 */
interface ExternalMenuLazyBuilderInterface {

  /**
   * Builds a render array for an external menu.
   *
   * @param string $menuId
   *   The menu ID to build.
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
   */
  public function build(
    string $menuId,
    string $langcode,
    string $requestOptions,
    int $maxDepth,
    int $startingLevel,
    bool $expandAllItems,
    string $themeSuggestion,
  ): array;

}
