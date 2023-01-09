<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Plugin\rest\resource;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManager;
use Drupal\helfi_navigation\ApiManager;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents Global menu records as resources.
 *
 * @RestResource(
 *   id = "helfi_global_mobile_menu",
 *   label = @Translation("Mobile global menu"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/global-mobile-menu",
 *   }
 * )
 */
final class GlobalMobileMenu extends ResourceBase
{

  protected ApiManager $apiManager;

  protected LanguageManager $languageManager;

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
    $instance->languageManager = $container->get('language_manager');
    $instance->apiManager = $container->get('helfi_navigation.api_manager');

    return $instance;
  }

  /**
   * Callback for GET requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The response.
   */
  public function get(): ModifiedResourceResponse
  {
    $langcode = $this->languageManager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();
    /* * @var ApiResponse $apiResponse */
    // $apiResponse = $this->apiManager->get($langId, 'main', []);

    try {
      $apiResponse =  $this->apiManager->get($langcode, 'main', []);
    }
    catch(\Exception $exception) {
      // catch
    }

    // rikasta

    return new ModifiedResourceResponse($apiResponse);
  }

}
