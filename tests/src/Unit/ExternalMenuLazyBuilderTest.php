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
   *
   * @var array
   */
  protected array $defaultOptions = [
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
   * Provides data for testBuildWithVariousDataFormats().
   *
   * @return array
   *   Test cases.
   */
  public static function menuTreeDataProvider(): array {
    $numericData = array_fill(0, 3, new \stdClass());

    $objectData = [];
    foreach (['Item A', 'Item B', 'Item C'] as $title) {
      $item = new \stdClass();
      $item->menu_tree = [['title' => $title]];
      $objectData[] = $item;
    }

    return [
      'numeric-keyed' => [
        $numericData,
        [
          ['title' => 'Item 1'],
          ['title' => 'Item 2'],
          ['title' => 'Item 3'],
        ],
        [],
        FALSE,
      ],
      'object-keyed' => [
        $objectData,
        [
          ['title' => 'Item A'],
          ['title' => 'Item B'],
          ['title' => 'Item C'],
        ],
        [
          'menuId' => 'other',
          'themeSuggestion' => 'menu__external_menu__other',
        ],
        TRUE,
      ],
    ];
  }

  /**
   * Tests build() with different response formats.
   *
   * @param array|object $data
   *   The raw API data.
   * @param array $expectedTree
   *   The expected menu tree.
   * @param array $overrides
   *   Any overrides to default lazy builder options.
   * @param bool $objectKeyed
   *   Whether the API data should be cast to object.
   *
   * @dataProvider menuTreeDataProvider
   */
  public function testBuildWithVariousDataFormats(array|object $data, array $expectedTree, array $overrides, bool $objectKeyed): void {
    $options = $this->getLazyBuilderOptions($overrides);
    $result = $this->simulateLazyBuilderBuild($data, $expectedTree, $options, $objectKeyed);

    $this->assertEquals($expectedTree, $result['#items']);
    $this->assertEquals('menu__external_menu', $result['#theme']);
    $this->assertEquals($options['menuId'], $result['#menu_type']);
    $this->assertEquals($options['themeSuggestion'], $result['#attributes']['theme_suggestion']);
    $this->assertArrayNotHasKey('#cache', $result);
  }

  /**
   * Tests build() when the API call fails.
   */
  public function testBuildWhenApiFails(): void {
    $this->apiManager
      ->get('fi', 'failing_menu', Argument::type('array'))
      ->willThrow(new \Exception('API error'));

    $sut = $this->getSut();
    $result = $sut->build('failing_menu', 'fi', '', 2, 0, FALSE, 'theme_suggestion');

    $this->assertEquals(60, $result['#cache']['max-age']);
    $this->assertArrayNotHasKey('#items', $result);
  }

  /**
   * Tests build() when API returns an empty response.
   */
  public function testBuildWithEmptyResponse(): void {
    $result = $this->simulateLazyBuilderBuild([], [], $this->defaultOptions, FALSE);
    $this->assertEquals([], $result['#items']);
  }

  /**
   * Tests build() when tree builder returns NULL.
   */
  public function testBuildWhenTreeBuilderReturnsNull(): void {
    $response = new ApiResponse((object) []);

    $this->apiManager
      ->get('fi', 'main', [])
      ->willReturn($response);

    $this->treeBuilder
      ->build([], Argument::any())
      ->willReturn(NULL);

    $sut = $this->getSut();
    $result = $sut->build('main', 'fi', '', 2, 0, FALSE, 'fallback_theme');

    $this->assertEquals(60, $result['#cache']['max-age']);
    $this->assertEmpty($result['#items']);
  }

  /**
   * Tests that trustedCallbacks() returns the correct value.
   */
  public function testTrustedCallbacks(): void {
    $this->assertEquals(['build'], ExternalMenuLazyBuilder::trustedCallbacks());
  }

  /**
   * Gets lazy builder options with optional overrides.
   *
   * @param array $overrides
   *   Options to override.
   *
   * @return array
   *   Merged options.
   */
  private function getLazyBuilderOptions(array $overrides = []): array {
    return array_merge($this->defaultOptions, $overrides);
  }

  /**
   * Simulates the lazy builder build method.
   *
   * @param array|object $data
   *   The API response data.
   * @param array $expectedMenuTree
   *   The expected menu tree.
   * @param array $options
   *   The options for the builder.
   * @param bool $objectKeyed
   *   Whether to use object casting for the data.
   *
   * @return array
   *   The render array result.
   */
  private function simulateLazyBuilderBuild(array|object $data, array $expectedMenuTree, array $options, bool $objectKeyed): array {
    $response = $objectKeyed
      ? new ApiResponse((object) $data)
      : new ApiResponse((array) $data);

    $requestOptions = !empty($options['maxDepth'])
      ? ['query' => "max-depth={$options['maxDepth']}"]
      : [];

    $this->apiManager
      ->get($options['langcode'], $options['menuId'], $requestOptions)
      ->willReturn($response);

    $treeBuilderOptions = [
      'menu_type' => $options['menuId'],
      'max_depth' => $options['maxDepth'],
      'level' => $options['startingLevel'],
      'expand_all_items' => $options['expandAllItems'],
      'theme_suggestion' => $options['themeSuggestion'],
    ];

    $this->treeBuilder
      ->build($data, $treeBuilderOptions)
      ->willReturn($expectedMenuTree);

    $sut = $this->getSut();

    $query = $options['maxDepth'] > 0 ? "max-depth={$options['maxDepth']}" : '';

    return $sut->build(
      $options['menuId'],
      $options['langcode'],
      $query,
      $options['maxDepth'],
      $options['startingLevel'],
      $options['expandAllItems'],
      $options['themeSuggestion'],
    );
  }

}
