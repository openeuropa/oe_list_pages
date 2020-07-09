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
        foreach ($bundles as $id => $label) {
          $id = $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $id;
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
