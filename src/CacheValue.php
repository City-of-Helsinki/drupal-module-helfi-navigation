<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

/**
 * A value object to store cache data.
 */
final class CacheValue {

  public const TTL = 180;

  /**
   * Constructs a new instance.
   *
   * @param object $value
   *   The cache data.
   * @param int $created
   *   The created date.
   * @param array $tags
   *   The cache tags.
   */
  public function __construct(
    public object $value,
    public int $created,
    public array $tags,
  ) {
  }

  /**
   * Checks if cache has expired.
   *
   * @param int $currentTime
   *   The current (unix) timestamp.
   *
   * @return bool
   *   TRUE if cache has expired.
   */
  public function hasExpired(int $currentTime) : bool {
    return $currentTime > ($this->created + self::TTL);
  }

}
