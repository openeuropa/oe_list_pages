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
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new ListFacetSourceDeriver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
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

    $definitions = [];
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
        foreach ($bundles as $id => $label) {

          $id = $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $id;
          $definition = $base_plugin_definition;
          $definition['id'] = $base_plugin_id . PluginBase::DERIVATIVE_SEPARATOR . $id;
          $definition['index'] = $datasource->getIndex()->id();
          $definition['label'] = $this->t('List %bundle', ['%bundle' => $id]);
          $definition['display_id'] = $id;
          $definitions[$id] = $definition;
        }

        $this->derivatives = $definitions;
      }
    }

    return $this->derivatives;
  }

}
