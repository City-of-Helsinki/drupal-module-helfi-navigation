<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\helfi_api_base\Environment\EnvironmentEnum;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\helfi_api_base\Traits\EnvironmentResolverTrait;

/**
 * Tests drupal settings.
 *
 * @group helfi_navigation
 */
class JsSettingsTest extends WebDriverTestBase {

  use EnvironmentResolverTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'helfi_api_base',
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
    $this->setActiveProject(Project::ASUMINEN, EnvironmentEnum::Local);

    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => 'en', 'fi' => 'fi', 'sv' => 'sv'])
      ->save();
  }

  /**
   * Tests drupalSettings.
   */
  public function testSettings() : void {
    $suffix = [
      'fi' => 'asuminen',
      'en' => 'housing',
      'sv' => 'boende',
    ];

    foreach (['en', 'fi', 'sv'] as $language) {
      $this->drupalGet('/' . $language);

      $currentSuffix = $suffix[$language];

      $settings = $this->getDrupalSettings();
      $this->assertEquals("https://helfi-asuminen.docker.so/$language/$currentSuffix/api/v1/global-mobile-menu", $settings['helfi_navigation']['links']['api']);
      $this->assertEquals("https://helfi-etusivu.docker.so/$language", $settings['helfi_navigation']['links']['canonical']);
    }
  }

}
