<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_displays;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Service provider class for the module.
 */
class OeListPagesLinkListDisplaysServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->hasDefinition('oe_list_pages.builder')) {
      // Switch out the ListBuilder class with our own.
      $definition = $container->getDefinition('oe_list_pages.builder');
      $definition->setClass(ListBuilder::class);
      $definition->addArgument(new Reference('event_dispatcher'));
      $definition->addArgument(new Reference('plugin.manager.oe_link_lists.link_display'));
    }
  }

}
