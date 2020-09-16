<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\search_api\IndexInterface;

/**
 * Factory class for ListSource objects.
 */
class ListSourceFactory implements ListSourceFactoryInterface {

  /**
   * The facets manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
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
   * ListSourceFactory constructor.
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
   * {@inheritdoc}
   */
  public function get(string $entity_type, string $bundle): ?ListSourceInterface {

    if (empty($this->listsSources)) {
      $this->instantiateLists();
    }

    $id = self::generateFacetSourcePluginId($entity_type, $bundle);
    return !empty($this->listsSources[$id]) ? $this->listsSources[$id] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateFacetSourcePluginId(string $entity_type, string $bundle): string {
    return 'list_facet_source' . PluginBase::DERIVATIVE_SEPARATOR . $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $bundle;
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
   * Creates a new list source.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API Index.
   *
   * @return \Drupal\oe_list_pages\ListSourceInterface
   *   The created list source
   */
  protected function create(string $entity_type, string $bundle, IndexInterface $index): ListSourceInterface {
    $filters = [];
    $id = self::generateFacetSourcePluginId($entity_type, $bundle);
    $facets = $this->facetsManager->getFacetsByFacetSourceId($id);
    foreach ($facets as $facet) {
      $field_id = $facet->getFieldIdentifier();
      $field = $facet->getFacetSource()->getIndex()->getField($field_id);
      if (!$field) {
        // In case the field is missing from the index, don't crash the
        // application.
        continue;
      }
      $filters[$facet->id()] = $field->getLabel();
    }

    $bundle_field_id = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
    return new ListSource($id, $entity_type, $bundle, $bundle_field_id, $index, $filters);
  }

}
