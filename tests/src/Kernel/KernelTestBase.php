<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\KernelTests\KernelTestBase as CoreKernelTestBase;
use Drupal\Tests\helfi_api_base\Traits\ApiTestTrait;
use Drupal\Tests\helfi_api_base\Traits\LanguageManagerTrait;

/**
 * A base test class for all Kernel tests.
 */
abstract class KernelTestBase extends CoreKernelTestBase {

  use LanguageManagerTrait;
  use ApiTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'link',
    'user',
    'content_translation',
    'menu_link_content',
    'helfi_api_base',
    'language',
    'helfi_language_negotiator_test',
    'helfi_navigation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() : void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('menu_link_content');
    $this->installConfig(['system']);
    $this->setupLanguages();
  }

}
