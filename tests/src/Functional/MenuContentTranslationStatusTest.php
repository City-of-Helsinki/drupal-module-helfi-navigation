<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests menu translation on node pages.
 *
 * @group helfi_navigation
 */
class MenuContentTranslationStatusTest extends BrowserTestBase {

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
    'block',
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
    $this->config('language.negotiation')
      ->set('url.prefixes', ['en' => 'en', 'fi' => 'fi', 'sv' => 'sv'])
      ->save();

    NodeType::create([
      'type' => 'page',
    ])->save();

    $this->placeBlock('local_tasks_block');

    /** @var \Drupal\content_translation\ContentTranslationManagerInterface $translationManager */
    $translationManager = $this->container->get('content_translation.manager');
    $translationManager->setEnabled('node', 'page', TRUE);
    $translationManager->setEnabled('menu_link_content', 'menu_link_content', TRUE);
  }

  /**
   * Make sure menu visibility can be changed on node edit page.
   */
  public function testNodeEditTranslationStatus() : void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/en/node/add/page');
    $this->submitForm([
      'title[0][value]' => 'Title en',
      'menu[enabled]' => 1,
      'menu[title]' => 'Link title en',
      'menu[content_translation_status]' => 1,
    ], 'Save');

    $this->getSession()->getPage()->clickLink('Edit');
    $this->assertSession()->fieldValueEquals('menu[title]', 'Link title en');
    $this->assertSession()->fieldValueEquals('menu[content_translation_status]', '1');

    // Make sure we can translate the menu link.
    $this->getSession()->getPage()->clickLink('Translate');
    $this->getSession()->getPage()->find('css', 'a[href="/fi/node/1/translations/add/en/fi"]')->click();

    $this->submitForm([
      'title[0][value]' => 'Title fi',
      'menu[enabled]' => 1,
      'menu[title]' => 'Link title fi',
      'menu[content_translation_status]' => 1,
    ], 'Save (this translation)');

    $this->getSession()->getPage()->clickLink('Edit');
    $this->assertStringStartsWith('/fi', parse_url($this->getSession()->getCurrentUrl(), PHP_URL_PATH));
    $this->assertSession()->fieldValueEquals('menu[title]', 'Link title fi');
    $this->assertSession()->fieldValueEquals('menu[content_translation_status]', '1');

    // Make sure we can unpublish finnish translation.
    $this->submitForm([
      'menu[content_translation_status]' => 0,
    ], 'Save (this translation)');

    $this->getSession()->getPage()->clickLink('Edit');
    $this->assertSession()->fieldValueEquals('menu[content_translation_status]', '');

    // Make sure english translation is still published.
    $this->drupalGet('/en/node/1/edit');
    $this->assertSession()->fieldValueEquals('menu[content_translation_status]', '1');
    $this->submitForm([
      'menu[content_translation_status]' => 0,
    ], 'Save');

    // Make sure we can unpublish default translation too.
    $this->getSession()->getPage()->clickLink('Edit');
    $this->assertSession()->fieldValueEquals('menu[content_translation_status]', '');
  }

}
