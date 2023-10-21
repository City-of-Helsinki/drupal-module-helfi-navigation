<?php

declare(strict_types=1);

namespace Drupal\Tests\helfi_navigation\Kernel;

use Drupal\Core\Url;
use Drupal\helfi_navigation\Menu\MenuTreeBuilder;
use Drupal\Tests\helfi_navigation\Traits\MenuLinkTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * A base class for menu tree builder tests.
 */
abstract class MenuTreeBuilderTestBase extends KernelTestBase {

  use MenuLinkTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);

    // We have a dependency to anonymous user when checking menu permissions
    // and might run into 'entity:user' context is required error when trying
    // to generate an entity link.
    User::create([
      'name' => '',
      'uid' => 0,
    ])->save();

    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();
  }

  /**
   * Gets the menu tree builder.
   *
   * @return \Drupal\helfi_navigation\Menu\MenuTreeBuilder
   *   The menu tree builder service.
   */
  protected function getMenuTreeBuilder() : MenuTreeBuilder {
    return $this->container->get('helfi_navigation.menu_tree_builder');
  }

  /**
   * Gets the menu tree in given language.
   *
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   The menu tree.
   */
  protected function getMenuTree(string $langcode) : array {
    $this->setOverrideLanguageCode($langcode);

    return $this->getMenuTreeBuilder()->build('main', $langcode, (object) [
      'name' => 'Test',
      'url' => new Url('<front>', options: [
        'language' => $this->languageManager()->getLanguage($langcode),
      ]),
      'id' => 'liikenne',
    ]);
  }

}
