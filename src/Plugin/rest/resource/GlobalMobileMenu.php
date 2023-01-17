<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\rest\resource;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolver;
use Drupal\helfi_navigation\ApiManager;
use Drupal\helfi_navigation\MainMenuManager;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents Global menu records as resources.
 *
 * @RestResource(
 *   id = "helfi_global_mobile_menu",
 *   label = @Translation("Global mobile menu"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/global-mobile-menu",
 *   }
 * )
 */
final class GlobalMobileMenu extends ResourceBase {

  /**
   * The global navigation service.
   *
   * @var \Drupal\helfi_navigation\ApiManager
   */
  protected ApiManager $apiManager;

  /**
   * The language manager service..
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected ConfigFactory $configFactory;

  /**
   * The environment resolver service.
   *
   * @var \Drupal\helfi_api_base\Environment\EnvironmentResolver
   */
  protected EnvironmentResolver $environmentResolver;

  /**
   * The menu manager service.
   *
   * @var Drupal\helfi_navigation\MainMenuManager
   */
  protected MainMenuManager $menuManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    $instance = parent::create(
      $container,
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->configFactory = $container->get('config.factory');
    $instance->languageManager = $container->get('language_manager');
    $instance->apiManager = $container->get('helfi_navigation.api_manager');
    $instance->environmentResolver = $container->get('helfi_api_base.environment_resolver');
    $instance->menuManager = $container->get('helfi_navigation.menu_manager');

    return $instance;
  }

  /**
   * Callback for GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response.
   */
  public function get(): ResourceResponse {
    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();

    try {
      $apiResponse = $this->apiManager->get($langcode, 'main', []);
    }
    catch (\Exception) {
      return new ResourceResponse([], 404);
    }

    // If authorization key is set, just return the menu without enrichment.
    if ($this->apiManager->isAuthorized()) {
      return new ResourceResponse($this->normalizeResponseData($apiResponse->data), 200);
    }

    $environment = $this->environmentResolver->getActiveEnvironment();
    $site_name = $this->configFactory->get('system.site')->get('name');
    $project_name = $environment->getId();

    // Create menu tree and add data to the local menu.
    $menuTree = $this->menuManager->build();
    $menuTree['is_injected'] = TRUE;

    // Commented lines are present in the api request,
    // most likely generated by the drupal api module.
    $site_data = [
      // 'content_translation_changed' => '',
      // 'content_translation_created' => '',
      // 'content_translation_created' => '',
      // 'content_translation_source' => '',
      // 'content_translation_uid' => '',
      // 'metatag' => '',
      // 'default_langcode' => [(object) ['value' => TRUE]],
      'langcode' => [['value' => $langcode]],
      'menu_tree' => [0 => $menuTree],
      'name' => [['value' => $site_name]],
      'project' => [['value' => $project_name]],
      'status' => [['value' => TRUE]],
      'uuid' => [['value' => $this->configFactory->get('system.site')->get('uuid')]],
      'weight' => [['value' => 0]],
    ];

    // Add local menu to the api response.
    $apiResponse->data->{$project_name} = $site_data;

    return new ResourceResponse($this->normalizeResponseData($apiResponse->data), 200);
  }

  /**
   * Normalizes response data.
   *
   * @param array|object $data
   *   Data to normalize.
   *
   * @return array
   *   Normalized data array.
   */
  private function normalizeResponseData(array|object $data): array {
    return json_decode(json_encode($data), TRUE);
  }

}
