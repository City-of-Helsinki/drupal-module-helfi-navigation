<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\EventSubscriber;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\helfi_navigation\Event\MenuTreeBuilderLink;
use Drupal\redirect\RedirectRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters the menu tree builder links to include redirect URL.
 */
final class RedirectEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\redirect\RedirectRepository $repository
   *   The redirect repository.
   */
  public function __construct(
    private RedirectRepository $repository,
    private OutboundPathProcessorInterface $pathProcessor
  ) {
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
  public function updateLink(MenuTreeBuilderLink $event) : void {
    $path = $event->url->setAbsolute(FALSE)->toString();

    $options = ['prefix' => ''];
    $p = $this->pathProcessor->processOutbound($path, $options);

    $prefix = '/' . rtrim($options['prefix'], '/');

    if ($path !== $prefix) {
      return;
    }
    $x = 1;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() : array {
    return [
      MenuTreeBuilderLink::class => ['updateLink'],
    ];
  }

}
