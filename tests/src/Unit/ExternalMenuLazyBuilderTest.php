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
  protected ExternalMenuTreeBuilderInterface|ObjectProphecy $treeBuilder;

  /**
   * Default options.
   */
  protected array $lazyBuilderOptions = [
    'menuId' => 'main',
    'langcode' => 'fi',
    'maxDepth' => 4,
    'startingLevel' => 0,
    'expandAllItems' => FALSE,
    'themeSuggestion' => 'menu__external_menu__main',
  ];

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
    for ($i = 0; $i < 3; $i++) {
      $data[$i] = new \stdClass();
    }

    $expectedMenuTree = [
      ['title' => 'Menu item'],
      ['title' => 'Another menu item'],
      ['title' => 'Yet another menu item'],
    ];

    $result = $this->simulateLazyBuilderBuild(
      $data,
      $expectedMenuTree,
      $this->lazyBuilderOptions,
    );

    $this->assertEquals($expectedMenuTree, $result['#items']);
    $this->assertEquals('menu__external_menu', $result['#theme']);
    $this->assertEquals('main', $result['#menu_type']);
    $this->assertEquals('menu__external_menu__' . $result['#menu_type'], $result['#attributes']['theme_suggestion']);
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
    $item3 = new \stdClass();
    $item3->menu_tree = [['title' => 'Yet another menu item']];

    $data = [$item1, $item2,
      $item3,
    ];

    $expectedMenuTree = [
      ['title' => 'Menu item'],
      ['title' => 'Another menu item'],
      ['title' => 'Yet another menu item'],
    ];

    $this->lazyBuilderOptions['menuId'] = 'other';
    $this->lazyBuilderOptions['themeSuggestion'] = 'menu__external_menu__other';

    $result = $this->simulateLazyBuilderBuild(
      $data,
      $expectedMenuTree,
      $this->lazyBuilderOptions,
      TRUE
    );

    $this->assertEquals($expectedMenuTree, $result['#items']);
    $this->assertEquals('menu__external_menu', $result['#theme']);
    $this->assertEquals('other', $result['#menu_type']);
    $this->assertEquals('menu__external_menu__' . $result['#menu_type'], $result['#attributes']['theme_suggestion']);
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
    $result = $this->simulateLazyBuilderBuild(
      [],
      [],
      $this->lazyBuilderOptions,
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
      ->build(Argument::exact([]), Argument::any())
      ->willReturn(NULL);

    $sut = $this->getSut();
    $result = $sut->build('main', 'fi', '', 2, 0, FALSE, 'fallback_theme');

    $this->assertEquals(60, $result['#cache']['max-age']);
    $this->assertEmpty($result['#items']);
  }

  /**
   * Test trustedCallbacks() returns the expected callback method.
   */
  public function testTrustedCallbacks(): void {
    $this->assertEquals(['build'], ExternalMenuLazyBuilder::trustedCallbacks());
  }

  /**
   * Simulates the lazy builder build method.
   */
  protected function simulateLazyBuilderBuild($data, $expectedMenuTree, $lazyBuilderOptions = [], $objectKeyed = FALSE): array {
    $response = $objectKeyed
      ? new ApiResponse((object) $data)
      : new ApiResponse((array) $data);

    if ($data === NULL) {
      $response = new ApiResponse((object) []);
    }

    [
      'menuId' => $menuId,
      'langcode' => $langcode,
      'maxDepth' => $maxDepth,
      'startingLevel' => $startingLevel,
      'expandAllItems' => $expandAllItems,
      'themeSuggestion' => $themeSuggestion,
    ] = $this->lazyBuilderOptions;

    $requestOptions = !empty($maxDepth)
      ? ['query' => "max-depth=$maxDepth"]
      : (array) Argument::any();

    $this->apiManager
      ->get($langcode, $menuId, $requestOptions)
      ->willReturn($response);

    $treeBuilderOptions = [
      'menu_type' => $menuId,
      'max_depth' => $maxDepth,
      'level' => $startingLevel,
      'expand_all_items' => $expandAllItems,
      'theme_suggestion' => $themeSuggestion,
    ];

    $this->treeBuilder
      ->build($data, $treeBuilderOptions)
      ->willReturn($expectedMenuTree);
    $sut = $this->getSut();

    return $sut->build(
      $menuId,
      $langcode,
      !empty($maxDepth) ? "max-depth=$maxDepth" : '',
      $maxDepth,
      $startingLevel,
      $expandAllItems,
      $themeSuggestion,
    );
  }

}
