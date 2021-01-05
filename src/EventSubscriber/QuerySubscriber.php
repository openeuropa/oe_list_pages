<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\EventSubscriber;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\facets\QueryType\QueryTypePluginManager;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListPresetFiltersBuilder;
use Drupal\search_api\Event\QueryPreExecuteEvent;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
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
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
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
      $facets[$facet->id()] = $facet;
    }

    $cloned_facets = [];
    $facets = $this->processFacetActiveValues($facets, $preset_filter_values, $cloned_facets);

    foreach ($facets as $facet) {
      // If the facet is using a default status processor and has default
      // active items, set them if we did not yet have any. This unless it
      // was cloned as preset filters, in which case we need to take over the
      // defaults completely.
      if (!$facet->getActiveItems() && $facet->get('default_status_active_items') && !isset($cloned_facets[$facet->id()])) {
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

      if (!isset($cloned_facets[$facet->id()])) {
        continue;
      }

      // At this point we run the query type plugin execute method which adds
      // the query conditions for our preset filters. But we want to ensure that
      // these conditions are not taken into account when determining the
      // result count for the main facet which may be exposed. Its values
      // should only include the options that would limit the result set. So
      // for this we need to remove the query tag which is used in
      // Database::getFacets().
      $this->removeAppliedQueryTag($query, $facet);
    }
  }

  /**
   * Processes the preset values and creates "fake" facet definitions.
   *
   * Preset values are always going to be in the query and cannot be removed.
   *
   * For each facet for which we have preset values, we create a clone and
   * apply the active values onto it, while keeping the original intact so it
   * can take active values from the URL (exposed filters).
   *
   * @param array $facets
   *   The facets.
   * @param array $preset_filter_values
   *   The preset values.
   * @param array $cloned_facets
   *   Keep track of the facets that we clone.
   *
   * @return array
   *   The processed facets.
   */
  protected function processFacetActiveValues(array $facets, array $preset_filter_values, array &$cloned_facets = []): array {
    // Group the preset filter values by the facet ID.
    $grouped_preset_filter_values = [];
    foreach ($preset_filter_values as $filter_id => $value) {
      $grouped_preset_filter_values[$value->getFacetId()][$filter_id] = $value;
    }

    // Group the facets by their default filter IDs.
    $facets_by_filter = [];
    foreach ($facets as $facet) {
      $filter_id = ListPresetFiltersBuilder::generateFilterId($facet->id(), array_keys($facets_by_filter));
      $facets_by_filter[$filter_id] = $facet;
    }

    foreach ($grouped_preset_filter_values as $facet_id => $values) {
      // For each facet, we need to keep the original with the active values
      // from the context, and clone it for each time it has been set as a
      // preset filters.
      /** @var \Drupal\facets\Entity\Facet $original_facet */
      $original_facet = $facets[$facet_id];

      foreach ($values as $preset_filter_id => $preset_filter) {
        $facet = clone $original_facet;
        $cloned_facets[$facet->id()] = $facet;

        // Generate a new filter ID for each of the clone.
        $this->applyPresetFilterValues($facet, $preset_filter);
        $clone_filter_id = ListPresetFiltersBuilder::generateFilterId($facet_id, array_keys($facets_by_filter));
        $facets_by_filter[$clone_filter_id] = $facet;
      }
    }

    return $facets_by_filter;
  }

  /**
   * Get original field name from facet config.
   *
   * @param \Drupal\facets\Entity\FacetInterface $facet
   *   The facet.
   *
   * @return string|null
   *   The field name if found.
   */
  protected function getFieldName(FacetInterface $facet) : ?string {
    $field = $facet->getFacetSource()->getIndex()->getField($facet->getFieldIdentifier());
    $field_name = $field->getOriginalFieldIdentifier();
    $property_path = $field->getPropertyPath();
    $parts = explode(':', $property_path);
    if (count($parts) > 1) {
      $field_name = $parts[0];
    }

    return $field_name;
  }

  /**
   * Applies the preset filter values onto the facet.
   *
   * Preset values are always going to be in the query and cannot be removed.
   * Extra values can be included on top of the preset ones.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param \Drupal\oe_list_pages\ListPresetFilter $preset_filter
   *   The preset values.
   */
  protected function applyPresetFilterValues(FacetInterface $facet, ListPresetFilter $preset_filter): void {

    if ($preset_filter->getType() == 'contextual') {
      /** @var \Drupal\node\NodeInterface $current_node */
      $current_node = \Drupal::routeMatch()->getParameter('node');
      $field_name = $this->getFieldName($facet);
      $field = $current_node->get($field_name);
      $field_definition = $field->getFieldDefinition();
      $values = [];
      /* @todo create a plugin type MultiSelectFilterFieldPlugin */
      if (in_array(EntityReferenceFieldItemListInterface::class, class_implements($field_definition->getClass()))) {
        $field_value = $current_node->get($field_name)->getValue();
        $values = array_map(function ($value) {
          return $value['target_id'];
        }, $field_value);
      }
    }
    else {
      $values = $preset_filter->getValues();
    }

    $facet->setActiveItems($values);
    if ($preset_filter->getOperator() === ListPresetFilter::NOT_OPERATOR) {
      $facet->setQueryOperator(ListPresetFilter::AND_OPERATOR);
      $facet->setExclude(TRUE);

      return;
    }

    $facet->setExclude(FALSE);
    $facet->setQueryOperator($preset_filter->getOperator());
  }

  /**
   * Removes the condition groups query tag for a given facet.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   *
   * @see self::queryAlter()
   */
  protected function removeAppliedQueryTag(QueryInterface $query, FacetInterface $facet): void {
    $tag = 'facet:' . $facet->getFieldIdentifier();
    $group = $query->getConditionGroup();
    if (!$group instanceof ConditionGroupInterface) {
      return;
    }
    $this->removeQueryTagFromConditions($group, $tag);
  }

  /**
   * Recursively tries to remove a query tag from a nested condition group.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $group
   *   The condition group.
   * @param string $tag
   *   The query tag.
   */
  protected function removeQueryTagFromConditions(ConditionGroupInterface $group, string $tag): void {
    $tags = &$group->getTags();
    if (isset($tags[$tag])) {
      unset($tags[$tag]);
      return;
    }

    foreach ($group->getConditions() as $condition) {
      if ($condition instanceof ConditionGroupInterface) {
        $this->removeQueryTagFromConditions($condition, $tag);
      }
    }
  }

}
