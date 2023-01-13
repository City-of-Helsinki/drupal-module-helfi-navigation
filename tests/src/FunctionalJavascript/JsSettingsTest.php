<?php

declare(strict_types = 1);

namespace Drupal\Tests\helfi_navigation\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests drupal settings.
 *
 * @group helfi_navigation
 */
class JsSettingsTest extends WebDriverTestBase {

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
    $config = $this->config('helfi_api_base.environment_resolver.settings');
    $config->set(EnvironmentResolver::ENVIRONMENT_NAME_KEY, 'local');
    $config->set(EnvironmentResolver::PROJECT_NAME_KEY, Project::ASUMINEN);
    $config->save();

    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => 'en', 'fi' => 'fi', 'sv' => 'sv'])
      ->save();
  }

  /**
   * Tests drupalSettings.
   */
  public function testSettings() : void {
    foreach (['en', 'fi', 'sv'] as $language) {
      $this->drupalGet('/' . $language);

      $settings = $this->getDrupalSettings();
      $this->assertEquals("https://helfi-asuminen.docker.so/$language/housing/api/v1/global-mobile-menu", $settings['helfi_navigation']['links']['api']);
      $this->assertEquals("https://helfi-etusivu.docker.so/$language", $settings['helfi_navigation']['links']['canonical']);
    }
  }

}
