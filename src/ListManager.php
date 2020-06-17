<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityTypeManager;
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
      /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
      foreach ($datasources as $datasource) {
        $entity_type = $datasource->getEntityTypeId();
        $bundles = $datasource->getBundles();
        foreach ($bundles as $id => $label) {

          // In case not all bundles are indexed:
          if (!empty($datasource->getConfiguration()['bundles']['selected']) && !in_array($id, $datasource->getConfiguration()['bundles']['selected'])) {
            continue;
          }

          $list = new ListSource($entity_type, $id);
          $list->setDataSource($datasource);
          $lists[$list->id()] = $list;
        }
      }
    }

    return $lists;
  }

}
