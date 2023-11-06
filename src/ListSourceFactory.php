<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\Exception\InvalidQueryTypeException;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;

/**
 * Factory class for ListSource objects.
 */
class ListSourceFactory implements ListSourceFactoryInterface {

  /**
   * The facets manager.
   *
   * @var \Drupal\oe_list_pages\ListFacetManagerWrapper
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
   * @var \Drupal\oe_list_pages\ListSourceInterface[]
   */
  protected $listsSources;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * ListSourceFactory constructor.
   *
   * @param \Drupal\oe_list_pages\ListFacetManagerWrapper $facetsManager
   *   The facets manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(ListFacetManagerWrapper $facetsManager, EntityTypeManagerInterface $entityTypeManager, ModuleHandlerInterface $moduleHandler) {
    $this->facetsManager = $facetsManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $moduleHandler;
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
  public function isEntityTypeSourced(string $entity_type): bool {
    if (empty($this->listsSources)) {
      $this->instantiateLists();
    }

    foreach ($this->listsSources as $lists_source) {
      if ($lists_source->getEntityType() === $entity_type) {
        return TRUE;
      }
    }

    return FALSE;
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

    // Allow other sites, in rare cases, to remove items from the list of
    // elligible indexes.
    $this->moduleHandler->alter('oe_list_pages_list_source_indexes', $indexes);

    $lists_sources = [];

    /** @var \Drupal\search_api\Entity\Index $index */
    foreach ($indexes as $index) {
      $lists_pages_index = $index->getThirdPartySetting('oe_list_pages', 'lists_pages_index', FALSE);
      if (!$lists_pages_index) {
        continue;
      }
      $datasources = $index->getDatasources();
      /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
      foreach ($datasources as $datasource) {
        $entity_type = $datasource->getEntityTypeId();
        $bundles = $datasource->getBundles();
        foreach ($bundles as $bundle => $label) {
          // In case not all bundles are indexed.
          if (!$this->isBundleIndexed($datasource, $bundle)) {
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
    $facets = $this->facetsManager->getFacetsByFacetSourceId($id, $index);
    usort($facets, function ($facet1, $facet2) {
      return ($facet1->getWeight() <=> $facet2->getWeight());
    });

    foreach ($facets as $facet) {
      $field_id = $facet->getFieldIdentifier();
      $field = $facet->getFacetSource()->getIndex()->getField($field_id);
      if (!$field) {
        // In case the field is missing from the index, don't crash the
        // application.
        continue;
      }

      try {
        // Try to see if the facet has a query type and skip if not.
        $facet->getQueryType();
      }
      catch (InvalidQueryTypeException $exception) {
        continue;
      }

      $filters[$facet->id()] = $facet->label();
    }

    $bundle_field_id = $this->entityTypeManager->getDefinition($entity_type)->getKey('bundle');
    return new ListSource($id, $entity_type, $bundle, $bundle_field_id, $index, $filters);
  }

  /**
   * Checks if a given bundle is indexed on a data source.
   *
   * @param \Drupal\search_api\Datasource\DatasourceInterface $datasource
   *   The datasource.
   * @param string $bundle
   *   The bundle.
   *
   * @return bool
   *   Whether the bundle is indexed.
   */
  protected function isBundleIndexed(DatasourceInterface $datasource, string $bundle): bool {
    $configuration = $datasource->getConfiguration();
    $selected = $configuration['bundles']['selected'];
    if ($configuration['bundles']['default'] === TRUE && empty($selected)) {
      // All bundles are indexed.
      return TRUE;
    }

    if ($configuration['bundles']['default'] === TRUE && !empty($selected) && !in_array($bundle, $selected)) {
      // All bundles are indexed, except a few that are selected.
      return TRUE;
    }

    if ($configuration['bundles']['default'] === FALSE && in_array($bundle, $selected)) {
      // Only specific bundles are indexed.
      return TRUE;
    }

    return FALSE;
  }

}
