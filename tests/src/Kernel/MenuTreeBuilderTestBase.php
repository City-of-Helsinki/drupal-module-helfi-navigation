<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\Core\Url;
use Drupal\helfi_navigation\Menu\MenuTreeBuilder;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * A base class for menu tree builder tests.
 */
abstract class MenuTreeBuilderTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');

    NodeType::create(['type' => 'page']);
    // We have a dependency to anonymous user when checking menu permissions
    // and might run into 'entity:user' context is required error when trying
    // to generate an entity link.
    User::create([
      'name' => '',
      'uid' => 0,
    ])->save();

    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();
  }

  /**
   * Gets the menu tree builder.
   *
   * @return \Drupal\helfi_navigation\Menu\MenuTreeBuilder
   *   The menu tree builder service.
   */
  protected function getMenuTreeBuilder() : MenuTreeBuilder {
    return $this->container->get('helfi_navigation.menu_tree_builder');
  }

  /**
   * Creates a new test node.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function createNode() : NodeInterface {
    $node = Node::create([
      'title' => 'Test',
      'type' => 'page',
      'path' => ['alias' => '/test-node-page', 'langcode' => 'en'],
    ]);
    $node->save();

    return $node;
  }

  /**
   * Gets the menu tree in given language.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   The menu tree.
   */
  protected function getMenuTree(string $langcode) : array {
    $this->setOverrideLanguageCode($langcode);

    return $this->getMenuTreeBuilder()->build('main', $langcode, (object) [
      'name' => 'Test',
      'url' => new Url('<front>', options: [
        'language' => $this->languageManager()->getLanguage($langcode),
      ]),
      'id' => 'liikenne',
    ]);
  }

  /**
   * Create a test menu link.
   *
   * @param array $overrides
   *   The overrides.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface
   *   The menu link.
   */
  protected function createTestLink(
    array $overrides = []
  ) : MenuLinkContentInterface {
    $defaults = [
      'menu_name' => 'main',
    ];
    $link = MenuLinkContent::create($overrides + $defaults);
    $link->save();

    return $link;
  }

  /**
   * Creates test links.
   *
   * @return array
   *   An array of links.
   */
  protected function createLinks() : array {
    $this->createNode();

    $links = [
      [
        'uuid' => '18b403a4-dfda-4301-b0da-1c81777aed2a',
        'title' => 'Link 1',
        'langcode' => 'en',
        'link' => ['uri' => 'internal:/admin/content'],
        'expanded' => TRUE,
      ],
      [
        'uuid' => 'de6409aa-c620-4327-90f4-127176f209b2',
        'parent' => 'menu_link_content:18b403a4-dfda-4301-b0da-1c81777aed2a',
        'title' => 'Link 1 depth 1',
        'langcode' => 'en',
        'link' => ['uri' => 'internal:/test'],
      ],
      [
        'uuid' => '79736feb-77b7-4981-821b-90f02814e5b5',
        'title' => 'Link 2 - Unpublished',
        'langcode' => 'en',
        'enabled' => FALSE,
        'link' => ['uri' => 'internal:/test'],
      ],
      [
        'uuid' => '520df961-36dc-4fd4-8cff-3d3acaa9f0a8',
        'langcode' => 'fi',
        'title' => 'Link 3',
        'link' => ['uri' => 'entity:node/1'],
      ],
      [
        'uuid' => '3fd92b84-6b9c-4970-934e-6b4468b618c0',
        'langcode' => 'en',
        'title' => 'Link 4',
        'link' => ['uri' => 'entity:node/1'],
      ],
      [
        'uuid' => 'b108dc3c-3a22-455f-90a7-238331bc2bfe',
        'langcode' => 'en',
        'title' => 'Link 4 - Child 1',
        'parent' => 'menu_link_content:3fd92b84-6b9c-4970-934e-6b4468b618c0',
        'link' => ['uri' => 'internal:/test'],
      ],
      [
        'uuid' => '64a5a6d1-ffce-481b-b321-260d9cf66ad9',
        'langcode' => 'en',
        'title' => 'Link 5 internal',
        'link' => ['https://www.hel.fi/helsinki/test'],
        'lang_attribute' => 'en-US',
      ],
      [
        'uuid' => '0d8a1366-4fcd-4dbc-bb75-854dedf28a1b',
        'langcode' => 'en',
        'title' => 'Link 5 internal - Child 1',
        'parent' => 'menu_link_content:64a5a6d1-ffce-481b-b321-260d9cf66ad9',
        'link' => ['https://www.hel.fi/helsinki/2'],
      ],
      [
        'uuid' => '0b10ba16-e2d5-4251-ac37-8ed27a02ff1f',
        'langcode' => 'en',
        'title' => 'Link 5 internal - Child 1 - Child 1',
        'parent' => 'menu_link_content:0d8a1366-4fcd-4dbc-bb75-854dedf28a1b',
        'link' => ['tel:+358040123'],
      ],
      [
        'uuid' => '263464a3-bd76-4a4e-a77f-03ab4960ff1e',
        'langcode' => 'en',
        'title' => 'Link 6',
        'link' => ['uri' => 'route:<nolink>'],
        'lang_attribute' => 'en-GB',
      ],
    ];

    $linkEntities = [];
    foreach ($links as $link) {
      $linkEntities[$link['uuid']] = $this->createTestLink($link);
    }
    return $linkEntities;
  }

}
