<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\Block;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\helfi_api_base\Language\DefaultLanguageResolver;
use Drupal\helfi_navigation\ApiManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * The path matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  private PathMatcherInterface $pathMatcher;

  /**
   * The global navigation service.
   *
   * @var \Drupal\helfi_navigation\ApiManager
   */
  private ApiManager $apiManager;

  /**
   * Default language resolver.
   *
   * @var \Drupal\helfi_api_base\Language\DefaultLanguageResolver
   */
  private DefaultLanguageResolver $defaultLanguageResolver;

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  private MenuLinkManagerInterface $menuLinkManager;

  /**
   * A flag indicating whether to filter out untranslated links or not.
   *
   * This requires 'menu_block_current_language' module.
   *
   * @var bool
   */
  private bool $filterByLanguage = FALSE;

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
    $instance->configFactory = $container->get('config.factory');
    $instance->pathMatcher = $container->get('path.matcher');
    $instance->apiManager = $container->get('helfi_navigation.api_manager');
    $instance->defaultLanguageResolver = $container->get('helfi_api_base.default_language_resolver');
    $instance->filterByLanguage = $container->has('menu_block_current_language_tree_manipulator');
    $instance->menuLinkManager = $container->get('plugin.manager.menu.link');
    return $instance;
  }

  /**
   * Builds the parent links.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface|null $activeLink
   *   The currently active menu link.
   * @param array $parents
   *   The parent IDs.
   *
   * @return array
   *   The parent links.
   */
  private function buildParentLinks(?MenuLinkInterface $activeLink, array $parents) : array {
    $langcode = $this->defaultLanguageResolver->getCurrentOrFallbackLanguage();
    $url = $this->apiManager->getUrl(
      'canonical',
      $langcode,
    );

    $parentLinks = [
      [
        'title' => (string) $this->t('Front page'),
        'url' => Url::fromUri($url),
      ],
      [
        'is_currentPage' => $this->pathMatcher->isFrontPage(),
        'attributes' => new Attribute(),
        'title' => $this->configFactory->get('system.site')->get('name') ?? $this->t('Front page'),
        'url' => Url::fromRoute('<front>'),
      ],
    ];

    foreach (array_reverse($parents) as $id) {
      if (!$this->menuLinkManager->hasDefinition($id)) {
        continue;
      }
      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $this->menuLinkManager->createInstance($id);

      $parentLinks[] = [
        'title' => $link->getTitle(),
        'url' => $link->getUrlObject(),
        'is_currentPage' => $activeLink?->getPluginId() === $id,
      ];
    }

    // We only show the last two parents.
    return array_slice($parentLinks, -2);
  }

  /**
   * Build the fallback menu render array.
   *
   * @return array
   *   Returns the render array.
   */
  public function build() : array {
    $parameters = $this->menuTree->getCurrentRouteMenuTreeParameters('main');
    $parameters->expandedParents = [];

    $parents = [];
    $root = '';

    if ($activeMenuLink = $this->menuActiveTrail->getActiveLink('main')) {
      $parents = $this->menuLinkManager->getParentIds($activeMenuLink->getPluginId());

      // Default root to the closest parent whenever possible.
      $root = array_key_first($parents);

      // If the currently active link has no children, we must start
      // one parent further down, so we render the whole child tree
      // instead of just one link.
      if (!$this->menuLinkManager->getChildIds($activeMenuLink->getPluginId())) {
        $root = next($parents);
        reset($parents);

        // Remove the currently active link from parents when it has no
        // children.
        if (isset($parents[$activeMenuLink->getPluginId()])) {
          unset($parents[$activeMenuLink->getPluginId()]);
        }
      }
    }
    $parameters->setMaxDepth(2);
    $parameters->setRoot($root)->setMinDepth(1);

    $tree = $this->menuTree->load('main', $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    // Filter untranslated menu links if 'menu_block_current_language' module is
    // enabled.
    if ($this->filterByLanguage) {
      $manipulators[] = [
        'callable' => 'menu_block_current_language_tree_manipulator::filterLanguages',
        'args' => [['menu_link_content']],
      ];
    }
    $tree = $this->menuTree->transform($tree, $manipulators);
    $build = $this->menuTree->build($tree);

    [$menu_link_back, $menu_link_current_or_parent] = $this->buildParentLinks($activeMenuLink, $parents);

    $build['#theme'] = 'menu__external_menu__fallback';
    $build['#menu_link_back'] = $menu_link_back;
    $build['#menu_link_current_or_parent'] = $menu_link_current_or_parent;

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeId() : string {
    return 'main';
  }

}
