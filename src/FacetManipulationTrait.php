<?php

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
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
   * Generates default filter value labels from a facet.
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
    $filter_values = $filter->getValues();

    $clone = clone $facet;
    $this->rebuildFacet($clone, $filter->getValues());

    $filter_label = [];
    foreach ($filter_values as $value) {
      $filter_label[$value] = $value;
      foreach ($clone->getResults() as $result) {
        if ($result->getRawValue() == $value) {
          $filter_label[$value] = $result->getDisplayValue();
        }
      }
    }

    return implode(', ', $filter_label);
  }

  /**
   * Gets field definition for the field used in the facet.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition.
   */
  protected function getFacetFieldDefinition(FacetInterface $facet, ListSourceInterface $list_source): ?FieldDefinitionInterface {
    if (!isset($this->entityFieldManager) || !$this->entityFieldManager instanceof EntityFieldManagerInterface) {
      $this->entityFieldManager = \Drupal::service('entity_field.manager');
    }

    $field = $list_source->getIndex()->getField($facet->getFieldIdentifier());
    $field_name = $field->getOriginalFieldIdentifier();
    $property_path = $field->getPropertyPath();
    $parts = explode(':', $property_path);
    if (count($parts) > 1) {
      $field_name = $parts[0];
    }

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($list_source->getEntityType(), $list_source->getBundle());

    return $field_definitions[$field_name] ?? NULL;
  }

  /**
   * Forces a rebuild of a facet using predefined filter values.
   *
   * We need this because the facet manager prevents the processing more than
   * once of facets.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param array $values
   *   The values.
   */
  protected function rebuildFacet(FacetInterface $facet, array $values): void {
    $facet->setActiveItems($values);
    \Drupal::service('facets.manager')->updateResults($facet->getFacetSourceId());
    \Drupal::service('facets.manager')->build($facet);
    return;

    $configuration = [
      'query' => NULL,
      'facet' => $facet,
      'results' => [],
    ];
    foreach ($values as $value) {
      $configuration['results'][] = [
        'count' => 1,
        'filter' => $value,
      ];
    }
    /** @var \Drupal\facets\QueryType\QueryTypeInterface $query_type */
    $query_type = \Drupal::service('plugin.manager.facets.query_type')->createInstance($facet->getQueryType(), $configuration);
    $query_type->build();
    $facet->setResults($this->processFacetResults($facet));
  }

  /**
   * Returns the facets of a given list source from the facet manager.
   *
   * These facets have already been built and can contain results.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   *
   * @return array
   *   The facets keyed by ID.
   */
  protected function getKeyedFacetsFromSource(ListSourceInterface $list_source): array {
    $facets = $this->getFacetsManager()->getFacetsByFacetSourceId($list_source->getSearchId(), $list_source->getIndex());
    $keyed = [];
    foreach ($facets as $facet) {
      $keyed[$facet->id()] = $facet;
    }

    return $keyed;
  }

  /**
   * Returns the facets manager.
   *
   * @return \Drupal\oe_list_pages\ListFacetManagerWrapper
   *   The facets manager.
   */
  protected function getFacetsManager(): ListFacetManagerWrapper {
    if (!isset($this->facetManager)) {
      $this->facetManager = \Drupal::service('oe_list_pages.list_facet_manager_wrapper');
    }

    return $this->facetManager;
  }

}
