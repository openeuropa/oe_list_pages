<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\FacetManager\DefaultFacetManager;

/**
 * The filter manager.
 */
class FilterManager {

  /**
   * The Facet Manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetManager;

  /**
   * ListFacetManager constructor.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facet manager.
   */
  public function __construct(DefaultFacetManager $facetManager) {
    $this->facetManager = $facetManager;
  }

  /**
   * Get the search id for the list used for entity type / bundle.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return string
   *   The id.
   */
  public function getSearchId(string $entity_type, string $bundle) {
    return $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $bundle;
  }

  /**
   * Get available filters for the entity type / bundle.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   *
   * @return array
   *   The available filters.
   */
  public function getAvailableFilters(string $entity_type, string $bundle): array {
    $filters = [];
    $search_id = $this->getSearchId();
    // $facets = $this->facetManager->getFacetsByFacetSourceId($search_id);
    return $filters;
  }

}
