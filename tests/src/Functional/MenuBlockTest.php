<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Functional;

use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;
use Drupal\Tests\helfi_navigation\Traits\MenuLinkTrait;

/**
 * Tests menu blocks.
 *
 * @group helfi_navigation
 */
class MenuBlockTest extends BrowserTestBase {

  use EnvironmentResolverTrait;
  use MenuLinkTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
    'node',
    'menu_ui',
    'path',
    'path_alias',
    'helfi_api_base',
    'block',
    'helfi_navigation',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();

    foreach (['fi', 'sv', 'es'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
    $this->config('language.negotiation')
      ->set('url.prefixes', [
        'en' => 'en',
        'fi' => 'fi',
        'sv' => 'sv',
        'es' => 'es',
      ])->save();

    $this->config('helfi_navigation.settings')
      ->set(['global_navigation_enabled' => TRUE])
      ->save();

    NodeType::create([
      'type' => 'page',
    ])->save();

    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);

    _helfi_navigation_generate_blocks('stark', 'content', TRUE);
    $this->setContainerParameter('helfi_navigation.request_timeout', 1);
  }

  /**
   * Make sure menu block can be placed.
   */
  public function testExternalMenuBlock() : void {
    $this->createLinks();
    // Verify that:
    // 1. Mega menu has only two levels of links.
    // 2. Block label is translated when a translation is provided.
    // 3. Link are translated.
    // Also verify exception for non-primary languages:
    // 4. On languages other than fi, sv, en the external menus use en versions.
    // These blocks and their content will be generated from fixtures/*.json
    // files.
    $expected = [
      'fi' => [
        'menus' => [
          'External - Mega menu' => [
            'Kaupunkiympäristö ja liikenne' => [],
            'Pysäköinti' => ['lang' => 'fi-FI'],
          ],
          'External - Footer bottom navigation' => [
            'Saavutettavuusseloste' => [],
          ],
          'External - Header language links' => [
            'Selkokieli' => [],
          ],
          'External - Header top navigation' => [
            'Uutiset' => [],
          ],
          'Helsingin kaupunki' => [
            'Työpaikat' => ['lang' => 'fi-FI'],
          ],
          'Ota yhteyttä' => [
            'Kaupunkineuvonta – Helsinki-info' => [],
          ],
        ],
      ],
      'sv' => [
        'menus' => [
          'External - Footer bottom navigation' => [
            'Tillgänglighetsutlåtande' => [],
          ],
          'External - Header language links' => [
            'Lättläst språk' => [],
          ],
          'External - Header top navigation' => [
            'Nyheter' => [],
          ],
          'External - Mega menu' => [
            'Stadsmiljö ock trafik' => [],
            'Parkering' => ['lang' => 'sv-SV'],
          ],
          'Helsingfors stad' => [
            'Lediga jobb' => ['lang' => 'sv-SV'],
          ],
          'Ta kontakt' => [
            'Ge respons' => [],
          ],
        ],
      ],
      'en' => [
        'menus' => [
          'External - Footer bottom navigation' => [
            'Accessibility statement' => [],
          ],
          'External - Header language links' => [
            'Selkokieli' => [],
          ],
          'External - Header top navigation' => [
            'News' => [],
          ],
          'External - Mega menu' => [
            'Urban environment and traffic' => [],
            'Parking' => ['lang' => 'en-GB'],
          ],
          'City of Helsinki' => [
            'Employment opportunities' => ['lang' => 'en-GB'],
          ],
          'Connect' => [
            'Feedback' => [],
          ],
        ],
      ],
      'es' => [
        'menus' => [
          'External - Footer bottom navigation' => [
            'Accessibility statement' => [],
          ],
          'External - Header language links' => [
            'Selkokieli' => [],
          ],
          'External - Header top navigation' => [
            'News' => [],
          ],
          'External - Mega menu' => [
            'Urban environment and traffic' => [],
            'Parking' => ['lang' => 'en-GB'],
          ],
          'City of Helsinki' => [
            'Employment opportunities' => ['lang' => 'en-GB'],
          ],
          'Connect' => [
            'Feedback' => [],
          ],
        ],
      ],
    ];

    foreach (['en', 'sv', 'fi', 'es'] as $language) {
      $this->drupalGet('/' . $language);

      ['menus' => $menus] = $expected[$language];

      foreach ($menus as $label => $links) {
        $this->assertSession()->pageTextContains($label);
        foreach ($links as $link => $attributes) {
          $this->assertSession()->linkExistsExact($link);
          $item = $this->getSession()->getPage()->findLink($link);

          // Test lang attributes.
          foreach ($attributes as $key => $value) {
            $this->assertEquals($value, $item->getAttribute($key));
          }
        }
      }
      // Make sure mega menu has only two levels of links since it's configured
      // to only show up to two levels.
      $elements = $this->getSession()->getPage()->findAll('css', '#block-external-menu-mega-menu ul ul');
      $this->assertTrue(count($elements) > 1);
      $elements = $this->getSession()->getPage()->findAll('css', '#block-external-menu-mega-menu ul ul ul');
      $this->assertCount(0, $elements);
    }
  }

