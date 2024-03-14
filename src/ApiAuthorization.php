<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_api_base\Vault\VaultManager;

/**
 * A BC layer to handle API authorization.
 */
final class ApiAuthorization {

  public const VAULT_MANAGER_KEY = 'helfi_navigation';

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\helfi_api_base\Vault\VaultManager $vaultManager
   *   The vault manager service.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly VaultManager $vaultManager,
  ) {
  }

  /**
   * Gets the authorization token.
   *
   * @return string|null
   *   The authorization token.
   */
  public function getAuthorization() : ?string {
    if ($authorization = $this->vaultManager->get(self::VAULT_MANAGER_KEY)) {
      return $authorization->data();
    }

    // Provide a BC layer to fetch API keys from previously used
    // configuration.
    // @todo remove this once all projects have migrated to Vault.
    return $this->configFactory->get('helfi_navigation.api')
      ?->get('key');
  }

}
