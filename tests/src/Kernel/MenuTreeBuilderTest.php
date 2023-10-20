<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests menu tree builder.
 *
 * @coversDefaultClass \Drupal\helfi_navigation\Menu\MenuTreeBuilder
 * @group helfi_navigation
 */
class MenuTreeBuilderTest extends MenuTreeBuilderTestBase {

  use ProphecyTrait;

  /**
   * Tests menu tree without root object.
   *
   * @covers ::__construct
   * @covers ::build
   * @covers ::transform
   * @covers ::getEntity
   */
  public function testBuildMenuTreeWithoutRoot() : void {
    $this->createLinks();

    $tree = $this->getMenuTreeBuilder()->build('main', 'en');
    $this->assertTree($tree, FALSE);
  }

  /**
   * Asserts that tree is build correctly.
   *
   * @param array $tree
   *   The tree to run asserts against.
   * @param bool $hasRoot
   *   Whether the tree has root element.
   */
  private function assertTree(array $tree, bool $hasRoot) : void {
    // Only links after 'Link 3' should be available because:
    // - Anonymous user has no access to 'Link 1' and since 'Link 1 depth 1'
    // is a child of 'Link 1' and children inherit permission from its
    // parent, thus it should be hidden as well.
    // - Link 2 is unpublished.
    // - Link 3 is in different language.
    $this->assertCount(3, $tree);

    // Test <nolink>.
    $this->assertEquals('', $tree[2]->url);

    // Link 5 should have three links deep tree.
    $this->assertTrue($tree[1]->hasItems);
    $this->assertTrue($tree[1]->sub_tree[0]->hasItems);
    $this->assertFalse($tree[1]->sub_tree[0]->sub_tree[0]->hasItems);

    $expectedParents = [
      'menu_link_content:0b10ba16-e2d5-4251-ac37-8ed27a02ff1f',
      'menu_link_content:0d8a1366-4fcd-4dbc-bb75-854dedf28a1b',
      'menu_link_content:64a5a6d1-ffce-481b-b321-260d9cf66ad9',
    ];
    // Trees with root element should include the root element as a parent
    // as well.
    if ($hasRoot) {
      $expectedParents[] = 'liikenne';
    }
    // Make the whole tree is included in parents. The tree should be sorted
    // from last to first element in tree.
    $this->assertEquals($expectedParents, $tree[1]->sub_tree[0]->sub_tree[0]->parents);
    // Tel/mailto links should be external and have attributes to indicate that.
    $this->assertTrue($tree[1]->sub_tree[0]->sub_tree[0]->external);
    $this->assertEquals((object) [
      'data-external' => TRUE,
      'data-protocol' => 'tel',
    ], $tree[1]->sub_tree[0]->sub_tree[0]->attributes);
    // Link 5 should be marked as internal by InternalDomainResolver.
    $this->assertFalse($tree[1]->external);
  }

  /**
   * Tests menu tree build.
   *
   * @covers ::__construct
   * @covers ::build
   * @covers ::transform
   * @covers ::getEntity
   */
  public function testBuildMenuTree() : void {
    $linkEntities = $this->createLinks();

    $tree = $this->getMenuTree('en');
    $this->assertTree($tree['sub_tree'], TRUE);

    // Translate a subtree item to make sure it's not visible in
    // finnish, because the parent is not translated.
    $linkEntities['0d8a1366-4fcd-4dbc-bb75-854dedf28a1b']->addTranslation('fi')
      ->save();

    // Make sure lang attribute is set.
    $this->assertEquals('en-US', $tree['sub_tree'][1]->attributes->lang);

    // Only one finnish link should be available.
    $tree = $this->getMenuTree('fi');
    $this->assertCount(1, $tree['sub_tree']);
    $this->assertFalse($tree['sub_tree'][0]->hasItems);
    // Make sure first level links use root as their parent.
    $this->assertEquals('liikenne', $tree['sub_tree'][0]->parentId);

    // Make sure link becomes available when the parent is translated.
    $linkEntities['64a5a6d1-ffce-481b-b321-260d9cf66ad9']->addTranslation('fi')
      ->set('lang_attribute', 'fi-SV')
      ->save();

    $tree = $this->getMenuTree('fi');
    $this->assertCount(2, $tree['sub_tree']);
    $this->assertTrue($tree['sub_tree'][1]->hasItems);
    // Assert that last level link is not visible because it's not translated.
    $this->assertFalse($tree['sub_tree'][1]->sub_tree[0]->hasItems);
    // Assert that lang attribute can be translated and doesn't change the
    // original (english) value.
    $this->assertEquals('fi-SV', $tree['sub_tree'][1]->attributes->lang);
    $tree = $this->getMenuTree('en');
    $this->assertEquals('en-US', $tree['sub_tree'][1]->attributes->lang);

    // Unpublish a translated link and make sure it's not visible anymore.
    $linkEntities['64a5a6d1-ffce-481b-b321-260d9cf66ad9']
      ->getTranslation('fi')
      ->set('content_translation_status', FALSE)
      ->save();
    $tree = $this->getMenuTree('fi');
    $this->assertCount(1, $tree['sub_tree']);

    // Unpublish all nodes and make sure they are not visible anymore.
    foreach ($this->nodes as $node) {
      $node->setUnpublished()
        ->save();
    }
    /** @var \Drupal\Core\DrupalKernelInterface $kernel */
    $kernel = $this->container->get('kernel');
    // Rebuild the container to empty static entity cache.
    $kernel->rebuildContainer();

    // Make sure no links are visible after the node was unpublished.
    $tree = $this->getMenuTree('fi');
    $this->assertCount(0, $tree['sub_tree']);

    // Enable all english node translations.
    foreach ($this->nodes as $node) {
      if ($node->hasTranslation('en')) {
        $translation = $node->getTranslation('en');
        $translation->setPublished()->save();
      }
    }

    // Rebuild the container to empty static entity cache.
    $kernel->rebuildContainer();

    // Make sure english nodes are enabled.
    $tree = $this->getMenuTree('en');
    $this->assertCount(3, $tree['sub_tree']);

    // Disable all english node translations.
    foreach ($this->nodes as $node) {
      if ($node->hasTranslation('en')) {
        $translation = $node->getTranslation('en');
        $translation->setUnpublished()->save();
      }
    }

    // Rebuild the container to empty static entity cache.
    $kernel->rebuildContainer();

    // Make sure english nodes disappear.
    $tree = $this->getMenuTree('en');
    $this->assertCount(2, $tree['sub_tree']);

  }

}
