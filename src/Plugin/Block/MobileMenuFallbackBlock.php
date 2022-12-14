<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\helfi_navigation\ApiManager;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a fallback mobile navigation menu block.
 *
 * This is used to render non-javascript version of mobile
 * navigation.
 *
 * @Block(
 *   id = "external_menu_block_fallback",
 *   admin_label = @Translation("External - Fallback mobile menu"),
 *   category = @Translation("External menu"),
 * )
 */
final class MobileMenuFallbackBlock extends MenuBlockBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  private Request $currentRequest;

  /**
   * The path matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  private PathMatcherInterface $pathMatcher;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The global navigation service.
   *
   * @var \Drupal\helfi_navigation\ApiManager
   */
  protected ApiManager $apiManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->configFactory = $container->get('config.factory');
    $instance->currentRequest = $container->get('request_stack')->getCurrentRequest();
    $instance->pathMatcher = $container->get('path.matcher');
    $instance->languageManager = $container->get('language_manager');
    $instance->apiManager = $container->get('helfi_navigation.api_manager');
    return $instance;
  }

  /**
   * Build the fallback menu render array.
   *
   * @return array
   *   Returns the render array.
   */
  public function build() : array {

    ['max_depth' => $depth, 'expand_all_items' => $expand] = $this->getOptions();
    // Adjust the menu tree parameters based on the block's configuration.
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters('main');

    if ($expand) {
      $parameters->expandedParents = [];
    }

    // Update the level to match the active menu item's level in the menu.
    $level = count($parameters->activeTrail);
    $active_trail = $parameters->activeTrail;

    // Get active menu link, aka. current menu link.
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $active_menu_link */
    $active_menu_link = $this->getActiveMenuLink();
    $last_link_of_tree = $active_menu_link && $this->menuLinkHasChildren(
        $active_menu_link
      );

    // Set min and max depth variables for the menu tree parameters.
    $parameters->setMinDepth($level);
    $parameters->setMaxDepth($depth);

    // If active menu link is last of its kind, treat the menu tree as the
    // link would really appear on second to last level.
    $offset = ($last_link_of_tree) ? 2 : 1;

    // Active trail array is child-first. Reverse it, and pull the new menu
    // root based on the parent of the configured start level.
    $menu_trail_ids = array_reverse(array_values($parameters->activeTrail));
    $menu_root = $menu_trail_ids[$level - $offset];
    $parameters->setRoot($menu_root)->setMinDepth(1);

    // Load the menu tree with.
    $tree = $this->menuTree->load('main', $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];

    // Get the grandparent link for the fallback menu.
    $grand_parents = array_slice($active_trail, 2);
    $grand_parent_link_id = reset($grand_parents);
    $grand_parent_link = !empty($grand_parent_link_id)
      ? $this->loadMenuLinkByDerivativeId($grand_parent_link_id)
      : $this->getSiteFrontPageMenuLink();

    if ($grand_parent_link instanceof MenuLinkContentInterface) {
      $grand_parent_link = $this->createRenderArrayFromMenuLinkContent($grand_parent_link);
    }

    // Get the parent link for the fallback menu.
    $parents = array_slice($active_trail, 1);
    $parent_link_id = reset($parents);
    $parent_link = !empty($parent_link_id)
      ? $this->loadMenuLinkByDerivativeId($parent_link_id)
      : $this->getSiteFrontPageMenuLink();
    if ($parent_link instanceof MenuLinkContentInterface) {
      $parent_link = $this->createRenderArrayFromMenuLinkContent($parent_link);
    }

    // If the current menu link available, create back and current/parent links
    // for the fallback menu.
    if ($active_menu_link) {
      if ($last_link_of_tree) {
        $menu_link_back = $grand_parent_link;
        $menu_link_current_or_parent = $parent_link;
      }
      else {
        $menu_link_back = $parent_link;
        $menu_link_current_or_parent = $this->createRenderArrayFromMenuLinkContent($this->getActiveMenuLink());
      }
    }
    else {
      // If the current menu link is not available, we're most likely browsing
      // the front page or first level of the menu tree.
      // Create back and current/parent links accordingly.
      $url = $this->apiManager->getUrl('canonical',
        $this->languageManager->getCurrentLanguage()->getId()
      );

      $menu_link_back = [
        'title' => $this->t('Front page'),
        'url' => Url::fromUri($url),
      ];
      $menu_link_current_or_parent = $grand_parent_link;
    }

    // Add needed menu fragment to menu back link.
    if (isset($menu_link_back['url']) && $menu_link_back['url'] instanceof Url) {
      $menu_link_back['url']->setOption('fragment', 'menu');
    }

    // Build a render array and enable proper caching.
    $tree = $this->menuTree->transform($tree, $manipulators);
    $build = $this->menuTree->build($tree);

    $build['#theme'] = 'menu__external_menu__fallback';
    $build['#menu_link_back'] = $menu_link_back;
    $build['#menu_link_current_or_parent'] = $menu_link_current_or_parent;
    $build['#cache'] = [
      'contexts' => [
        'url',
        'route.menu_active_trails:main',
      ],
      'tags' => [
        'config:system.menu.main',
      ],
    ];
    if ($this->pathMatcher->isFrontPage()) {
      $build['#cache']['contexts'][] = 'url.path.is_front';
    }

    return $build;
  }

  /**
   * Get menu block options.
   *
   * @return array
   *   Returns the options as an array.
   */
  protected function getOptions(): array {
    return [
      'menu_type' => 'main',
      'max_depth' => $this->getMaxDepth(),
      'level' => $this->getStartingLevel(),
      'expand_all_items' => $this->getExpandAllItems(),
    ];
  }

  /**
   * Gets the active menu item.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|bool
   *   Returns the currently active menu item or FALSE.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function getActiveMenuLink(): MenuLinkContentInterface|bool {
    $active_menu_link = FALSE;
    $active_trail_id = $this->getDerivativeActiveTrailIds();
    $active_trail_id = reset($active_trail_id);
    if ($active_trail_id) {
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $active_menu_link */
      $active_menu_link = $this->loadMenuLinkByDerivativeId($active_trail_id);
    }
    return $active_menu_link;
  }

  /**
   * Checks if given menu link content entity has children.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $menu_link_content
   *   Menu link content entity.
   *
   * @return bool
   *   Returns true or false depending of the children.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function menuLinkHasChildren(MenuLinkContentInterface $menu_link_content): bool {
    $children = $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->loadByProperties([
        'menu_name' => 'main',
        'enabled' => 1,
        'parent' => $menu_link_content->getPluginId(),
      ]);
    return count($children) == 0;
  }

  /**
   * Load menu link content entity with menu link content derivative id.
   *
   * @param int|string $id
   *   Menu link derivative id.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   Returns menu link content entity or NULL.
   */
  protected function loadMenuLinkByDerivativeId(int|string $id): ?EntityInterface {
    $menu_link = $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->loadByProperties([
        'uuid' => str_replace("menu_link_content:", '', $id),
      ]);

    return $menu_link ? reset($menu_link) : NULL;
  }

  /**
   * Gets an array of the active trail menu link items.
   *
   * @return array
   *   The active trail menu item IDs.
   */
  protected function getDerivativeActiveTrailIds(): array {
    return array_filter($this->menuActiveTrail->getActiveTrailIds('main'));
  }

  /**
   * Get current instance front page menu link.
   *
   * @return array
   *   Returns current instance front page menu link array.
   */
  protected function getSiteFrontPageMenuLink(): array {
    return [
      'is_currentPage' => $this->pathMatcher->isFrontPage(),
      'attributes' => new Attribute(),
      'title' => $this->configFactory->get('system.site')->get('name') ?? $this->t('Front page'),
      'url' => Url::fromRoute('<front>'),
    ];
  }

  /**
   * Create simple render array from menu link content.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $link
   *   Menu link content entity.
   *
   * @return array
   *   Returns an array of menu link content title and URL object.
   */
  protected function createRenderArrayFromMenuLinkContent(MenuLinkContentInterface $link): array {
    $path = parse_url($this->currentRequest->getUri(), PHP_URL_PATH);
    $linkPath = parse_url($link->getUrlObject()->toString(), PHP_URL_PATH);

    return [
      'is_currentPage' => $path === $linkPath,
      'title' => $link->getTitle(),
      'url' => $link->getUrlObject(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() : array {
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'config:system.menu.main';
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() : array {
    return ['route.menu_active_trails:main'];
  }

}
