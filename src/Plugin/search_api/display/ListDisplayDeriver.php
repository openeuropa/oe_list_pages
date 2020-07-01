<?php

declare(strict_types = 1);

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
    $index_storage = $this->entityTypeManager->getStorage('search_api_index');

    // Loop through all available data sources from enabled indexes.
    $indexes = $index_storage->loadByProperties(['status' => 1]);
    foreach ($indexes as $index) {
      $datasources = $index->getDatasources();
      /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
      foreach ($datasources as $datasource) {
        $entity_type = $datasource->getEntityTypeId();
        $bundles = $datasource->getBundles();
<<<<<<< HEAD:src/Plugin/search_api/display/ListDisplayDeriver.php
        foreach ($bundles as $bundle_id => $label) {
          $id = $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $bundle_id;
=======
        foreach ($bundles as $id => $label) {
          $id = 'list_display' . PluginBase::DERIVATIVE_SEPARATOR . $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $id;
>>>>>>> OPENEUROPA-3373: Query service returning from list source.:src/Plugin/facets/display/ListDisplayDeriver.php
          $definition = $base_plugin_definition;
          $definition['label'] = $this->t('List display %id', ['%id' => $id]);
          $definition['description'] = $this->t('List display %id', ['%id' => $id]);
          $definition['index'] = $datasource->getIndex()->id();
          $this->derivatives[$id] = $definition;
        }
      }
    }

    return $this->derivatives;
  }

}
