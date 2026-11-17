<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_filters_test\EventSubscriber;

use Drupal\Core\State\StateInterface;
use Drupal\oe_list_pages\ListPageEvents;
use Drupal\oe_list_pages\ListPageIgnoredFiltersAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * OpenEuropa List Pages test event subscriber for updating ignored filters.
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
      ListPageEvents::ALTER_IGNORED_FILTERS => ['onFormBuilderAlter'],
    ];
  }

  /**
   * Event handler for limiting the allowed filters in list builder.
   *
   * @param \Drupal\oe_list_pages\ListPageIgnoredFiltersAlterEvent $event
   *   The event object.
   */
  public function onFormBuilderAlter(ListPageIgnoredFiltersAlterEvent $event): void {
    $ignored_filters = $this->state->get('oe_list_pages_test.ignored_filters', NULL);
    if (!empty($ignored_filters)) {
      $event->setIgnoredFilters(array_merge($event->getIgnoredFilters(), $ignored_filters));
    }
  }

}
