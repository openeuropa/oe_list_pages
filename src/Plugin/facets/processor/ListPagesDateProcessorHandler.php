<?php

namespace Drupal\oe_list_pages\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\processor\UrlProcessorHandler;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\PreQueryProcessorInterface;

/**
 * The URL processor handler for adapting active filter for date fields.
 *
 * @FacetsProcessor(
 *   id = "date_processor_handler",
 *   label = @Translation("Date handler"),
 *   description = @Translation("Trigger the Date processor."),
 *   stages = {
 *     "pre_query" = 50,
 *     "build" = 15,
 *   },
 *   locked = false
 * )
 */
class ListPagesDateProcessorHandler extends UrlProcessorHandler implements BuildProcessorInterface, PreQueryProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function preQuery(FacetInterface $facet) {
    $active_filters = $this->processor->getActiveFilters();
    if (isset($active_filters[$facet->id()]) && $facet->getWidget()['type'] === 'oe_list_pages_date') {
      $active_items = [];
      $active_filter_values = explode('|', $active_filters[$facet->id()][0]);
      foreach ($active_filter_values as $value) {
        $active_items[] = $value;
      }
      // Override active items for removing concatenated value with index '0'.
      $facet->setActiveItems($active_items);
    }
  }

}
