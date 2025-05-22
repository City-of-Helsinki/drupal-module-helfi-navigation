<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Unit;

use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\ExternalMenuLazyBuilder;
use Drupal\helfi_navigation\ExternalMenuTreeBuilderInterface;
use Drupal\helfi_api_base\ApiClient\ApiResponse;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Unit test for the ExternalMenuLazyBuilder class.
 *
 * @coversDefaultClass \Drupal\helfi_navigation\ExternalMenuLazyBuilder
 */
final class ExternalMenuLazyBuilderTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The API manager prophecy.
   *
   * @var \Drupal\helfi_navigation\ApiManager|\Prophecy\Prophecy\ObjectProphecy
   */
  protected ApiManager|ObjectProphecy $apiManager;

  /**
   * The external menu tree builder prophecy.
   *
   * @var \Drupal\helfi_navigation\ExternalMenuTreeBuilderInterface|\Prophecy\Prophecy\ObjectProphecy
   */
  private ExternalMenuTreeBuilderInterface|ObjectProphecy $treeBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->apiManager = $this->prophesize(ApiManager::class);
    $this->treeBuilder = $this->prophesize(ExternalMenuTreeBuilderInterface::class);
  }

  /**
   * Gets the SUT.
   *
   * @return \Drupal\helfi_navigation\ExternalMenuLazyBuilder
   *   The lazy builder.
   */
  private function getSut(): ExternalMenuLazyBuilder {
    return new ExternalMenuLazyBuilder(
      $this->apiManager->reveal(),
      $this->treeBuilder->reveal(),
    );
  }

  /**
   * Test build with numeric-keyed API response.
   */
  public function testBuildWithNumericKeyedData(): void {
    $data = [
      0 => new \stdClass(),
      1 => new \stdClass(),
      2 => new \stdClass(),
    ];
    $response = new ApiResponse((object) $data);

    $this->apiManager
      ->get('fi', 'main', ['query' => 'test'])
      ->willReturn($response);

    $menuTree = [
      ['title' => 'Menu item'],
      ['title' => 'Another menu item'],
    ];

    $this->treeBuilder
      ->build($data, [
        'menu_type' => 'main',
        'max_depth' => 5,
        'level' => 0,
        'expand_all_items' => FALSE,
        'theme_suggestion' => 'menu__external',
      ])
      ->willReturn($menuTree);

    $sut = $this->getSut();
    $result = $sut->build('main', 'fi', 'test', 5, 0, FALSE, 'menu__external');

    $this->assertEquals($menuTree, $result['#items']);
    $this->assertEquals('menu__external_menu', $result['#theme']);
    $this->assertEquals('main', $result['#menu_type']);
    $this->assertArrayNotHasKey('#cache', $result);
  }

  /**
   * Test build with object-keyed API response.
   */
  public function testBuildWithObjectKeyedData(): void {
    $item1 = new \stdClass();
    $item1->menu_tree = [['title' => 'Menu item']];
    $item2 = new \stdClass();
    $item2->menu_tree = [['title' => 'Another menu item']];

    $data = [
      'some' => $item1,
      'other' => $item2,
    ];
    $response = new ApiResponse((object) $data);

    $this->apiManager
      ->get('fi', 'global', (array) Argument::any())
      ->willReturn($response);

    $expectedTree = [
      ['title' => 'Menu item'],
      ['title' => 'Another menu item'],
    ];

    $this->treeBuilder
      ->build($expectedTree, [
        'menu_type' => 'global',
        'max_depth' => 3,
        'level' => 1,
        'expand_all_items' => TRUE,
        'theme_suggestion' => 'menu__alt',
      ])
      ->willReturn($expectedTree);

    $sut = $this->getSut();
    $result = $sut->build(
      'global',
      'fi',
      '',
      3,
      1,
      TRUE,
      'menu__alt'
    );

    $this->assertEquals($expectedTree, $result['#items']);
    $this->assertEquals('menu__external_menu', $result['#theme']);
    $this->assertEquals('global', $result['#menu_type']);
    $this->assertArrayNotHasKey('#cache', $result);
  }

  /**
   * Test build when the API call fails (e.g. throws an exception).
   */
  public function testBuildWhenApiFails(): void {
    $this->apiManager
      ->get('fi', 'failing_menu', (array) Argument::any())
      ->willThrow(new \Exception('API error'));

    $sut = $this->getSut();
    $result = $sut->build('failing_menu', 'fi', '', 2, 0, FALSE, 'theme_suggestion');

    $this->assertEquals(60, $result['#cache']['max-age']);
    $this->assertArrayNotHasKey('#items', $result);
  }

  /**
   * Test the build with an empty API response.
   */
  public function testBuildWithEmptyResponse(): void {
    $response = new ApiResponse((object) []);

    $this->apiManager
      ->get('fi', 'main', [])
      ->willReturn($response);

    $this->treeBuilder
      ->build([], [
        'menu_type' => 'main',
        'max_depth' => 3,
        'level' => 0,
        'expand_all_items' => FALSE,
        'theme_suggestion' => 'default_theme',
      ])
      ->willReturn([]);

    $sut = $this->getSut();
    $result = $sut->build(
      'main',
      'fi',
      '',
      3,
      0,
      FALSE,
      'default_theme',
    );

    $this->assertEquals([], $result['#items']);
  }

  /**
   * Tests fallback cache when treeBuilder returns null.
   */
  public function testBuildWhenTreeBuilderReturnsNull(): void {
    $response = new ApiResponse((object) []);

    $this->apiManager
      ->get('fi', 'main', [])
      ->willReturn($response);

    $this->treeBuilder
      ->build([], (array) Argument::any())
      ->willReturn(NULL);

    $sut = $this->getSut();
    $result = $sut->build('main', 'fi', '', 2, 0, FALSE, 'fallback_theme');

    $this->assertEquals(60, $result['#cache']['max-age']);
    $this->assertempty($result['#items']);
  }

  /**
   * Tests build with object-keyed data that lacks menu_tree property.
   */
  public function testBuildWithMalformedGlobalData(): void {
    $badItem = new \stdClass();
    $response = new ApiResponse((object) ['some' => $badItem]);

    $this->apiManager
      ->get('fi', 'global', (array) Argument::any())
      ->willReturn($response);

    $this->treeBuilder
      ->build([], [
        'menu_type' => 'global',
        'max_depth' => 2,
        'level' => 0,
        'expand_all_items' => FALSE,
        'theme_suggestion' => 'broken_theme',
      ])
      ->willReturn([]);

    $sut = $this->getSut();
    $result = $sut->build(
      'global',
      'fi',
      '',
      2,
      0,
      FALSE,
      'broken_theme',
    );

    $this->assertEquals([], $result['#items']);
  }

  /**
   * Test trustedCallbacks() returns the expected callback method.
   */
  public function testTrustedCallbacks(): void {
    $this->assertEquals(['build'], ExternalMenuLazyBuilder::trustedCallbacks());
  }

}
