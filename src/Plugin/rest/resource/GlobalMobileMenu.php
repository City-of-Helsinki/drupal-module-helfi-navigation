<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\rest\resource;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
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
  private ApiManager $apiManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private LanguageManagerInterface $languageManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private ConfigFactory $configFactory;

  /**
   * The environment resolver service.
   *
   * @var \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface
   */
  private EnvironmentResolverInterface $environmentResolver;

  /**
   * The menu manager service.
   *
   * @var \Drupal\helfi_navigation\MainMenuManager
   */
  private MainMenuManager $mainMenuManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    $instance->languageManager = $container->get('language_manager');
    $instance->apiManager = $container->get(ApiManager::class);
    $instance->environmentResolver = $container->get('helfi_api_base.environment_resolver');
    $instance->mainMenuManager = $container->get(MainMenuManager::class);

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
    $projectName = $this->environmentResolver
      ->getActiveProject()
      ->getName();
    $site_name = $this->configFactory->get('system.site')->get('name');
    $site_data = $this->createLocalMenuData($site_name, $projectName, $langcode);

    // Handle non-core sites that only need local menu.
    if ($this->excludeGlobalNavigationMenuItems()) {
      return $this->toResourceResponse(
        $this->normalizeResponseData([$projectName => $site_data])
      );
    }

    try {
      // Fetch the main menu from Etusivu instance.
      $apiResponse = $this->apiManager->get($langcode, 'main', []);
    }
    catch (\Exception $e) {
      return new ResourceResponse([], 404);
    }

    // If authorization key is set, just return the menu without enrichment.
    // This is used to so instances that are not a part of the
    // "global navigation" to show their own main menu in mobile
    // navigation, namely Rekry.
    // @see https://helsinkisolutionoffice.atlassian.net/browse/UHF-7607
    if (
      !$this->apiManager->isManuallyDisabled() &&
      $this->apiManager->hasAuthorization()
    ) {
      return $this->toResourceResponse(
        $this->normalizeResponseData($apiResponse->data)
      );
    }

    // Add local menu to the api response.
    $apiResponse->data->{$projectName} = $site_data;

    return $this->toResourceResponse(
      $this->normalizeResponseData($apiResponse->data)
    );
  }

  /**
   * Constructs a new resource response with required cache dependencies.
   *
   * @param array $data
   *   The response data.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The resource response.
   */
  private function toResourceResponse(array $data) : ResourceResponse {
    $response = new ResourceResponse($data, 200);
    $response->getCacheableMetadata()
      ->addCacheTags(['config:system.menu.main']);
    return $response;
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

  /**
   * Create the local menu data structure that matches the global navigation.
   *
   * @param string $siteName
   *   The site name.
   * @param string $projectName
   *   The project name.
   * @param string $langcode
   *   The langcode.
   *
   * @return array
   *   Local menu data.
   */
  private function createLocalMenuData(string $siteName, string $projectName, string $langcode) : array {
    // Create menu tree and add data to the local menu.
    $menuTree = $this->mainMenuManager->build($langcode);
    // This is used by Mobile navigation javascript to
    // figure out if special handling is needed.
    $menuTree['is_injected'] = TRUE;
    $menuTree['no_global_navigation'] = $this->apiManager->isManuallyDisabled();

    return [
      'langcode' => [['value' => $langcode]],
      'menu_tree' => [0 => $menuTree],
      'name' => [['value' => $siteName]],
      'project' => [['value' => $projectName]],
      'status' => [['value' => TRUE]],
      'uuid' => [['value' => $this->configFactory->get('system.site')->get('uuid')]],
      'weight' => [['value' => 0]],
    ];
  }

  /**
   * Check if global navigation items should be excluded from mobile-nav.
   *
   * @return bool
   *   Exclude global navigation items.
   */
  private function excludeGlobalNavigationMenuItems() : bool {
    return $this->apiManager->isManuallyDisabled() &&
      !$this->apiManager->hasAuthorization();
  }

}
