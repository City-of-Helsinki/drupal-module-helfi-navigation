<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\helfi_api_base\Link\InternalDomainResolver;
use Drupal\helfi_navigation\Menu\MenuTreeBuilder;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\Menu\MenuTreeBuilder
 * @group helfi_navigation
 */
class MenuTreeBuilderTest extends UnitTestCase {

  /**
   * Tests root element validation.
   *
   * @dataProvider rootElementExceptionData
   * @covers ::__construct
   * @covers ::buildMenuTree
   */
  public function testRootElementException(\stdClass $rootElement) : void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Missing $rootElement->name, $rootElement->url or $rootElement->id property.');

    $menuTreeBuilder = new MenuTreeBuilder(
      $this->prophesize(EntityTypeManagerInterface::class)->reveal(),
      new InternalDomainResolver(),
      $this->prophesize(MenuLinkTreeInterface::class)->reveal(),
    );
    $menuTreeBuilder->buildMenuTree('main', 'en', $rootElement);
  }

  /**
   * The data provider for root element exception test.
   *
   * @return \object[][]
   *   The data.
   */
  public function rootElementExceptionData() : array {
    return [
      [
        (object) [],
      ],
      [
        (object) ['name' => 'test'],
      ],
      [
        (object) ['name' => 'test', 'url' => 'test'],
      ],
      [
        (object) ['name' => 'test', 'id' => 'test'],
      ],
    ];
  }

}