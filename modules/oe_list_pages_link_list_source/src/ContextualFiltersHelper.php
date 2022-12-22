<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_source;

use Drupal\facets\FacetInterface;
use Drupal\oe_list_pages\ListSourceInterface;
use Drupal\search_api\Processor\ProcessorInterface;

/**
 * Helper class for the contextual filters logic.
 */
class ContextualFiltersHelper {

  /**
   * Returns the search API processor that is contextual filters aware.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet for which to determine the plugin.
   *
   * @return \Drupal\oe_list_pages_link_list_source\ContextualAwareProcessorInterface|null
   *   The processor if found.
   */
  public static function getContextualAwareSearchApiProcessor(ListSourceInterface $list_source, FacetInterface $facet): ?ContextualAwareProcessorInterface {
    $processor = static::getSearchApiFieldProcessor($list_source, $facet);
    if ($processor instanceof ContextualAwareProcessorInterface) {
      return $processor;
    }

    return NULL;
  }

  /**
   * Returns the custom Search API field processor this facet is based on.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   *
   * @return \Drupal\search_api\Processor\ProcessorInterface|null
   *   The processor if found, NULL otherwise.
   */
  public static function getSearchApiFieldProcessor(ListSourceInterface $list_source, FacetInterface $facet): ?ProcessorInterface {
    $field = $list_source->getIndex()->getField($facet->getFieldIdentifier());
    if (!$field) {
      return NULL;
    }
    $property_path = $field->getPropertyPath();
    $processors = $list_source->getIndex()->getProcessorsByStage(ProcessorInterface::STAGE_ADD_PROPERTIES);
    foreach ($processors as $processor) {
      $properties = $processor->getPropertyDefinitions();
      if (isset($properties[$property_path])) {
        return $processor;
      }
    }

    return NULL;
  }

}
