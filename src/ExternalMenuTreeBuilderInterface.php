<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation;

/**
 * Interface for external menu tree building.
 */
interface ExternalMenuTreeBuilderInterface {

  /**
   * Form and return a menu tree instance for given menu items.
   *
   * @param array $items
   *   The menu items.
   * @param array $options
   *   Options for the menu link item handling.
   *
   * @return array|null
   *   The resulting menu tree instance.
   */
  public function build(array $items, array $options = []) :? array;

}
