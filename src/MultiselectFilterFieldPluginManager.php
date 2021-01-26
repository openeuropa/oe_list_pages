<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\oe_list_pages\Annotation\MultiselectFilterField;

/**
 * Plugin manager for multiselect filter field plugins.
 */
class MultiselectFilterFieldPluginManager extends DefaultPluginManager {

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
      if (in_array($field_type, $definition['field_types'])) {
        return $id;
      }
    }
    return NULL;
  }

}