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

}
