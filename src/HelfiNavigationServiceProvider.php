<?php

declare(strict_types = 1);

namespace Drupal\helfi_navigation;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\helfi_navigation\EventSubscriber\RedirectEventSubscriber;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers services for non-required modules.
 */
class HelfiNavigationServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    // We cannot use the module handler as the container is not yet compiled.
    // @see \Drupal\Core\DrupalKernel::compileContainer()
    $modules = $container->getParameter('container.modules');

    if (isset($modules['redirect'])) {
      $container->register('helfi_navigation.redirect_subscriber', RedirectEventSubscriber::class)
        ->addTag('event_subscriber')
        ->addArgument(new Reference('redirect.repository'))
        ->addArgument(new Reference('path_processor_manager'));
    }
  }

}
