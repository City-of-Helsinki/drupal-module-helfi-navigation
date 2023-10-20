<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Link\InternalDomainResolver;
use Drupal\helfi_api_base\Link\UrlHelper;
use Drupal\helfi_navigation\Plugin\Menu\ExternalMenuLink;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper class for external menu tree actions.
 */
final class ExternalMenuTreeBuilder {

  /**
   * All menu link IDs in active trail.
   *
   * @var string[]
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
    private readonly InternalDomainResolver $domainResolver,
    private readonly RequestStack $requestStack,
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
    $this->updateActiveTrail($tree);

    return $tree ?? NULL;
  }

  /**
   * Updates active trail from last item upwards.
   *
   * @param array $tree
   *   The menu tree to update.
   */
  private function updateActiveTrail(array &$tree) : void {
    foreach ($tree as &$item) {
      if (isset($item['below'])) {
        $this->updateActiveTrail($item['below']);
      }
      if (isset($this->activeTrail[$item['id']])) {
        $item['in_active_trail'] = TRUE;
      }
    }
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
        // We need to render full tree structure to collect the entire
        // active trail chain.
        $subTree = $this->transform($item->sub_tree, $options);

        // Only show subtree up to max depth.
        if ($level < $max_depth) {
          $link['below'] = $subTree;
        }
      }
      $links[] = $link;
    }

    return $links;
  }

  /**
   * Create link from menu tree item.
   *
   * @param \stdClass $item
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
    \stdClass $item,
    string $menu,
    bool $expand_all_items
  ): array {
    $link_definition = [
      'menu_name' => $menu,
      'options' => [],
      'title' => $item->name ?? '',
      'provider' => 'helfi_navigation',
    ];

    $item->url = isset($item->url) ? UrlHelper::parse($item->url) : new Url('<nolink>');

    $item->external = $this->domainResolver->isExternal($item->url);

    if (isset($item->weight)) {
      $link_definition['weight'] = $item->weight;
    }

    if ($inActiveTrail = $this->inActiveTrail($item)) {
      // Add whole tree in active trail.
      array_map(function ($parent) {
        $this->activeTrail[$parent] = $parent;
      }, $item->parents ?? []);
    }

    return [
      'attributes' => new Attribute($item->attributes ?? []),
      'title' => $item->name,
      'id' => $item->id,
      'parent_id' => $item->parentId ?? NULL,
      'is_expanded' => $expand_all_items || !empty($item->expanded),
      'in_active_trail' => $inActiveTrail,
      'is_currentPage' => $inActiveTrail,
      'original_link' => new ExternalMenuLink([], $item->id, $link_definition),
      'external' => $item->external,
      'url' => $item->url,
      'below' => [],
    ];
  }

  /**
   * Check if current menu link item is in active trail.
   *
   * @param \stdClass $item
   *   Menu link item.
   *
   * @return bool
   *   Returns true or false.
   */
  private function inActiveTrail(\stdClass $item): bool {
    if (
      $item->url->isRouted() &&
      $item->url->getRouteName() === '<nolink>' ||
      $item->external
    ) {
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
