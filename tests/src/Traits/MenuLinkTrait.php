<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Traits;

use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;

/**
 * A trait to generate menu links.
 */
trait MenuLinkTrait {

  /**
   * An array of nodes.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected array $nodes = [];

  /**
   * Creates a new test node.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  protected function createNodeWithAlias() : NodeInterface {
    if (!NodeType::load('page')) {
      NodeType::create(['type' => 'page'])->save();
    }
    $node = Node::create([
      'title' => 'Test',
      'type' => 'page',
      'path' => ['alias' => '/test-node-page', 'langcode' => 'en'],
    ]);
    $node->save();
    $this->nodes[] = $node;

    return $node;
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
    $this->createNodeWithAlias();

    $links = [
      [
        'uuid' => '18b403a4-dfda-4301-b0da-1c81777aed2a',
        'title' => 'Link 1',
        'langcode' => 'en',
        'link' => ['uri' => 'internal:/admin/content'],
        'expanded' => TRUE,
        'weight' => 0,
        'description' => 'Link 1',
      ],
      [
        'uuid' => 'de6409aa-c620-4327-90f4-127176f209b2',
        'parent' => 'menu_link_content:18b403a4-dfda-4301-b0da-1c81777aed2a',
        'title' => 'Link 1 depth 1',
        'langcode' => 'en',
        'link' => ['uri' => 'internal:/test'],
        'weight' => 0,
      ],
      [
        'uuid' => '79736feb-77b7-4981-821b-90f02814e5b5',
        'title' => 'Link 2 - Unpublished',
        'langcode' => 'en',
        'enabled' => FALSE,
        'link' => ['uri' => 'internal:/test'],
        'weight' => 1,
      ],
      [
        'uuid' => '520df961-36dc-4fd4-8cff-3d3acaa9f0a8',
        'langcode' => 'fi',
        'title' => 'Link 3',
        'link' => ['uri' => 'entity:node/1'],
        'weight' => 2,
      ],
      [
        'uuid' => '3fd92b84-6b9c-4970-934e-6b4468b618c0',
        'langcode' => 'en',
        'title' => 'Link 4',
        'link' => ['uri' => 'entity:node/1'],
        'weight' => 3,
      ],
      [
        'uuid' => 'b108dc3c-3a22-455f-90a7-238331bc2bfe',
        'langcode' => 'en',
        'title' => 'Link 4 - Child 1',
        'parent' => 'menu_link_content:3fd92b84-6b9c-4970-934e-6b4468b618c0',
        'link' => ['uri' => 'internal:/test'],
        'weight' => 0,
      ],
      [
        'uuid' => '64a5a6d1-ffce-481b-b321-260d9cf66ad9',
        'langcode' => 'en',
        'title' => 'Link 5 internal',
        'link' => ['https://www.hel.fi/helsinki/test'],
        'lang_attribute' => 'en-US',
        'weight' => 4,
      ],
      [
        'uuid' => '0d8a1366-4fcd-4dbc-bb75-854dedf28a1b',
        'langcode' => 'en',
        'title' => 'Link 5 internal - Child 1',
        'parent' => 'menu_link_content:64a5a6d1-ffce-481b-b321-260d9cf66ad9',
        'link' => ['https://www.hel.fi/helsinki/2'],
        'weight' => 0,
      ],
      [
        'uuid' => '0b10ba16-e2d5-4251-ac37-8ed27a02ff1f',
        'langcode' => 'en',
        'title' => 'Link 5 internal - Child 1 - Child 1',
        'parent' => 'menu_link_content:0d8a1366-4fcd-4dbc-bb75-854dedf28a1b',
        'link' => ['tel:+358040123'],
        'weight' => 0,
      ],
      [
        'uuid' => '263464a3-bd76-4a4e-a77f-03ab4960ff1e',
        'langcode' => 'en',
        'title' => 'Link 6',
        'link' => ['uri' => 'route:<nolink>'],
        'lang_attribute' => 'en-GB',
        'weight' => 5,
      ],
    ];

    $linkEntities = [];
    foreach ($links as $link) {
      $linkEntities[$link['uuid']] = $this->createTestLink($link);
    }
    return $linkEntities;
  }

}
