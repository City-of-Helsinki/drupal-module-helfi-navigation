<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel\Plugin\rest\Resource;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_navigation\Kernel\MenuTreeBuilderTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Tests Global mobile menu rest endpoint.
 *
 * @coversDefaultClass \Drupal\helfi_navigation\Plugin\rest\resource\GlobalMobileMenu
 * @group helfi_navigation
 */
class GlobalMobileMenuTest extends MenuTreeBuilderTestBase {

  use ProphecyTrait;
  use ApiTestTrait;
  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'migrate',
    'serialization',
    'rest',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('helfi_navigation');
  }

  /**
   * Grants required permissions for anonymous user.
   */
  private function grantRestfulPermissions() : void {
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('restful get helfi_global_mobile_menu');
  }

  /**
   * @covers ::__construct
   * @covers ::create
   * @covers ::get
   */
  public function test403() : void {
    // Make sure we get a 403 response when permissions are not set.
    $request = $this->getMockedRequest('/api/v1/global-mobile-menu');
    $response = $this->processRequest($request);

    $this->assertEquals(HttpResponse::HTTP_FORBIDDEN, $response->getStatusCode());
  }

  /**
   * @covers ::__construct
   * @covers ::create
   * @covers ::get
   */
  public function test404() : void {
    $this->grantRestfulPermissions();
    $this->container->get('config.factory')
      ->getEditable('helfi_navigation.settings')
      ->set('global_navigation_enabled', TRUE)
      ->save();

    // Make sure a 404 response is sent when we fail to fetch mobile navigation.
    $request = $this->getMockedRequest('/api/v1/global-mobile-menu');
    $response = $this->processRequest($request);

    $this->assertEquals(HttpResponse::HTTP_NOT_FOUND, $response->getStatusCode());
  }

  /**
   * @covers ::get
   * @covers ::__construct
   * @covers ::normalizeResponseData
   * @covers ::toResourceResponse
   */
  public function testEndpointWithLocalModifications() : void {
    $this->grantRestfulPermissions();
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);
    $this->config('system.site')
      ->set('name', Project::ASUMINEN)
      ->save();

    $request = $this->getMockedRequest('/api/v1/global-mobile-menu');
    $response = $this->processRequest($request);
    $array = json_decode($response->getContent(), TRUE);

    // Make sure 'is_injected' property is set, indicating that
    // local navigation is appended into API response.
    $this->assertTrue($array[Project::ASUMINEN]['menu_tree'][0]['is_injected']);
  }

  /**
   * @covers ::get
   * @covers ::__construct
   * @covers ::normalizeResponseData
   * @covers ::toResourceResponse
   */
  public function testEndpointPassthrough() : void {
    $request = $this->getMockedRequest('/api/v1/global-mobile-menu');
    $response = $this->processRequest($request);
    $array = json_decode($response->getContent(), TRUE);

    // Make sure Asuminen project has no navigation since
    // we just return the API response from global menu
    // endpoint without any modifications.
    $this->assertArrayNotHasKey(Project::ASUMINEN, $array);
  }

}
