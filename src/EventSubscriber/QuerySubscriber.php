<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\EventSubscriber;

use Drupal\facets\FacetInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\facets\QueryType\QueryTypePluginManager;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListPresetFiltersBuilder;
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
    $ignored_filters = $preset_filter_values = [];

    if (!$query->getIndex()->getServerInstance()->supportsFeature('search_api_facets')) {
      return;
    }

    $source_id = $query->getSearchId();
    /** @var \Drupal\oe_list_pages\ListQueryOptionsInterface $query_options */
    $query_options = $query->getOption('oe_list_page_query_options');

    if (!empty($query_options)) {
      $ignored_filters = $query_options->getIgnoredFilters();
      $preset_filter_values = $query_options->getPresetFiltersValues();
    }

    $facets = [];
    foreach ($this->facetManager->getFacetsByFacetSourceId($source_id) as $facet) {
      $filter_id = ListPresetFiltersBuilder::generateFilterId($facet->id(), array_keys($facets));
      $facets[$filter_id] = $facet;
    }

    $this->processFacetActiveValues($facets, $preset_filter_values);

    foreach ($facets as $facet) {
      // If the facet is using a default status processor and has default
      // active items, set them if we did not yet have any.
      if (!$facet->getActiveItems() && $facet->get('default_status_active_items')) {
        $facet->setActiveItems($facet->get('default_status_active_items'));
      }

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
   * Applies the preset filter values onto the facet.
   *
   * Preset values are always going to be in the query and cannot be removed.
   * Extra values can be included on top of the preset ones.
   *
   * Since each facet can have multiple filters associated with it due to having
   * multiple operators, we create a "fake" for each of these filters so we
   * can
   *
   * @param array $facets
   *   The facets.
   * @param array $preset_filter_values
   *   The preset values.
   */
  protected function processFacetActiveValues(array &$facets, array $preset_filter_values): void {
    // Group the preset filter values by the facet ID.
    $grouped_preset_filter_values = [];
    foreach ($preset_filter_values as $filter_id => $value) {
      $grouped_preset_filter_values[$value->getFacetId()][$filter_id] = $value;
    }

    foreach ($grouped_preset_filter_values as $facet_id => $values) {
      if (count($values) === 1) {
        // If we have only 1 filter for this facet, we just apply the operator
        // and preset values on it.
        $preset_filter = reset($values);
        $filter_id = key($values);
        $this->applyPresetFilterValues($facets[$filter_id], $preset_filter);
        continue;
      }

      // Otherwise, we need to generate extra facets to accommodate multiple
      // filters.
      $default_filter_id = key($values);
      /** @var \Drupal\facets\Entity\Facet $original_facet */
      $original_facet = $facets[$default_filter_id];
      // Unset the original facet from the list, as will will clone it for each
      // filter that uses this facet.
      unset($facets[$default_filter_id]);

      foreach ($values as $preset_filter_id => $preset_filter) {
        $facet = clone $original_facet;

        $this->applyPresetFilterValues($facet, $preset_filter);
        $facets[$preset_filter_id] = $facet;
      }
    }
  }

  /**
   * Applies the preset filter values onto the facet.
   *
   * Preset values are always going to be in the query and cannot be removed.
   * Extra values can be included on top of the preset ones.
   *
   * Since each facet can have multiple filters associated with it due to having
   * multiple operators, we create a "fake" for each of these filters so we
   * can
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param \Drupal\oe_list_pages\ListPresetFilter $preset_filter
   *   The preset values.
   */
  protected function applyPresetFilterValues(FacetInterface $facet, ListPresetFilter $preset_filter): void {
    $active_items = $facet->getActiveItems();

    // Set the operator.
    if ($preset_filter->getOperator() === ListPresetFilter::NOT_OPERATOR) {
      $facet->setQueryOperator(ListPresetFilter::AND_OPERATOR);
      $facet->setExclude(TRUE);
    }
    else {
      $facet->setExclude(FALSE);
      $facet->setQueryOperator($preset_filter->getOperator());
    }

    if (empty($active_items)) {
      // If the facet doesn't already have active items from this facet, we
      // just set them all.
      $facet->setActiveItems($preset_filter->getValues());
      return;
    }

    // If the facet widget is not multiselect, it means we cannot merge the
    // filter values with the preset, so we just override whatever is in the
    // facet with the preset.
    if ($facet->getWidget()['type'] !== 'oe_list_pages_multiselect') {
      $facet->setActiveItems($preset_filter->getValues());
      return;
    }

    // Otherwise, we have to merge them to ensure that the preset values are
    // always set no matter what and to allow exposed filters to add to them.
    $active_items = array_unique(array_merge($preset_filter->getValues(), $active_items));
    $facet->setActiveItems($active_items);
  }

}
