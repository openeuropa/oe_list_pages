<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\facet_source;

use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\FacetSource\FacetSourceDeriverBase;

/**
 * Derives a facet source plugin definition for every indexed content bundle.
 */
class ListFacetSourceDeriver extends FacetSourceDeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
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
        foreach ($bundles as $bundle_id => $label) {

          $id = $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $bundle_id;
          $definition = $base_plugin_definition;
          $definition['index'] = $datasource->getIndex()->id();
          $definition['label'] = $this->t('List %bundle', ['%bundle' => $id]);
          // We use as the display ID the same ID as this derivative.
          // @see \Drupal\oe_list_pages\Plugin\search_api\display\ListDisplayDeriver
          $definition['display_id'] = 'oe_list_pages' . PluginBase::DERIVATIVE_SEPARATOR . $id;
          $this->derivatives[$id] = $definition;
        }

      }
    }

    return $this->derivatives;
  }

}
