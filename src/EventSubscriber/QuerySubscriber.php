<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\EventSubscriber;

use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\facets\QueryType\QueryTypePluginManager;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event subscriber that allows to alter list source queries.
 */
class QuerySubscriber implements EventSubscriberInterface {

  /**
   * The facets manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetManager;

  /**
   * The query type plugin manager.
   *
   * @var \Drupal\facets\QueryType\QueryTypePluginManager
   */
  protected $queryTypePluginManager;

  /**
   * QuerySubscriber Constructor.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facets manager.
   * @param \Drupal\facets\QueryType\QueryTypePluginManager $queryTypePluginManager
   *   The query type plugin manager.
   */
  public function __construct(DefaultFacetManager $facetManager, QueryTypePluginManager $queryTypePluginManager) {
    $this->facetManager = $facetManager;
    $this->queryTypePluginManager = $queryTypePluginManager;
  }

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

    if (!$query->getIndex()->getServerInstance()->supportsFeature('search_api_facets')) {
      return;
    }

    $facetsource_id = $query->getSearchId();
    /** @var \Drupal\oe_list_pages\ListQueryOptionsInterface $query_options */
    $query_options = $query->getOption('oe_list_page_query_options');

    if (!empty($query_options)) {
      $ignored_filters = $query_options->getIgnoredFilters();
      $preset_filters = $query_options->getPresetFiltersValues();
    }

    // Add the active filters.
    foreach ($this->facetManager->getFacetsByFacetSourceId($facetsource_id) as $facet) {
      $facets[$facet->id()] = $facet;
    }

    $processed_facets = array_merge($facets, $this->processDefaultFilters($facets, $preset_filters));
    foreach ($processed_facets as $facet) {
      // Handle ignored filters. If filter is ignored unset its active items.
      if (in_array($facet->id(), $ignored_filters)) {
        $facet->setActiveItems([]);
      }

      /** @var \Drupal\facets\QueryType\QueryTypeInterface $query_type_plugin */
      $query_type_plugin = $this->queryTypePluginManager->createInstance($facet->getQueryType(), [
        'query' => $query,
        'facet' => $facet,
      ]);
      $query_type_plugin->execute();
    }
  }

  /**
   * Processes the default filters.
   *
   * @param array $facets
   *   The facets.
   * @param array $preset_filters
   *   The preset filters.
   *
   * @return array
   *   The processed facets.
   */
  protected function processDefaultFilters(array $facets, array $preset_filters): array {
    $ignored_filters = $processed_facets = [];

    // Some facets might have original values already set (by url processor).
    foreach ($preset_filters as $filter) {
      /** @var \Drupal\facets\Entity\Facet $facet */
      $facet = $facets[$filter->getFacetId()];
      if (!empty($facet->getActiveItems())) {
        $ignored_filters[] = $facet->id();
      }
    }

    /** @var \Drupal\oe_list_pages\ListPresetFilter $filter */
    foreach ($preset_filters as $filter) {
      // We need to clone the facet to use to guarantee we can execute it
      // several times if needed.
      /** @var \Drupal\facets\Entity\Facet $original_facet */
      $original_facet = $facets[$filter->getFacetId()];
      $facet = clone $original_facet;

      // If we have already active items skip default values.
      if (in_array($facet->id(), $ignored_filters)) {
        continue;
      }
      if ($filter->getOperator() === ListPresetFilter::NOT_OPERATOR) {
        $facet->setQueryOperator(ListPresetFilter::AND_OPERATOR);
        $facet->setExclude(TRUE);
      }
      else {
        $facet->setQueryOperator($filter->getOperator());
      }

      $active_items = is_array($filter->getValues()) ? $filter->getValues() : [$filter->getValues()];
      // For the query.
      $facet->setActiveItems($active_items);
      // For the form.
      $original_facet->setActiveItems($active_items);

      $facet_id = $facet->id();
      $inc = 1;
      while (isset($processed_facets[$facet_id])) {
        $facet_id .= $inc;
      }
      $processed_facets[$facet_id] = $facet;
    }

    return $processed_facets;
  }

}
