<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_open_vocabularies_test\EventSubscriber;

use Drupal\oe_list_pages_open_vocabularies\Event\AssociationFacetUpdateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Responds to changes to facet configuration update/create.
 */
class SearchApiFacetTestSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AssociationFacetUpdateEvent::NAME] = 'onFacetUpdate';
    return $events;
  }

  /**
   * Updates the open vocabulary facets before saving.
   *
   * @param \Drupal\oe_list_pages_open_vocabularies\Event\AssociationFacetUpdateEvent $event
   *   The Search API Facet update event.
   */
  public function onFacetUpdate(AssociationFacetUpdateEvent $event) {
    $facet = $event->getFacet();
    $settings = [
      'behavior' => 'text',
      'text' => 'No results found for this block!',
      'text_format' => 'plain_text',
    ];
    $facet->setEmptyBehavior($settings);
    $event->setFacet($facet);
  }

}
