<?php

namespace Drupal\oe_list_pages;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\ProcessorInterface;
use Drupal\facets\Result\ResultInterface;

/**
 * Provides helper methods to manipulate facets and results.
 */
trait FacetManipulationTrait {

  /**
   * Processes and returns the results of a given facet.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   *
   * @return array
   *   The results.
   */
  protected function processFacetResults(FacetInterface $facet): array {
    $results = $facet->getResults();
    foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_PRE_QUERY) as $processor) {
      $processor->preQuery($facet);
    }

    foreach ($facet->getProcessorsByStage(ProcessorInterface::STAGE_BUILD) as $processor) {
      $results = $processor->build($facet, $results);
    }

    return $results;
  }

  /**
   * Transforms a list of Facet results to selectable options.
   *
   * @param array $results
   *   The results.
   *
   * @return array
   *   The options.
   */
  protected function transformResultsToOptions(array $results): array {
    $options = [];
    array_walk($results, function (ResultInterface &$result) use (&$options) {
      $options[$result->getRawValue()] = $result->getDisplayValue();
    });

    return $options;
  }

  /**
   * Generates the label for the filter values set as default values.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param \Drupal\oe_list_pages\ListPresetFilter $filter
   *   The filter.
   *
   * @return string
   *   The label.
   */
  protected function getDefaultFilterValuesLabel(FacetInterface $facet, ListPresetFilter $filter): string {
    // Keep track of the original active items so we can reset them.
    $active_items = $facet->getActiveItems();
    $filter_values = $filter->getValues();
    $facet->setActiveItems($filter_values);
    $results = $this->processFacetResults($facet);

    $filter_label = [];
    foreach ($filter_values as $value) {
      $filter_label[$value] = $value;
      foreach ($results as $result) {
        if ($result->getRawValue() == $value) {
          $filter_label[$value] = $result->getDisplayValue();
        }
      }
    }

    // Reset active items.
    $facet->setActiveItems($active_items);
    return implode(', ', $filter_label);
  }

}