  /**
   * Tests active trail.
   */
  public function testActiveTrail() : void {
    $content = [
      '/urban-environment-and-traffic/urban-environment-and-traffic' =>
        [
          'title' => 'Urban environment and traffic',
          'active_trail' => [
            'https://helfi-kymp.docker.so/en/urban-environment-and-traffic/urban-environment-and-traffic',
          ],
          'aria_current' => [
            'https://helfi-kymp.docker.so/en/urban-environment-and-traffic/urban-environment-and-traffic',
          ],
        ],
      '/urban-environment-and-traffic/parking' => [
        'title' => 'Parking',
        'active_trail' => [
          'https://helfi-kymp.docker.so/en/urban-environment-and-traffic/urban-environment-and-traffic',
          'https://helfi-kymp.docker.so/en/urban-environment-and-traffic/parking',
        ],
        'aria_current' => [
          'https://helfi-kymp.docker.so/en/urban-environment-and-traffic/parking',
        ],
      ],
      '/urban-environment-and-traffic/parking/parking-areas-prices-and-payment-methods' => [
        'title' => 'Parking areas, prices and payment methods',
        // Third level is not visible in menu block, make sure two parents are
        // set in active trail.
        'active_trail' => [
          'https://helfi-kymp.docker.so/en/urban-environment-and-traffic/urban-environment-and-traffic',
          'https://helfi-kymp.docker.so/en/urban-environment-and-traffic/parking',
        ],
        'aria_current' => [],
      ],
      // Test different second level link to make sure active trail is not
      // cached for wrong language.
      '/urban-environment-and-traffic/cycling' => [
        'title' => 'Cycling',
        'active_trail' => [
          'https://helfi-kymp.docker.so/en/urban-environment-and-traffic/urban-environment-and-traffic',
          'https://helfi-kymp.docker.so/en/urban-environment-and-traffic/cycling',
        ],
        'aria_current' => [
          'https://helfi-kymp.docker.so/en/urban-environment-and-traffic/cycling',
        ],
      ],
    ];

    foreach ($content as $path => $data) {
      $node = Node::create([
        'title' => $data['title'],
        'path' => $path,
        'type' => 'page',
      ]);
      $node->save();
    }

    foreach ($content as $path => $data) {
      $this->drupalGet('/en' . $path);
      $this->assertActiveTrail($data['active_trail'], $data['aria_current']);
    }
  }

  /**
   * Asserts that expected links are in active trail.
   *
   * @param array $expectedActiveTrail
   *   An array of expected URLs in active trail.
   * @param array $expectedAriaCurrent
   *   An array of expected URLs with aria-current attribute.
   */
  private function assertActiveTrail(array $expectedActiveTrail, array $expectedAriaCurrent) : void {
    $items = $this->getSession()->getPage()->findAll('css', 'a.menu__link--in-path');

    $this->assertCount(count($expectedActiveTrail), $items);
    foreach ($items as $item) {
      $this->assertTrue(in_array($item->getAttribute('href'), $expectedActiveTrail));
    }

    $ariaCurrent = $this->getSession()->getPage()->findAll('css', '[aria-current="page"]');
    $this->assertCount(count($expectedAriaCurrent), $ariaCurrent);

    foreach ($ariaCurrent as $item) {
      $this->assertTrue(in_array($item->getAttribute('href'), $expectedAriaCurrent));
    }
  }

}
