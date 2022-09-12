<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Link\UrlHelper;
use Drupal\helfi_navigation\Plugin\Menu\ExternalMenuLink;
use Drupal\helfi_api_base\Link\InternalDomainResolver;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper class for external menu tree actions.
 */
final class ExternalMenuTreeBuilder {

  /**
   * The menu links in active trail.
   *
   * @var array
   */
  private array $activeTrail = [];

  /**
   * Constructs a tree instance from supplied JSON.
   *
   * @param \Drupal\helfi_api_base\Link\InternalDomainResolver $domainResolver
   *   Internal domain resolver.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    private InternalDomainResolver $domainResolver,
    private RequestStack $requestStack,
  ) {
  }

  /**
   * Form and return a menu tree instance for given menu items.
   *
   * @param array $items
   *   The menu.
   * @param array $options
   *   Options for the menu link item handling.
   *
   * @return array|null
   *   The resulting menu tree instance.
   */
  public function build(array $items, array $options = []) :? array {
    $tree = $this->transform($items, $options);

    return $tree ?? NULL;
  }

  /**
   * Create menu link items from JSON elements.
   *
   * @param array $items
   *   Provided menu items.
   * @param array $options
   *   Keyed array of options needed to create menu link items.
   *
   * @return array
   *   Resulting array of menu links.
   */
  private function transform(array $items, array $options): array {
    $links = [];

    [
      'expand_all_items' => $expand_all_items,
      'level' => $level,
      'max_depth' => $max_depth,
      'menu_type' => $menu_type,
    ] = $options;

    foreach ($items as $item) {
      $link = $this->createLink($item, $menu_type, (bool) $expand_all_items);

      $options['level'] = $level + 1;

      if (isset($item->sub_tree)) {
        // Handle subtree.
        if ($level < $max_depth) {
          $link['below'] = $this->transform($item->sub_tree, $options);
        }
      }

      $links[$link['id']] = $link;
    }

    return $links;
  }

  /**
   * Create link from menu tree item.
   *
   * @param object $item
   *   Menu tree item.
   * @param string $menu
   *   Menu name.
   * @param bool $expand_all_items
   *   Should the menu link item be expanded.
   *
   * @return array
   *   A menu link.
   */
  private function createLink(
    object $item,
    string $menu,
    bool $expand_all_items
  ): array {
    $link_definition = [
      'menu_name' => $menu,
      'options' => [],
      'title' => $item->name,
      'provider' => 'helfi_navigation',
    ];

    // Parse the URL.
    $item->url = !empty($item->url) ? UrlHelper::parse($item->url) : new Url('<nolink>');

    if (!isset($item->parentId)) {
      $item->parentId = NULL;
    }

    if (!isset($item->external)) {
      $item->external = $this->domainResolver->isExternal($item->url);
    }

    if (isset($item->description)) {
      $link_definition['description'] = $item->description;
    }

    if (isset($item->weight)) {
      $link_definition['weight'] = $item->weight;
    }

    return [
      'attributes' => new Attribute($item->attributes ?? []),
      'title' => $item->name,
      'id' => $item->id,
      'parent_id' => $item->parentId,
      'is_expanded' => $expand_all_items || !empty($item->expanded),
      // @todo mark parents in active trail as well.
      'in_active_trail' => $this->inActiveTrail($item),
      'original_link' => new ExternalMenuLink([], $item->id, $link_definition),
      'external' => $item->external,
      'url' => $item->url,
      'below' => [],
    ];
  }

  /**
   * Check if current menu link item is in active trail.
   *
   * @param object $item
   *   Menu link item.
   *
   * @return bool
   *   Returns true or false.
   */
  private function inActiveTrail(object $item): bool {
    if ($item->url->isRouted() && $item->url->getRouteName() === '<nolink>') {
      return FALSE;
    }
    if (!$request = $this->requestStack->getCurrentRequest()) {
      throw new \LogicException('Request is not set.');
    }
    $currentPath = parse_url($request->getUri(), PHP_URL_PATH);
    $linkPath = parse_url($item->url->getUri(), PHP_URL_PATH);

    // We don't care about the domain when comparing URLs because the
    // site might be served from multiple different domains.
    if ($linkPath === $currentPath) {
      return TRUE;
    }
    return FALSE;
  }

}
