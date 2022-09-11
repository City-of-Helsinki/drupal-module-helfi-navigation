<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\helfi_navigation\Menu\MenuTreeBuilder;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Tests menu tree builder.
 *
 * @coversDefaultClass \Drupal\helfi_navigation\Menu\MenuTreeBuilder
 * @group helfi_navigation
 */
class MenuTreeBuilderTest extends KernelTestBase {

  /**
   * The menu tree builder.
   *
   * @var \Drupal\helfi_navigation\Menu\MenuTreeBuilder|null
   */
  protected ?MenuTreeBuilder $menuTreeBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();
    $this->enableTranslation(['menu_link_content']);
    $this->menuTreeBuilder = $this->container->get('helfi_navigation.menu_tree_builder');
  }

  /**
   * Create new menu link.
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
   * Tests menu tree build.
   *
   * @covers ::__construct
   * @covers ::buildMenuTree
   * @covers ::transformMenuItems
   * @covers ::getEntity
   */
  public function testBuildMenuTree() : void {
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
        'link' => ['uri' => 'internal:/test'],
      ],
      [
        'uuid' => '3fd92b84-6b9c-4970-934e-6b4468b618c0',
        'langcode' => 'en',
        'title' => 'Link 4',
        'link' => ['uri' => 'internal:/test'],
      ],
      [
        'uuid' => '64a5a6d1-ffce-481b-b321-260d9cf66ad9',
        'langcode' => 'en',
        'title' => 'Link 5 internal',
        'link' => ['https://www.hel.fi/helsinki/test'],
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
    ];

    $linkEntities = [];
    foreach ($links as $link) {
      $linkEntities[$link['uuid']] = $this->createTestLink($link);
    }

    $tree = $this->menuTreeBuilder->buildMenuTree('main', 'en', (object) [
      'name' => 'Test',
      'url' => 'https://localhost/test',
      'id' => 'liikenne',
    ]);
    // Only links after 'Link 3' should be available because:
    // - Anonymous user has no access to 'Link 1' and since 'Link 1 depth 1'
    // is a child of 'Link 1' and children inherit permission from its
    // parent, thus it should be hidden as well.
    // - Link 2 is unpublished.
    // - Link 3 is in different language.
    $this->assertCount(2, $tree['sub_tree']);
    $this->assertFalse($tree['sub_tree'][0]->hasItems);

    // Link 5 should have three links deep tree.
    $this->assertTrue($tree['sub_tree'][1]->hasItems);
    $this->assertTrue($tree['sub_tree'][1]->sub_tree[0]->hasItems);
    $this->assertFalse($tree['sub_tree'][1]->sub_tree[0]->sub_tree[0]->hasItems);
    // Tel/mailto links should be external and have attributes to indicate that.
    $this->assertTrue($tree['sub_tree'][1]->sub_tree[0]->sub_tree[0]->external);
    $this->assertEquals((object) [
      'data-external' => TRUE,
      'data-protocol' => 'tel',
    ], $tree['sub_tree'][1]->sub_tree[0]->sub_tree[0]->attributes);
    // Link 5 should be marked as internal by InternalDomainResolver.
    $this->assertFalse($tree['sub_tree'][1]->external);

    // Translate one subtree item to make sure it's not visible in
    // finnish, because the parent is not translated.
    $linkEntities['0d8a1366-4fcd-4dbc-bb75-854dedf28a1b']->addTranslation('fi')
      ->save();

    // Only one finnish link should be available.
    $tree = $this->menuTreeBuilder->buildMenuTree('main', 'fi', (object) [
      'name' => 'Test',
      'url' => 'https://localhost/test',
      'id' => 'liikenne',
    ]);
    $this->assertCount(1, $tree['sub_tree']);
    $this->assertFalse($tree['sub_tree'][0]->hasItems);

    // Make sure link becomes available when the parent is translated.
    $linkEntities['64a5a6d1-ffce-481b-b321-260d9cf66ad9']->addTranslation('fi')
      ->save();

    $tree = $this->menuTreeBuilder->buildMenuTree('main', 'fi', (object) [
      'name' => 'Test',
      'url' => 'https://localhost/test',
      'id' => 'liikenne',
    ]);
    $this->assertCount(2, $tree['sub_tree']);
    $this->assertTrue($tree['sub_tree'][1]->hasItems);
    // Make sure last level link is not visible because it's not translated.
    $this->assertFalse($tree['sub_tree'][1]->sub_tree[0]->hasItems);
  }

}
