<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkBase;

/**
 * Provides an implementation of menu link.
 */
final class ExternalMenuLink extends MenuLinkBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() : ? string {
    // Return NULL explicitly, so we don't unnecessarily process global
    // navigation links in 'translatable_menu_link_uri_iterate_menu()'.
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() : string {
    return (string) $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return (string) $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(array $new_definition_values, $persist) {
    $this->pluginDefinition = $new_definition_values + $this->getPluginDefinition();

    return $this->pluginDefinition;
  }

}
