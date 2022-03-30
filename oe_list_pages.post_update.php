<?php

/**
 * @file
 * OE List Pages post updates.
 */

declare(strict_types = 1);

use Drupal\facets\Entity\Facet;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\search_api\Datasource\DatasourceInterface;

/**
 * Installs the new dependencies.
 */
function oe_list_pages_post_update_0001() {
  \Drupal::service('module_installer')->install([
    'facets',
    'search_api',
  ]);
}

/**
 * Installs Multivalue Form Element.
 */
function oe_list_pages_post_update_0002() {
  \Drupal::service('module_installer')->install([
    'multivalue_form_element',
  ]);
}

/**
 * Removes the date_processor_handler from existing facets.
 */
function oe_list_pages_post_update_0003() {
  $facets = Facet::loadMultiple();
  foreach ($facets as $facet) {
    $processors = $facet->getProcessorConfigs();
    if (!isset($processors['date_processor_handler'])) {
      continue;
    }

    $facet->removeProcessor('date_processor_handler');
    $facet->save();
  }
}

/**
 * Marks the indexes as being used for list pages.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 * @SuppressWarnings(PHPMD.NPathComplexity)
 */
function oe_list_pages_post_update_0004() {
  // In order to prevent BC problems, we need to use the same logic as was
  // in the list source factory to determine which of the indexes we should
  // mark.
  /** @var \Drupal\search_api\Entity\SearchApiConfigEntityStorage $storage_index */
  $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');

  // Loop through all available data sources from enabled indexes.
  $indexes = $index_storage->loadByProperties(['status' => 1]);
  $keyed_indexes = [];

  $is_bundle_indexed = function (DatasourceInterface $datasource, string $bundle) {
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
  };

  /** @var \Drupal\search_api\Entity\Index $index */
  foreach ($indexes as $index) {
    $datasources = $index->getDatasources();
    /** @var \Drupal\search_api\Datasource\DatasourceInterface $datasource */
    foreach ($datasources as $datasource) {
      $entity_type = $datasource->getEntityTypeId();
      $bundles = $datasource->getBundles();
      foreach ($bundles as $bundle => $label) {
        // In case not all bundles are indexed.
        if (!$is_bundle_indexed($datasource, $bundle)) {
          continue;
        }

        $id = ListSourceFactory::generateFacetSourcePluginId($entity_type, $bundle);
        $keyed_indexes[$id] = $index;
      }
    }
  }

  $indexes = [];
  foreach ($keyed_indexes as $index) {
    $indexes[$index->id()] = $index;
  }

  foreach ($indexes as $index) {
    $index->setThirdPartySetting('oe_list_pages', 'lists_pages_index', TRUE);
    $index->save();
  }
}
