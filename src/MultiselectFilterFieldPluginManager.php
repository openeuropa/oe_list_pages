<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\facets\FacetInterface;
use Drupal\oe_list_pages\Annotation\MultiselectFilterField;

/**
 * Plugin manager for multiselect filter field plugins.
 */
class MultiselectFilterFieldPluginManager extends DefaultPluginManager {

  use FacetManipulationTrait;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * Constructs a new multiselect filter field plugin manager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/MultiselectFilterField', $namespaces, $module_handler, MultiselectFilterFieldPluginInterface::class, MultiselectFilterField::class);

    $this->alterInfo('multiselect_filter_field_info');
    $this->setCacheBackend($cache_backend, 'multiselect_filter_field_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    uasort($definitions, [SortArray::class, 'sortByWeightElement']);

    return $definitions;
  }

  /**
   * Returns the plugin ID supported by a field type.
   *
   * @param string $field_type
   *   The field type we need to check the plugin for.
   *
   * @return string|null
   *   The applicable plugin id.
   */
  public function getPluginIdByFieldType(string $field_type): ?string {
    $definitions = $this->getDefinitions();
    foreach ($definitions as $id => $definition) {
      if (isset($definition['field_types']) && in_array($field_type, $definition['field_types'])) {
        return $id;
      }
    }
    return NULL;
  }

  /**
   * Returns the plugin ID supported for a Search API data type.
   *
   * @param string $data_type
   *   The data type we need to check the plugin for.
   *
   * @return string|null
   *   The applicable plugin id.
   */
  public function getPluginIdByDataType(string $data_type): ?string {
    $definitions = $this->getDefinitions();
    foreach ($definitions as $id => $definition) {
      if (isset($definition['data_types']) && in_array($data_type, $definition['data_types'])) {
        return $id;
      }
    }

    return NULL;
  }

  /**
   * Determines the plugin ID to use for a given facet.
   *
   * It first checks if there are any plugins specific to this given facet.
   * Then, it checks for the Drupal field types the plugins would apply. And
   * finally, it tries the Search API data type in case nothing was found.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   *
   * @return string|null
   *   The applicable plugin id.
   */
  public function getPluginIdForFacet(FacetInterface $facet, ListSourceInterface $list_source): ?string {
    // First, check if there is a specific plugin for a given facet.
    $definitions = $this->getDefinitions();
    foreach ($definitions as $id => $definition) {
      if (isset($definition['facet_ids']) && in_array($facet->id(), $definition['facet_ids'])) {
        return $id;
      }
    }

    // Then check for the Drupal field type.
    $field_definition = $this->getFacetFieldDefinition($facet, $list_source);
    $field_type = !empty($field_definition) ? $field_definition->getType() : NULL;
    if ($field_type) {
      $id = $this->getPluginIdByFieldType($field_type);
      if ($id) {
        return $id;
      }
    }

    // If we cannot find a Drupal field type, we try using the Search API
    // data type.
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $list_source->getIndex();
    $index_field = $index->getField($facet->getFieldIdentifier());
    $index_field_type = $index_field->getType();
    return $this->getPluginIdByDataType($index_field_type);
  }

}
