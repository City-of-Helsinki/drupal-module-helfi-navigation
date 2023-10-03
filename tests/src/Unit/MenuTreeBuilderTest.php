<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Unit;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\helfi_api_base\Link\InternalDomainResolver;
use Drupal\helfi_navigation\Menu\MenuTreeBuilder;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\Menu\MenuTreeBuilder
 * @group helfi_navigation
 */
class MenuTreeBuilderTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Tests root element validation.
   *
   * @dataProvider rootElementExceptionData
   * @covers ::__construct
   * @covers ::build
   */
  public function testRootElementException(\stdClass $rootElement) : void {
    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Missing $rootElement->name, $rootElement->url or $rootElement->id property.');

    $menuTree = $this->prophesize(MenuLinkTreeInterface::class);
    $menuTree->load(Argument::cetera(), Argument::cetera())
      ->shouldBeCalled()
      ->willReturn([]);
    $menuTree->transform(Argument::cetera(), Argument::cetera())
      ->shouldBeCalled()
      ->willReturn([]);

    $menuTreeBuilder = new MenuTreeBuilder(
      $this->prophesize(EntityTypeManagerInterface::class)->reveal(),
      new InternalDomainResolver(),
      $menuTree->reveal(),
      $this->prophesize(MenuLinkManagerInterface::class)->reveal(),
      $this->prophesize(EventDispatcherInterface::class)->reveal(),
      $this->prophesize(LanguageManagerInterface::class)->reveal(),
    );
    $menuTreeBuilder->build('main', 'en', $rootElement);
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
