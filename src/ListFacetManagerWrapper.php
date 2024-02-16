<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\oe_list_pages\Plugin\facets\facet_source\ListFacetSource;
use Drupal\search_api\IndexInterface;

/**
 * Wraps the FacetManager service.
 *
 * Used to set the correct index on the instantiated facets.
 */
class ListFacetManagerWrapper {

  /**
   * The facet manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetManager;

  /**
   * ListFacetManagerWrapper constructor.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facets manager.
   */
  public function __construct(DefaultFacetManager $facetManager) {
    $this->facetManager = $facetManager;
  }

  /**
   * Returns the facets manager.
   *
   * @return \Drupal\facets\FacetManager\DefaultFacetManager
   *   The facets manager.
   */
  public function getFacetManager(): DefaultFacetManager {
    return $this->facetManager;
  }

  /**
   * Gets facets by source id.
   *
   * Replaces the facet source index with the correct one.
   *
   * @param string $list_facet_source_id
   *   The facet source id.
   * @param \Drupal\search_api\IndexInterface|null $index
   *   The index to set in the facetsource.
   *
   * @return array
   *   The facets.
   */
  public function getFacetsByFacetSourceId(string $list_facet_source_id, IndexInterface $index = NULL): array {
    $facets = $this->facetManager->getFacetsByFacetSourceId($list_facet_source_id);
    if (!$index instanceof IndexInterface) {
      return $facets;
    }

    // Change facet source index.
    foreach ($facets as $facet) {
      $facet_source = $facet->getFacetSource();
      if ($facet_source instanceof ListFacetSource) {
        $facet_source->setIndex($index);
      }

    }

    return $facets;
  }

}
