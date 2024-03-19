<?php

declare(strict_types=1);

namespace Drupal\helfi_navigation\EventSubscriber;

use Drupal\helfi_api_base\Environment\EnvironmentResolverInterface;
use Drupal\helfi_api_base\Environment\Project;
use Drupal\helfi_navigation\Event\MenuTreeBuilderLink;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Evaluates whether the global menu links can omit the domain.
 */
final class AbsoluteUrlMenuTreeBuilderLinkSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new instance.
   *
   * @param \Drupal\helfi_api_base\Environment\EnvironmentResolverInterface $environmentResolver
   *   The environment resolver.
   * @param bool|null $mustBeAbsoluteUrl
   *   Determines whether the link must be absolute URL.
   */
  public function __construct(
    private readonly EnvironmentResolverInterface $environmentResolver,
    private ?bool $mustBeAbsoluteUrl = NULL,
  ) {
  }

  /**
   * Evaluates whether the links can omit the domain.
   *
   * @return bool
   *   TRUE if the link must be absolute URL.
   */
  private function mustBeAbsolute() : bool {
    // No need to re-evaluate this since this is not link specific.
    if ($this->mustBeAbsoluteUrl !== NULL) {
      return $this->mustBeAbsoluteUrl;
    }

    try {
      $activeEnvironment = $this->environmentResolver->getActiveEnvironment();
      $matchingEnvironment = $this->environmentResolver->getProject(Project::ETUSIVU)
        ->getEnvironment($activeEnvironment->getEnvironmentName());

      // By default, links are either absolute (external) or not. This
      // should already be evaluated by MenuTreeBuilder service, so the
      // only thing left for us to do here is to determine whether the
      // Etusivu's domain matches instance's current domain.
      return $this->mustBeAbsoluteUrl = $matchingEnvironment->getBaseUrl() !== $activeEnvironment->getBaseUrl();
    }
    catch (\InvalidArgumentException) {
    }
    return FALSE;
  }

  /**
   * Responds to MenuTreeBuilderLink event.
   *
   * @param \Drupal\helfi_navigation\Event\MenuTreeBuilderLink $link
   *   The event to respond to.
   */
  public function updateLink(MenuTreeBuilderLink $link) : void {
    if (!$this->mustBeAbsolute()) {
      return;
    }
    $link->url->setAbsolute(TRUE);
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
