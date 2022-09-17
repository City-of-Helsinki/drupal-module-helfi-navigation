<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

/**
 * Tests menu tree builder.
 *
 * @coversDefaultClass \Drupal\helfi_navigation\Menu\MenuTreeBuilder
 * @group helfi_navigation
 */
class MenuTreeBuilderTest extends MenuTreeBuilderTestBase {

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
    // Only links after 'Link 3' should be available because:
    // - Anonymous user has no access to 'Link 1' and since 'Link 1 depth 1'
    // is a child of 'Link 1' and children inherit permission from its
    // parent, thus it should be hidden as well.
    // - Link 2 is unpublished.
    // - Link 3 is in different language.
    $this->assertCount(3, $tree['sub_tree']);

    // Test <nolink>.
    $this->assertEquals('', $tree['sub_tree'][2]->url);
    // Test "lang" attribute.
    $this->assertEquals('en-GB', $tree['sub_tree'][2]->attributes->lang);

    // Link 5 should have three links deep tree.
    $this->assertTrue($tree['sub_tree'][1]->hasItems);
    $this->assertTrue($tree['sub_tree'][1]->sub_tree[0]->hasItems);
    $this->assertFalse($tree['sub_tree'][1]->sub_tree[0]->sub_tree[0]->hasItems);
    // Make the whole tree is included in parents. The tree should be sorted
    // from last to first element in tree.
    $this->assertEquals([
      'menu_link_content:0b10ba16-e2d5-4251-ac37-8ed27a02ff1f',
      'menu_link_content:0d8a1366-4fcd-4dbc-bb75-854dedf28a1b',
      'menu_link_content:64a5a6d1-ffce-481b-b321-260d9cf66ad9',
      'liikenne',
    ], $tree['sub_tree'][1]->sub_tree[0]->sub_tree[0]->parents);
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
    $tree = $this->getMenuTree('fi');
    $this->assertCount(1, $tree['sub_tree']);
    $this->assertFalse($tree['sub_tree'][0]->hasItems);
    // Make sure first level links use root as their parent.
    $this->assertEquals('liikenne', $tree['sub_tree'][0]->parentId);

    // Make sure link becomes available when the parent is translated.
    $linkEntities['64a5a6d1-ffce-481b-b321-260d9cf66ad9']->addTranslation('fi')
      ->save();

    $tree = $this->getMenuTree('fi');
    $this->assertCount(2, $tree['sub_tree']);
    $this->assertTrue($tree['sub_tree'][1]->hasItems);
    // Make sure last level link is not visible because it's not translated.
    $this->assertFalse($tree['sub_tree'][1]->sub_tree[0]->hasItems);
  }

}
