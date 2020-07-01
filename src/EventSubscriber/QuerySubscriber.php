<?php

namespace Drupal\oe_list_pages\EventSubscriber;

use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event subscriber that allows to alter list source queries.
 */
class QuerySubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      SearchApiEvents::QUERY_PRE_EXECUTE => 'queryAlter',
    ];
  }

  /**
   * Reacts to the query alter event.
   *
   * @param \Drupal\search_api\Event\QueryPreExecuteEvent $event
   *   The query alter event.
   */
  public function queryAlter(QueryPreExecuteEvent $event) {
    $query = $event->getQuery();
    if ($query->getIndex()->getServerInstance()->supportsFeature('search_api_facets')) {
      /** @var \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager */
      $facet_manager = \Drupal::service('facets.manager');
      $queryTypePluginManager = \Drupal::service('plugin.manager.facets.query_type');
      $facetsource_id = $query->getSearchId();
      // Add the active filters.
      foreach ($facet_manager->getFacetsByFacetSourceId($facetsource_id) as $facet) {
        // $facet->setActiveItems([]);
        /** @var \Drupal\facets\QueryType\QueryTypeInterface $query_type_plugin */
        $query_type_plugin = $queryTypePluginManager->createInstance($facet->getQueryType(), ['query' => $query, 'facet' => $facet]);
        $query_type_plugin->execute();
      }
    }
  }

}
