<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_event_subscriber_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\oe_list_pages\ListPageEvents;
use Drupal\oe_list_pages\ListPageSourceAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * OpenEuropa List Pages test event subscriber.
 */
class ListPagesTestSubscriber implements EventSubscriberInterface {

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs event subscriber.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      ListPageEvents::ALTER_ENTITY_TYPES => ['onEntityTypesAlter'],
      ListPageEvents::ALTER_BUNDLES => ['onBundlesAlter'],
    ];
  }

  /**
   * Event handler for limiting the allowed entity types.
   *
   * @param \Drupal\oe_list_pages\ListPageSourceAlterEvent $event
   *   The event object.
   */
  public function onEntityTypesAlter(ListPageSourceAlterEvent $event): void {
    $entity_types = $event->getEntityTypes();
    $allowed = $this->state->get('oe_list_pages_test.allowed_entity_types_bundles');
    if ($allowed === NULL) {
      return;
    }

    $allowed_entity_types = array_keys($allowed);
    $event->setEntityTypes(array_intersect($entity_types, $allowed_entity_types));
  }

  /**
   * Event handler for limiting the allowed bundles.
   *
   * @param \Drupal\oe_list_pages\ListPageSourceAlterEvent $event
   *   The event object.
   */
  public function onBundlesAlter(ListPageSourceAlterEvent $event): void {
    $entity_types = $event->getEntityTypes();
    $entity_type = reset($entity_types);
    $bundles = $event->getBundles();

    $allowed = $this->state->get('oe_list_pages_test.allowed_entity_types_bundles');
    if ($allowed === NULL) {
      return;
    }

    $allowed_bundles = $allowed[$entity_type] ?? [];

    $event->setBundles($entity_type, array_intersect($bundles, $allowed_bundles));
  }

}
