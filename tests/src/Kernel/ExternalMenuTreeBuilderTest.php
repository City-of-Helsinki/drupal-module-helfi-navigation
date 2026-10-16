<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\helfi_navigation\ExternalMenuTreeBuilder;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests external menu tree builder.
 *
 * @coversDefaultClass \Drupal\helfi_navigation\ExternalMenuTreeBuilder
 * @group helfi_navigation
 */
class ExternalMenuTreeBuilderTest extends MenuTreeBuilderTestBase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) : void {
    $container->setParameter('helfi_api_base.internal_domains', [
      'localhost',
    ]);
    parent::register($container);
  }

  /**
   * Constructs a new external menu tree.
   *
   * @param \Prophecy\Prophecy\ObjectProphecy $requestStack
   *   The request stack mock.
   *
   * @return array
   *   The external menu tree.
   */
  private function getExternalMenuTree(ObjectProphecy $requestStack) : array {
    $this->createLinks();

    $options = [
      'level' => 0,
      'menu_type' => 'main',
      'expand_all_items' => FALSE,
      'max_depth' => 10,
    ];
    $tree = $this->getMenuTree('en');
    // Convert to stdClass.
    $tree = json_decode(json_encode($tree));

    $externalMenuTreeBuilder = new ExternalMenuTreeBuilder(
      $this->container->get('helfi_api_base.internal_domain_resolver'),
      $requestStack->reveal(),
    );
    return $externalMenuTreeBuilder->build($tree->sub_tree, $options);
  }

  /**
   * Make sure trying to render menu tree without active request will fail.
   */
  public function testInvalidRequestException() : void {
    $this->expectException(\LogicException::class);

    $requestStack = $this->prophesize(RequestStack::class);
    $requestStack->getCurrentRequest()->willReturn(NULL);
    $this->getExternalMenuTree($requestStack);
  }

  /**
   * Tests external menu tree build.
   *
   * @covers ::__construct
   * @covers ::build
   * @covers ::transform
   * @covers ::updateActiveTrail
   * @covers ::createLink
   * @covers ::inActiveTrail
   */
  public function testBuild() : void {
    $request = $this->prophesize(Request::class);
    $request->getUri()->willReturn('http://localhost/test');
    $requestStack = $this->prophesize(RequestStack::class);
    $requestStack->getCurrentRequest()->willReturn($request->reveal());

    $externalTree = $this->getExternalMenuTree($requestStack);
    // Make sure parent is marked in active trail as well when the child item
    // is the currently active page.
    $this->assertTrue($externalTree[0]['in_active_trail']);
    $this->assertFalse($externalTree[0]['is_currentPage']);
    $this->assertTrue($externalTree[0]['below'][0]['in_active_trail']);
    $this->assertTrue($externalTree[0]['below'][0]['is_currentPage']);
  }

  /**
   * Tests that the unsafe URLs and attributes are not rendered.
   *
   * @covers ::createLink
   * @covers ::createUrl
   */
  public function testUnsafeItems() : void {
    $request = $this->prophesize(Request::class);
    $request->getUri()->willReturn('http://localhost/test');
    $requestStack = $this->prophesize(RequestStack::class);
    $requestStack->getCurrentRequest()->willReturn($request->reveal());

    $items = json_decode(json_encode([
      [
        'id' => 'safe',
        'name' => 'Safe',
        'url' => 'https://www.hel.fi/fi',
        'attributes' => ['lang' => 'fi', 'onclick' => 'alert(1)'],
        'sub_tree' => [
          [
            'id' => 'unsafe',
            'name' => 'Unsafe',
            'url' => 'javascript:alert(document.cookie)',
          ],
        ],
      ],
    ], JSON_THROW_ON_ERROR));
    $externalMenuTreeBuilder = new ExternalMenuTreeBuilder(
      $this->container->get('helfi_api_base.internal_domain_resolver'),
      $requestStack->reveal(),
    );
    $tree = $externalMenuTreeBuilder->build($items, [
      'level' => 0,
      'menu_type' => 'main',
      'expand_all_items' => FALSE,
      'max_depth' => 10,
    ]);

    $this->assertSame('https://www.hel.fi/fi', $tree[0]['url']->toString());
    $this->assertSame(['lang' => 'fi'], $tree[0]['attributes']->toArray());
    $this->assertSame('<nolink>', $tree[0]['below'][0]['url']->getRouteName());
  }

}
