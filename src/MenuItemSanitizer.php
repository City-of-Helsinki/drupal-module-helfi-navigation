<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation;

use Drupal\Component\Utility\UrlHelper;

/**
 * Sanitizes the menu items received from the global navigation API.
 */
final class MenuItemSanitizer {

  /**
   * The menu item attributes that are allowed to be rendered.
   */
  public const array ALLOWED_ATTRIBUTES = ['data-external', 'data-protocol', 'lang'];

  /**
   * Checks whether the URL is safe to be rendered as a link.
   *
   * @param string $url
   *   The URL.
   *
   * @return bool
   *   TRUE if the URL is relative or uses an allowed protocol.
   */
  public static function isSafeUrl(string $url) : bool {
    return UrlHelper::stripDangerousProtocols($url) === $url;
  }

  /**
   * Removes the attributes that are not allowed to be rendered.
   *
   * @param array<string, mixed> $attributes
   *   The attributes.
   *
   * @return array<string, mixed>
   *   The allowed attributes.
   */
  public static function filterAttributes(array $attributes) : array {
    return array_intersect_key($attributes, array_flip(self::ALLOWED_ATTRIBUTES));
  }

  /**
   * Removes the unsafe URLs from the given menu items and their children.
   *
   * @param array<mixed> $items
   *   The normalized menu items.
   *
   * @return array<mixed>
   *   The menu items without the unsafe URLs.
   */
  public static function sanitizeTree(array $items) : array {
    foreach ($items as &$item) {
      if (!is_array($item)) {
        continue;
      }
      if (isset($item['url']) && (!is_string($item['url']) || !self::isSafeUrl($item['url']))) {
        $item['url'] = '';
      }
      if (isset($item['attributes']) && is_array($item['attributes'])) {
        $item['attributes'] = self::filterAttributes($item['attributes']);
      }
      if (isset($item['sub_tree']) && is_array($item['sub_tree'])) {
        $item['sub_tree'] = self::sanitizeTree($item['sub_tree']);
      }
    }
    return $items;
  }

}
