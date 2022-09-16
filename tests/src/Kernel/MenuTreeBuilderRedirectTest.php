<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\Core\Url;
use Drupal\path_alias\Entity\PathAlias;
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
    \Drupal::service('kernel')->rebuildContainer();
  }

  private function getMenuTree(string $langcode) : array {
    return $this->getMenuTreeBuilder()->build('main', $langcode, (object) [
      'name' => 'Test',
      'url' => new Url('<front>', options: [
        'language' => $this->languageManager()->getLanguage($langcode),
      ]),
      'id' => 'liikenne',
    ]);
  }

  /**
   * Tests that front page is redirected correctly.
   */
  public function testFrontPageRedirect() : void {
    $this->createLinks();

    $redirect = Redirect::create();
    $redirect->setLanguage('en');
    $redirect->setSource('/front');
    $redirect->setRedirect('/en/front-page-redirect');
    $redirect->save();

    $tree = $this->getMenuTree('en');
    $this->assertTrue(str_ends_with($tree['url'], '/en/front-page-redirect'));
  }

  public function testRegularRedirect() : void {
    $this->createLinks();

    /*$redirect = Redirect::create();
    $redirect->setLanguage('en');
    $redirect->setSource('node-test');
    $redirect->setRedirect('internal:/en/english-redirect');
    $redirect->save();*/

    $url = Url::fromRoute('<front>');

    $tree = $this->getMenuTree('en');
    $aliases = PathAlias::loadMultiple();
    $this->assertTrue(str_ends_with($tree['sub_tree'][0]->url, '/en/english-redirect'));

    $tree = $this->getMenuTree('fi');
    $this->assertTrue(str_ends_with($tree['sub_tree'][0]->url, '/fi/test'));
  }

}
