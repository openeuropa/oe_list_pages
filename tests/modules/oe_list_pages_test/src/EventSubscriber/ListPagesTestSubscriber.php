<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_test\EventSubscriber;

use Drupal\Core\Config\ConfigFactory;
use Drupal\oe_list_pages\ListPageEvents;
use Drupal\oe_list_pages\ListPageSourceRetrieveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * OpenEuropa List Pages test event subscriber.
 */
class ListPagesTestSubscriber implements EventSubscriberInterface {

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\Config
   */
  private $config;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The Config Factory.
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->config = $config_factory->get('oe_list_pages_test.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ListPageEvents::ALTER_ALLOWED_ENTITY_TYPES => ['onEntityTypesRetrieving'],
      ListPageEvents::ALTER_ALLOWED_BUNDLES => ['onBundlesRetrieving'],
    ];
  }

  /**
   * Event handler for limiting allowed entity types.
   *
   * @param \Drupal\oe_list_pages\ListPageSourceRetrieveEvent $event
   *   The event object.
   */
  public function onEntityTypesRetrieving(ListPageSourceRetrieveEvent $event): void {
    $event->addCacheableDependency($this->config);
    $entity_types = $event->getEntityTypes();
    $allowed_entity_bundles = $this->config->get('allowed_entity_types_bundles');
    if ($allowed_entity_bundles === NULL) {
      return;
    }
    $allowed_entity_bundles = array_keys($allowed_entity_bundles);

    foreach (array_keys($entity_types) as $entity_type) {
      if (!in_array($entity_type, $allowed_entity_bundles)) {
        unset($entity_types[$entity_type]);
      }
    }
    $event->setEntityTypes($entity_types);
  }

  /**
   * Event handler for limiting allowed bundles.
   *
   * @param \Drupal\oe_list_pages\ListPageSourceRetrieveEvent $event
   *   The event object.
   */
  public function onBundlesRetrieving(ListPageSourceRetrieveEvent $event): void {
    $event->addCacheableDependency($this->config);
    $entity_types = $event->getEntityTypes();
    $entity_type = reset($entity_types);
    $bundles = $event->getBundles();

    $allowed_entity_bundles = $this->config->get('allowed_entity_types_bundles');
    if ($allowed_entity_bundles === NULL) {
      return;
    }

    $allowed_bundles = $allowed_entity_bundles[$entity_type] ? array_keys($allowed_entity_bundles[$entity_type]) : NULL;

    foreach (array_keys($bundles) as $bundle) {
      if (!in_array($bundle, $allowed_bundles)) {
        unset($bundles[$bundle]);
      }
    }

    $event->setBundles($entity_type, $bundles);
  }

}
