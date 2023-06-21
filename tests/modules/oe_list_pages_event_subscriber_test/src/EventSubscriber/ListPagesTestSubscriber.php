<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_event_subscriber_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\oe_list_pages\ListPageDisallowSortEvent;
use Drupal\oe_list_pages\ListPageEvents;
use Drupal\oe_list_pages\ListPageRssAlterEvent;
use Drupal\oe_list_pages\ListPageRssItemAlterEvent;
use Drupal\oe_list_pages\ListPageSortAlterEvent;
use Drupal\oe_list_pages\ListPageSortOptionsResolver;
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
      ListPageEvents::ALTER_RSS_BUILD => ['onRssBuildAlter'],
      ListPageEvents::ALTER_RSS_ITEM_BUILD => ['onRssItemBuildAlter'],
      ListPageEvents::ALTER_SORT_OPTIONS => ['onSortOptionsAlter'],
      ListPageEvents::DISALLOW_EXPOSED_SORT => ['disallowExposedSort'],
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

  /**
   * Event handler for adding channel information.
   *
   * @param \Drupal\oe_list_pages\ListPageRssAlterEvent $event
   *   The event object.
   */
  public function onRssBuildAlter(ListPageRssAlterEvent $event): void {
    $build = $event->getBuild();
    $build['#channel_elements'][] = [
      '#type' => 'html_tag',
      '#tag' => 'custom_tag',
      '#value' => 'custom_value',
    ];
    $event->setBuild($build);
  }

  /**
   * Event handler for adding item information.
   *
   * @param \Drupal\oe_list_pages\ListPageRssItemAlterEvent $event
   *   The event object.
   */
  public function onRssItemBuildAlter(ListPageRssItemAlterEvent $event): void {
    $build = $event->getBuild();
    $entity = $event->getEntity();
    $creation_date = $entity->get('created')->value;
    $build['#item_elements'][] = [
      '#type' => 'html_tag',
      '#tag' => 'creationDate',
      '#value' => date('d/m/Y', (int) $creation_date),
    ];
    $event->setBuild($build);
  }

  /**
   * Event handler for altering the sort options.
   *
   * @param \Drupal\oe_list_pages\ListPageSortAlterEvent $event
   *   The event.
   */
  public function onSortOptionsAlter(ListPageSortAlterEvent $event): void {
    $alter = (bool) $this->state->get('oe_list_pages_test.alter_sort_options');
    if ($alter && $event->getScope() === ListPageSortOptionsResolver::SCOPE_CONFIGURATION) {
      $options = $event->getOptions();
      $options['field_test_boolean__DESC'] = 'Boolean';
      $event->setOptions($options);
    }

    if ($alter && $event->getScope() === ListPageSortOptionsResolver::SCOPE_USER) {
      $options = $event->getOptions();
      $options['field_test_boolean__DESC'] = 'From 1 to 0';
      $event->setOptions($options);
    }
  }

  /**
   * Event handler for disallowing frontend sort.
   *
   * @param \Drupal\oe_list_pages\ListPageDisallowSortEvent $event
   *   The event.
   */
  public function disallowExposedSort(ListPageDisallowSortEvent $event): void {
    $disallow = $this->state->get('oe_list_pages_test.disallow_frontend_sort', FALSE);
    if ($disallow) {
      $event->disallow();
    }
  }

}
