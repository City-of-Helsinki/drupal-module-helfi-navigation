<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Plugin\DebugDataItem;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\helfi_api_base\Attribute\DebugDataItem;
use Drupal\helfi_api_base\Debug\SupportsValidityChecksInterface;
use Drupal\helfi_api_base\DebugDataItemPluginBase;
use Drupal\helfi_navigation\ApiManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Debug data client for Global navigation API connection.
 *
 * This is used to ensure the current instance has access to Global
 * navigation API.
 */
#[DebugDataItem(
  id: 'global_navigation_api',
  title: new TranslatableMarkup('Global navigation API'),
)]
final class ApiAvailability extends DebugDataItemPluginBase implements SupportsValidityChecksInterface, ContainerFactoryPluginInterface {

  /**
   * The API manager service.
   *
   * @var \Drupal\helfi_navigation\ApiManager
   */
  private ApiManager $apiManager;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): self {
    $instance = new self($configuration, $plugin_id, $plugin_definition);
    $instance->apiManager = $container->get(ApiManager::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function check(): bool {
    return $this->apiManager->ping();
  }

}
