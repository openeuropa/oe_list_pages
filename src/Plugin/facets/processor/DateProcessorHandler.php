<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\processor\UrlProcessorHandler;

/**
 * The URL processor handler for adapting active filter for date fields.
 *
 * @FacetsProcessor(
 *   id = "date_processor_handler",
 *   label = @Translation("Date URL processor"),
 *   description = @Translation("Required processor to interpret the date active filters."),
 *   stages = {
 *     "pre_query" = 50,
 *     "build" = 15,
 *   },
 *   locked = false
 * )
 */
class DateProcessorHandler extends UrlProcessorHandler {

  /**
   * {@inheritdoc}
   */
  public function preQuery(FacetInterface $facet) {
    $active_filters = $this->processor->getActiveFilters();
    if (isset($active_filters[$facet->id()]) && $facet->getWidget()['type'] === 'oe_list_pages_date') {
      // The date facet comes with an active filter in the form of gt|2020-08-21
      // or bt|2020-08-21|2020-08-23. So we need to explode this and structure
      // this information so that the query plugin and widget can better
      // understand it.
      $active_items = [];
      // Normally we should only have one filter.
      $active_filter_values = explode('|', $active_filters[$facet->id()][0]);
      foreach ($active_filter_values as $value) {
        $active_items[] = $value;
      }

      $facet->setActiveItems($active_items);
    }
  }

  /**
   * Structure the active facet items into a descriptive array.
   *
   * NB: this needs to be called only after the preQuery() has run.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   *
   * @return array
   *   The structured array containing the operator and date values.
   */
  public static function structureActiveItems(FacetInterface $facet): array {
    if (!$facet->getWidget()['type'] === 'oe_list_pages_date') {
      return [];
    }

    $active_filters = $facet->getActiveItems();
    if (!$active_filters) {
      return [];
    }

    if (!isset($active_filters[1])) {
      // Normally should not happen, at least 1 filter value needs to be
      // present so we behave as no filter exists.
      return [];
    }

    $items = [
      'operator' => $active_filters[0],
      'first' => $active_filters[1],
    ];

    if (isset($active_filters[2])) {
      $items['second'] = $active_filters[2];
    }

    return $items;
  }

}
