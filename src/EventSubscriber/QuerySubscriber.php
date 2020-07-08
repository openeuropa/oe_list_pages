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

    $ignored_filters = $preset_filters = [];

    if ($query->getIndex()->getServerInstance()->supportsFeature('search_api_facets')) {
      /** @var \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager */
      $facet_manager = \Drupal::service('facets.manager');
      $queryTypePluginManager = \Drupal::service('plugin.manager.facets.query_type');
      $facetsource_id = $query->getSearchId();
      /** @var \Drupal\oe_list_pages\ListQueryOptionsInterface  $query_options */
      $query_options = $query->getOption('oe_list_page_query_options');

      if (!empty($query_options)) {
        $ignored_filters = $query_options->getIgnoredFilters();
        $preset_filters = $query_options->getPresetFiltersValues();
      }

      // Add the active filters.
      foreach ($facet_manager->getFacetsByFacetSourceId($facetsource_id) as $facet) {
        // Handle ignored filters. If filter is ignored unset its active items.
        if (in_array($facet->id(), $ignored_filters)) {
          $facet->setActiveItems([]);
        }

        // Handle preset filters. If filter is preset, set as active items.
        if (in_array($facet->id(), array_keys($preset_filters))) {
          $facet->setActiveItems([$preset_filters[$facet->id()]]);
        }

        /** @var \Drupal\facets\QueryType\QueryTypeInterface $query_type_plugin */
        $query_type_plugin = $queryTypePluginManager->createInstance($facet->getQueryType(), ['query' => $query, 'facet' => $facet]);
        $query_type_plugin->execute();
      }
    }
  }

}
