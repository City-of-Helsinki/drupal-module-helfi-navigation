<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Menu;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\helfi_api_base\Link\InternalDomainResolver;
use Drupal\helfi_navigation\Event\MenuTreeBuilderLink;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Create menu tree from Drupal menu.
 */
final class MenuTreeBuilder {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\helfi_api_base\Link\InternalDomainResolver $domainResolver
   *   The internal domain resolver.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuTree
   *   The menu link tree builder service.
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menuLinkManager
   *   The menu link manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    private InternalDomainResolver $domainResolver,
    private MenuLinkTreeInterface $menuTree,
    private MenuLinkManagerInterface $menuLinkManager,
    private EventDispatcherInterface $eventDispatcher
  ) {
  }

  /**
   * Builds menu tree for synchronization.
   *
   * @param string $menuName
   *   Menu type.
   * @param string $langcode
   *   Language code.
   * @param object|null $rootElement
   *   The root element.
   * @param \Drupal\Core\Menu\MenuTreeParameters|null $parameters
   *   The menu tree parameters.
   *
   * @return array
   *   The resulting tree.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function build(
    string $menuName,
    string $langcode,
    object $rootElement = NULL,
    MenuTreeParameters $parameters = NULL
  ): array {
    if (!$parameters) {
      $parameters = new MenuTreeParameters();
    }
    $parameters->onlyEnabledLinks();

    $tree = $this->menuTree->load($menuName, $parameters);
    $tree = $this->menuTree->transform($tree, [
      // Sync menu links accessible to anonymous users and sort them
      // the same way core does.
      ['callable' => 'helfi_navigation.menu_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ]);

    if ($rootElement) {
      if (!isset($rootElement->name, $rootElement->url, $rootElement->id)) {
        throw new \LogicException(
          'Missing $rootElement->name, $rootElement->url or $rootElement->id property.'
        );
      }
      return $this->processItem(
        new MenuTreeBuilderLink($rootElement->url, $langcode, [
          'id' => $rootElement->id,
          'name' => $rootElement->name,
          'external' => FALSE,
          'attributes' => new \stdClass(),
          'weight' => 0,
          'sub_tree' => $this->transform($tree, $langcode, $rootElement->id),
        ])
      );
    }
    return $this->transform($tree, $langcode);
  }

  /**
   * Transform menu items to response format.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $menuItems
   *   Array of menu items.
   * @param string $langcode
   *   Language code as a string.
   * @param string|null $rootId
   *   The root ID or null.
   *
   * @return array
   *   Returns an array of transformed menu items.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function transform(array $menuItems, string $langcode, string $rootId = NULL): array {
    $items = [];

    foreach ($menuItems as $element) {
      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
      // @todo Do we want to show links other than MenuLinkContent?
      if (!$link = $this->getEntity($element->link)) {
        continue;
      }

      // Handle only menu links with translations.
      if (
        !$link->hasTranslation($langcode) ||
        !$link->isTranslatable()
      ) {
        continue;
      }

      /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
      $link = $link->getTranslation($langcode);

      // Only show accessible links (and published).
      if (
        !$link->get('content_translation_status')->value ||
        ($element->access instanceof AccessResultInterface && !$element->access->isAllowed())
      ) {
        continue;
      }

      $parentId = $link->getParentId();
      // The first level link (depth 0) always links to a currently active
      // instance, meaning second level (depth 1) links have no proper
      // parent. Use a pre-defined root id to keep the menu structure
      // consistent.
      if ($parentId === '') {
        $parentId = (string) $rootId;
      }

      $isExternal = $this->domainResolver->isExternal($link->getUrlObject());

      // Include all parent ids for given menu links.
      if ($parents = $this->menuLinkManager->getParentIds($link->getPluginId())) {
        $parents = array_keys($parents);

        // Add first level root item as parent as well.
        if (!isset($parents[$rootId]) && $rootId) {
          $parents[] = $rootId;
        }
      }

      $urlObject = $link->getUrlObject();

      // Make sure url object retains the language information.
      if (!$urlObject->getOption('language')) {
        $urlObject->setOptions(['language' => $link->language()]);
      }

      $item = [
        'id' => $link->getPluginId(),
        'name' => $link->getTitle(),
        'parentId' => $parentId,
        'attributes' => new \stdClass(),
        'external' => $isExternal,
        'hasItems' => FALSE,
        'expanded' => $link->isExpanded(),
        'parents' => $parents ?? [],
        'weight' => $link->getWeight(),
      ];

      if ($isExternal) {
        $item['attributes']->{"data-external"} = TRUE;
      }

      if ($protocol = $this->domainResolver->getProtocol($urlObject)) {
        $item['attributes']->{"data-protocol"} = $protocol;
      }

      if (
        $link->hasField('lang_attribute') &&
        $langAttribute = $link->get('lang_attribute')->value
      ) {
        $item['attributes']->{"lang"} = $langAttribute;
      }

      if ($element->hasChildren) {
        $item['sub_tree'] = $this->transform($element->subtree, $langcode, $rootId);
        $item['hasItems'] = count($item['sub_tree']) > 0;
      }
      $items[] = (object) $this->processItem(
        new MenuTreeBuilderLink($urlObject, $langcode, $item)
      );
    }

    return $items;
  }

  /**
   * Processes the given link.
   *
   * @param \Drupal\helfi_navigation\Event\MenuTreeBuilderLink $link
   *   The menu tree builder link.
   *
   * @return array
   *   The processed item.
   */
  private function processItem(MenuTreeBuilderLink $link) : array {
    // Allow item to be altered.
    /** @var \Drupal\helfi_navigation\Event\MenuTreeBuilderLink $menuTreeBuilderLink */
    $menuTreeBuilderLink = $this->eventDispatcher
      ->dispatch($link);

    return array_merge([
      'url' => $menuTreeBuilderLink->url->setAbsolute()->toString(TRUE)->getGeneratedUrl(),
    ], $menuTreeBuilderLink->item);
  }

  /**
   * Load entity with given menu link.
   *
   * @param \Drupal\Core\Menu\MenuLinkInterface $link
   *   The menu link.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|null
   *   NULL if entity not found and
   *   a MenuLinkContentInterface if found.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getEntity(MenuLinkInterface $link): ? MenuLinkContentInterface {
    // MenuLinkContent::getEntity() has protected visibility and cannot be used
    // to directly fetch the entity.
    $metadata = $link->getMetaData();

    if (empty($metadata['entity_id'])) {
      return NULL;
    }
    return $this->entityTypeManager
      ->getStorage('menu_link_content')
      ->load($metadata['entity_id']);
  }

}
