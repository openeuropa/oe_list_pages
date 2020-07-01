<?php

namespace Drupal\oe_list_pages\Plugin\search_api\display;

use Drupal\Core\Plugin\PluginBase;
use Drupal\search_api\Display\DisplayDeriverBase;

/**
 * Derives a display plugin definition for all indexed bundles.
 */
class ListDisplayDeriver extends DisplayDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $base_plugin_id = $base_plugin_definition['id'];
    if (isset($this->derivatives[$base_plugin_id])) {
      return $this->derivatives[$base_plugin_id];
    }
    $definitions = [];
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
          $id = 'list_display' . PluginBase::DERIVATIVE_SEPARATOR . $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $id;
          $definition = $base_plugin_definition;
          $definitions[$id] = [
            'label' => $this->t('List display %id', ['%id' => $id]),
            'description' => $this->t('List display %id', ['%id' => $id]),
            'index' => $datasource->getIndex()->id(),
          ];
          $definitions[$id] = $definition;
        }
        $this->derivatives = $definitions;
      }
    }

    return $this->derivatives;
  }

}
