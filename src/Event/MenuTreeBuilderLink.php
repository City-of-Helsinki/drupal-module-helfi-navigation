<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\Core\Url;

/**
 * Event class to alter menu tree builder links.
 */
final class MenuTreeBuilderLink extends Event {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\Core\Url $url
   *   The url.
   * @param string $language
   *   The language.
   * @param array $item
   *   The data.
   */
  public function __construct(
    public Url $url,
    public string $language,
    public array $item,
  ) {
  }

}
