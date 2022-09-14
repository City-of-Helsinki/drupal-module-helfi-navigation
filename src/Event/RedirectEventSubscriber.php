<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\Event;

use Drupal\redirect\RedirectRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters the menu tree builder links to include redirect URL.
 */
final class RedirectEventSubscriber implements EventSubscriberInterface {

  /**
   * The redirect repository.
   *
   * @var \Drupal\redirect\RedirectRepository|null
   */
  private ?RedirectRepository $redirectRepository;

  /**
   * Sets th redirect repository if available.
   *
   * @param \Drupal\redirect\RedirectRepository $redirectRepository
   *   The redirect repository.
   */
  public function setRedirectRepository(RedirectRepository $redirectRepository) : void {
    $this->redirectRepository = $redirectRepository;
  }

  /**
   * Responds to MenuTreeBuilderLink event.
   *
   * Gets all available redirects for given link and updates the URL
   * to use the redirect destination.
   *
   * This is required by javascript navigation to build the active trail.
   *
   * @param \Drupal\helfi_navigation\Event\MenuTreeBuilderLink $event
   *   The event to respond to.
   */
  private function updateLink(MenuTreeBuilderLink $event) : void {
    if (!$this->redirectRepository) {
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      MenuTreeBuilderLink::class => [
        ['updateLink'],
      ],
    ];
  }

}
