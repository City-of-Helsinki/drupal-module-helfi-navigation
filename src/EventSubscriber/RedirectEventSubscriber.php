<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\helfi_navigation\Event\MenuTreeBuilderLink;
use Drupal\redirect\Entity\Redirect;
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
   *   The redirect repository service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   */
  public function __construct(
    private RedirectRepository $repository,
    private ConfigFactoryInterface $configFactory,
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
    $url = clone $event->url;
    $path = $url->toString(TRUE)
      ->getGeneratedUrl();

    // Skip external and empty URLs.
    if ($url->isExternal() || $path === '') {
      return;
    }

    $candidates = [
      $path,
    ];
    // Front page requires special handling because the path is removed from
    // URL generation. For example if the front page is set to '/front', the
    // URL is then normalized to '/{langcode}', '/', or in case helfi_proxy is
    // enabled, to '/{langcode}/{proxy_prefix}'.
    if ($url->isRouted() && $url->getRouteName() === '<front>') {
      $page = $this->configFactory->get('system.site')
        ->get('page');
      if (isset($page['front'])) {
        $candidates[] = $page['front'];
      }
    }

    foreach ($candidates as $candidate) {
      if ($redirect = $this->loadRedirects($candidate, $event->language)) {
        $event->url = $redirect->getRedirectUrl();
        break;
      }
    }
  }

  /**
   * Loads redirect for given path.
   *
   * @param string $path
   *   The path.
   * @param string $language
   *   The language.
   *
   * @return \Drupal\redirect\Entity\Redirect|null
   *   The redirect or null.
   */
  private function loadRedirects(string $path, string $language) : ? Redirect {
    if (str_starts_with($path, '/' . $language)) {
      $path = str_replace('/' . $language, '', $path);
    }
    return $this->repository->findMatchingRedirect($path, language: $language);
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
