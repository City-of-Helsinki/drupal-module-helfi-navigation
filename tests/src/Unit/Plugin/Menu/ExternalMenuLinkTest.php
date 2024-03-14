<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Unit\Plugin\QueueWorker;

use Drupal\helfi_navigation\Plugin\Menu\ExternalMenuLink;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\helfi_navigation\Plugin\Menu\ExternalMenuLink
 * @group helfi_navigation
 */
class ExternalMenuLinkTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::getDerivativeId
   */
  public function testGetDerivativeId() : void {
    $link = new ExternalMenuLink([], '', []);
    $this->assertNull($link->getDerivativeId());
  }

  /**
   * @covers ::getTitle
   */
  public function testGetTitle() : void {
    $expectedTitle = 'some title';
    $link = new ExternalMenuLink([], '', ['title' => $expectedTitle]);
    $this->assertEquals($expectedTitle, $link->getTitle());

    // Make sure TranslatableMarkup is converted to string too.
    $title = $this->getStringTranslationStub()->translate($expectedTitle);

    $link = new ExternalMenuLink([], '', ['title' => $title]);
    $this->assertEquals($expectedTitle, $link->getTitle());
  }

  /**
   * @covers ::getDescription
   */
  public function testGetDescription() : void {
    $expectedDescription = 'some description';
    $link = new ExternalMenuLink([], '', ['description' => $expectedDescription]);
    $this->assertEquals($expectedDescription, $link->getDescription());

    // Make sure TranslatableMarkup is converted to string too.
    $title = $this->getStringTranslationStub()->translate($expectedDescription);

    $link = new ExternalMenuLink([], '', ['description' => $title]);
    $this->assertEquals($expectedDescription, $link->getDescription());
  }

  /**
   * @covers ::updateLink
   * @covers ::getTitle
   */
  public function testUpdateLink() : void {
    $link = new ExternalMenuLink([], '', ['title' => '123']);
    $this->assertEquals('123', $link->getTitle());
    $link->updateLink(['title' => '321'], TRUE);
    $this->assertEquals('321', $link->getTitle());
  }

}
