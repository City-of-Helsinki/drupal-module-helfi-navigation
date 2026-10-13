<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Unit;

use Drupal\helfi_navigation\MenuItemSanitizer;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\MenuItemSanitizer
 * @group helfi_navigation
 */
class MenuItemSanitizerTest extends UnitTestCase {

  /**
   * Data provider for testIsSafeUrl().
   *
   * @return array<array{string, bool}>
   *   The URLs and whether they are safe.
   */
  public static function urlData() : array {
    return [
      ['https://www.hel.fi/fi/asuminen', TRUE],
      ['http://www.hel.fi', TRUE],
      ['/fi/asuminen', TRUE],
      ['/fi/asuminen?a=b:c#d', TRUE],
      ['javascript:alert(1)', FALSE],
      ['JavaScript:alert(1)', FALSE],
      [' javascript:alert(1)', FALSE],
      ["java\tscript:alert(1)", FALSE],
      ['data:text/html;base64,PHNjcmlwdD4=', FALSE],
      ['vbscript:msgbox(1)', FALSE],
    ];
  }

  /**
   * @covers ::isSafeUrl
   */
  #[DataProvider('urlData')]
  public function testIsSafeUrl(string $url, bool $expected) : void {
    $this->assertSame($expected, MenuItemSanitizer::isSafeUrl($url));
  }

  /**
   * @covers ::filterAttributes
   */
  public function testFilterAttributes() : void {
    $this->assertSame([
      'data-external' => TRUE,
      'lang' => 'fi',
    ], MenuItemSanitizer::filterAttributes([
      'data-external' => TRUE,
      'onclick' => 'alert(1)',
      'lang' => 'fi',
    ]));
  }

  /**
   * @covers ::sanitizeTree
   * @covers ::isSafeUrl
   * @covers ::filterAttributes
   */
  public function testSanitizeTree() : void {
    $tree = MenuItemSanitizer::sanitizeTree([
      [
        'url' => 'https://www.hel.fi/fi',
        'attributes' => ['lang' => 'fi', 'onmouseover' => 'alert(1)'],
        'sub_tree' => [
          ['url' => 'javascript:alert(1)'],
          [
            'url' => '/fi/asuminen',
            'sub_tree' => [
              ['url' => 'data:text/html,test'],
              ['url' => ['not', 'a', 'string']],
            ],
          ],
        ],
      ],
    ]);

    $this->assertSame('https://www.hel.fi/fi', $tree[0]['url']);
    $this->assertSame(['lang' => 'fi'], $tree[0]['attributes']);
    $this->assertSame('', $tree[0]['sub_tree'][0]['url']);
    $this->assertSame('/fi/asuminen', $tree[0]['sub_tree'][1]['url']);
    $this->assertSame('', $tree[0]['sub_tree'][1]['sub_tree'][0]['url']);
    $this->assertSame('', $tree[0]['sub_tree'][1]['sub_tree'][1]['url']);
  }

}
