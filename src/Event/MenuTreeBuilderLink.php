<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Event class to alter menu tree builder links.
 */
final class MenuTreeBuilderLink extends Event {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\menu_link_content\MenuLinkContentInterface $link
   *   The menu link.
   * @param array $item
   *   The data.
   */
  public function __construct(
    public MenuLinkContentInterface $link,
    public array $item
  ) {
  }

}
