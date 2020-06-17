<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\FacetManager\DefaultFacetManager;

/**
 * The List manager.
 */
class ListManager {

  /**
   * EntityTypeManager constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facet manager.
   */
  public function __construct(EntityTypeManager $entityTypeManager, DefaultFacetManager $facetManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->facetManager = $facetManager;
  }

  /**
   * Get available lists to be used in searches.
   *
   * @return array
   *   The available lists.
   */
  public function getAvailableLists(): array {
    $lists = [];

    /** @var \Drupal\search_api\Entity\SearchApiConfigEntityStorage $storage_index */
    $index_storage = $this->entityTypeManager->getStorage('search_api_index');

    // Loop through all available data sources from enabled indexes.
    $indexes = $index_storage->loadByProperties(['status' => 1]);
    foreach ($indexes as $index) {
      $datasources = $index->getDatasources();
      foreach ($datasources as $datasource) {
        $entity_type = $datasource->getEntityTypeId();
        $bundles = $datasource->getBundles();
        foreach ($bundles as $id => $label) {

          // In case not all bundles are indexed:
          if (!empty($datasource->getConfiguration()['bundles']['selected']) && !in_array($id, $datasource->getConfiguration()['bundles']['selected'])) {
            continue;
          }

          $lists[$entity_type][] = [
            'label' => $label,
            'index' => $index,
            'id' => $id,
          ];
        }
      }
    }

    return $lists;
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
    return 'list_facet_source' . PluginBase::DERIVATIVE_SEPARATOR . $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $bundle;
  }

  /**
   * Get available filters for the entity type / bundle.
   *
   * @param string $search_id
   *   The search id.
   *
   * @return array
   *   The filters.
   */
  public function getAvailableFiltersForList(string $search_id): array {
    $filters = [];
    $facets = $this->facetManager->getFacetsByFacetSourceId($search_id);
    foreach ($facets as $facet) {
      $field_id = $facet->getFieldIdentifier();
      $filters[$field_id] = $facet->getFacetSource()->getIndex()->getField($field_id)->getLabel();
    }

    return $filters;
  }

}
