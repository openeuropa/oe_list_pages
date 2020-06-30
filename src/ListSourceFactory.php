<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\search_api\Entity\Index;

/**
 * Factory class for ListSource entities.
 */
class ListSourceFactory implements ListSourceFactoryInterface {

  /**
   * The facets manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   *   The facets manager.
   */
  protected $facetsManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The list sources.
   *
   * @var array
   */
  protected $listsSources;

  /**
   * EntityMetaWrapperFactory constructor.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetsManager
   *   The facets manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(DefaultFacetManager $facetsManager, EntityTypeManagerInterface $entityTypeManager) {
    $this->facetsManager = $facetsManager;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Instantiate the list sources from the indexed content bundles.
   */
  protected function instantiateLists(): void {

    if (!empty($this->listsSources)) {
      return;
    }

    /** @var \Drupal\search_api\Entity\SearchApiConfigEntityStorage $storage_index */
    $index_storage = $this->entityTypeManager->getStorage('search_api_index');

    // Loop through all available data sources from enabled indexes.
    $indexes = $index_storage->loadByProperties(['status' => 1]);
    $lists_sources = [];

    /** @var \Drupal\search_api\Entity\Index $index */
    foreach ($indexes as $index) {
      $datasources = $index->getDatasources();
      /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
      foreach ($datasources as $datasource) {
        $entity_type = $datasource->getEntityTypeId();
        $bundles = $datasource->getBundles();
        foreach ($bundles as $bundle => $label) {
          // In case not all bundles are indexed.
          if (!empty($datasource->getConfiguration()['bundles']['selected']) && !in_array($bundle, $datasource->getConfiguration()['bundles']['selected'])) {
            continue;
          }

          $list = $this->create($entity_type, $bundle, $datasource->getIndex());
          $lists_sources[$list->getSearchId()] = $list;
        }
      }
    }

    $this->listsSources = $lists_sources;
  }

  /**
   * {@inheritdoc}
   */
  public function generateSearchId(string $entity_type, string $bundle): string {
    return 'list_facet_source' . PluginBase::DERIVATIVE_SEPARATOR . $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function create(string $entity_type, string $bundle, Index $index): ListSource {

    $filters = [];
    $id = $this->generateSearchId($entity_type, $bundle);
    $facets = $this->facetsManager->getFacetsByFacetSourceId($id);
    foreach ($facets as $facet) {
      $field_id = $facet->getFieldIdentifier();
      $filters[$field_id] = $facet->getFacetSource()->getIndex()->getField($field_id)->getLabel();
    }

    $list = new ListSource($id, $entity_type, $bundle, $index, $filters);
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function get(string $entity_type, string $bundle): ?ListSource {

    if (empty($this->listsSources)) {
      $this->instantiateLists();
    }

    $id = $this->generateSearchId($entity_type, $bundle);
    return !empty($this->listsSources[$id]) ? $this->listsSources[$id] : NULL;
  }

}
