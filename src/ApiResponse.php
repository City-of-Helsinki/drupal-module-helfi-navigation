<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

/**
 * A value object to store API responses.
 */
final class ApiResponse {

  /**
   * Constructs a new instance.
   *
   * @param array|object $data
   *   The response.
   */
  public function __construct(
    public array|object $data
  ) {
  }

}
