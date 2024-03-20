<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;

/**
 * Tests absolute url subscriber.
 *
 * @group helfi_navigation
 */
class AbsoluteUrlMenuTreeBuilderLinkSubscriberTest extends MenuTreeBuilderTestBase {

  use EnvironmentResolverTrait;

  /**
   * Tests that URLs are converted to absolute when the domains don't match.
   *
   * @dataProvider absoluteUrlData
   */
  public function testAbsoluteUrl(string $langcode, EnvironmentEnum $environment, string $expected) : void {
    $this->createLinks();
    $this->setActiveProject(Project::ASUMINEN, $environment);
    $menuTree = $this->getMenuTree($langcode);
    $this->assertEquals($expected, $menuTree['url']);
  }

  /**
   * A data provider.
   *
   * @return array[]
   *   The data.
   */
  public function absoluteUrlData() : array {
    return [
      // The URL should be absolute because the local environment has
      // a project-specific domains.
      [
        'fi',
        EnvironmentEnum::Local,
        'http://localhost/fi',
      ],
      // The URL should be relative because the prod environment shares
      // the domain name (www.hel.fi).
      [
        'en',
        EnvironmentEnum::Prod,
        '/en',
      ],
    ];
  }

}
