<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\redirect\Entity\Redirect;

/**
 * Tests menu tree builder with redirect module.
 *
 * @group helfi_navigation
 */
class MenuTreeBuilderRedirectTest extends MenuTreeBuilderTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['redirect'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('redirect');

    $this->config('system.site')
      ->set('page', ['front' => '/front'])
      ->save();
  }

  /**
   * Make sure redirects are taken into account.
   */
  public function testRedirect() : void {
    $this->createLinks();

    // Create redirect from /front (default <front>) to /front-page-redirect
    // and make sure it's taken into account.
    $redirect = Redirect::create();
    $redirect->setLanguage('en');
    $redirect->setSource('/front');
    $redirect->setRedirect('/en/front-page-redirect');
    $redirect->save();

    $tree = $this->getMenuTree('en');
    $this->assertTrue(str_ends_with($tree['url'], '/en/front-page-redirect'));

    // Create redirect from /en/test to /node/1, which should then become
    // /en/test-node-page.
    $redirect = Redirect::create();
    $redirect->setLanguage('en');
    $redirect->setSource('test');
    $redirect->setRedirect('/node/1');
    $redirect->save();

    // Make sure english menu is translated when redirect is created for
    // english only.
    $tree = $this->getMenuTree('en');
    $this->assertTrue(str_ends_with($tree['sub_tree'][0]->sub_tree[0]->url, '/en/test-node-page'));

    // Make sure finnish link has no path alias because node has
    // no finnish translation.
    $tree = $this->getMenuTree('fi');
    $this->assertTrue(str_ends_with($tree['sub_tree'][0]->url, '/fi/node/1'));
  }

}
