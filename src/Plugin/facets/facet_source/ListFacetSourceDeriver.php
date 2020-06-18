<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\facet_source;

use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\FacetSource\FacetSourceDeriverBase;
use Drupal\oe_list_pages\ListManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives a facet source plugin definition for every indexed content bundle.
 */
class ListFacetSourceDeriver extends FacetSourceDeriverBase {

  /**
   * The list manager.
   *
   * @var \Drupal\oe_list_pages\ListManager
   */
  private $listManager;

  /**
   * Constructs a new ListFacetDeriver.
   *
   * @param \Drupal\oe_list_pages\ListManager $listManager
   *   The list manager.
   */
  public function __construct(ListManager $listManager) {
    $this->listManager = $listManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('oe_list_pages.list_manager')
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

    $this->derivatives[$base_plugin_id] = [];

    // Loop through all available data sources from enabled indexes.
    $lists = $this->listManager->getAvailableLists();
    /** @var \Drupal\oe_list_pages\ListSource $list */
    foreach ($lists as $list) {
      $id = $list->getEntityType() . PluginBase::DERIVATIVE_SEPARATOR . $list->getBundle();
      $plugin_derivatives[$id] = [
        'id' => $base_plugin_id . PluginBase::DERIVATIVE_SEPARATOR . $id,
        'index' => $list->getDataSource()->getIndex()->id(),
        'label' => $this->t('List %content_type', ['%content_type' => $list->getBundle()]),
        'display_id' => $id,
      ] + $base_plugin_definition;;

      $this->derivatives[$base_plugin_id] = $plugin_derivatives;
    }

    return $this->derivatives[$base_plugin_id];
  }

}