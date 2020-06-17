<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\facet_source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\FacetSource\FacetSourceDeriverBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives a facet source plugin definition for every indexed content bundle.
 */
class ListFacetSourceDeriver extends FacetSourceDeriverBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ListFacetDeriver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $base_plugin_id = $base_plugin_definition['id'];

    if (isset($this->derivatives[$base_plugin_id])) {
      return $this->derivatives[$base_plugin_id];
    }
    /** @var \Drupal\search_api\Entity\SearchApiConfigEntityStorage $storage_index */
    $storage_index = $this->entityTypeManager->getStorage('search_api_index');

    // Loop through all available data sources from enabled indexes.
    $indexes = $storage_index->loadByProperties(['status' => 1]);
    foreach ($indexes as $index) {
      $datasources = $index->getDatasources();
      foreach ($datasources as $datasource) {
        $entity_type = $datasource->getEntityTypeId();
        $bundles = $datasource->getBundles();
        foreach ($bundles as $id => $label) {

          // In case not all bundles are indexed:
          if (!empty($datasource->getConfiguration()['bundles']['selected']) && !in_array($id, $datasource->getConfiguration()['bundles']['selected'])) {
            continue;
          }

          $id = $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $id;
          $plugin_derivatives[$id] = [
            'id' => $base_plugin_id . PluginBase::DERIVATIVE_SEPARATOR . $id,
            'index' => $index->id(),
            'label' => $this->t('List %content_type', ['%content_type' => $label]),
            'display_id' => $id,
          ] + $base_plugin_definition;;

          $this->derivatives[$base_plugin_id] = $plugin_derivatives;
        }
      }
    }

    return $this->derivatives[$base_plugin_id];
  }

}
