<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\Functional;

use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests menu blocks.
 *
 * @group helfi_navigation
 */
class MenuBlockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
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

    foreach (['fi', 'sv'] as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => 'en', 'fi' => 'fi', 'sv' => 'sv'])
      ->save();

    $config = $this->config('helfi_api_base.environment_resolver.settings');
    $config->set(EnvironmentResolver::ENVIRONMENT_NAME_KEY, 'local');
    $config->set(EnvironmentResolver::PROJECT_NAME_KEY, Project::ASUMINEN);
    $config->save();
  }

  /**
   * Make sure menu block can be placed.
   */
  public function testExternalMenuBlock() : void {
    // @todo Test that api addresses are set in drupalSettings.
    _helfi_navigation_generate_blocks('stark', 'content', TRUE);

    // Verify that:
    // 1. Mega menu has only two levels of links.
    // 2. Block label is translated when a translation is provided.
    // 3. Link are translated.
    // These blocks and their content will be generated from fixtures/*.json
    // files.
    $expected = [
      'fi' => [
        'menus' => [
          'External - Mega menu' => [
            'Kaupunkiympäristö ja liikenne',
            'Pysäköinti',
          ],
          'External - Footer bottom navigation' => [
            'Saavutettavuusseloste',
          ],
          'External - Header language links' => [
            'Selkokieli',
          ],
          'External - Header top navigation' => [
            'Uutiset',
          ],
          'Helsingin kaupunki' => [
            'Työpaikat',
          ],
          'Ota yhteyttä' => [
            'Kaupunkineuvonta – Helsinki-info',
          ],
        ],
      ],
      'sv' => [
        'menus' => [
          'External - Footer bottom navigation' => [
            'Tillgänglighetsutlåtande',
          ],
          'External - Header language links' => [
            'Lättläst språk',
          ],
          'External - Header top navigation' => [
            'Nyheter',
          ],
          'External - Mega menu' => [
            'Stadsmiljö ock trafik',
            'Parkering',
          ],
          'Helsingfors stad' => [
            'Lediga jobb',
          ],
          'Ta kontakt' => [
            'Ge respons',
          ],
        ],
      ],
      'en' => [
        'menus' => [
          'External - Footer bottom navigation' => [
            'Accessibility statement',
          ],
          'External - Header language links' => [
            'Selkokieli',
          ],
          'External - Header top navigation' => [
            'News',
          ],
          'External - Mega menu' => [
            'Urban environment and traffic',
            'Parking',
          ],
          'City of Helsinki' => [
            'Employment opportunities',
          ],
          'Connect' => [
            'Feedback',
          ],
        ],
      ],
    ];

    foreach (['en', 'sv', 'fi'] as $language) {
      $this->drupalGet('/' . $language);

      ['menus' => $menus] = $expected[$language];

      foreach ($menus as $label => $links) {
        $this->assertSession()->pageTextContains($label);
        foreach ($links as $link) {
          $this->assertSession()->linkExistsExact($link);
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

}
