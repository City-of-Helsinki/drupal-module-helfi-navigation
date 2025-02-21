<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel\Plugin\Block;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\Plugin\Block\MobileMenuFallbackBlock;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_api_base\Traits\LanguageManagerTrait;
use Drupal\Tests\helfi_navigation\Kernel\MenuTreeBuilderTestBase;

/**
 * Tests Mobile menu fallback.
 *
 * @group helfi_navigation
 */
class MobileMenuFallbackTest extends MenuTreeBuilderTestBase {

  use EnvironmentResolverTrait;
  use LanguageManagerTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'locale',
    'menu_block_current_language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    NodeType::create(['type' => 'page'])->save();
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);

    \Drupal::service('content_translation.manager')->setEnabled('node', 'page', TRUE);
  }

  /**
   * Tests build with different languages.
   */
  public function testBuild() : void {
    $this->setOverrideLanguageCode('en');
    $this->createLinks();
    $sut = MobileMenuFallbackBlock::create($this->container, [], '', ['provider' => 'helfi_navigation']);
    $build = $sut->build();
    // Only links after 'Link 3' should be available because:
    // - Anonymous user has no access to 'Link 1' and since 'Link 1 depth 1'
    // is a child of 'Link 1' and children inherit permission from its
    // parent, thus it should be hidden as well.
    // - Link 2 is unpublished.
    // - Link 3 is in different language.
    $this->assertCount(3, $build['#items']);

    $this->setOverrideLanguageCode('fi');
    $build = $sut->build();
    // Only 'Link 3' should be available because
    // other links are in different language.
    $this->assertCount(1, $build['#items']);
  }

}
