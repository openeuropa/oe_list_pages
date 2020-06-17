<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\facet_source;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\facets\FacetSource\SearchApiFacetSourceInterface;
use Drupal\facets\Plugin\facets\facet_source\SearchApiBaseFacetSource;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Represents a facet source to be used for lists.
 *
 * @FacetsFacetSource(
 *   id = "list_facet_source",
 *   display_id = "oe_list_pages",
 *   label = "OE List Pages",
 *   deriver = "Drupal\oe_list_pages\Plugin\facets\facet_source\ListFacetSourceDeriver"
 * )
 */
class ListFacetSource extends SearchApiBaseFacetSource implements SearchApiFacetSourceInterface {

  /**
   * The current path stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPathStack;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, $query_type_plugin_manager, $search_results_cache, RequestStack $request_stack, CurrentPathStack $current_path_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $query_type_plugin_manager, $search_results_cache);

    $this->currentPathStack = $current_path_stack;
    $this->requestStack = $request_stack;
    $index = $plugin_definition['index'];
    $this->index = Index::load($index);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.facets.query_type'),
      $container->get('search_api.query_helper'),
      $container->get('request_stack'),
      $container->get('path.current')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplay() {
    return $this->getPluginDefinition()['display_id'];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getViewsDisplay() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    return $this->currentPathStack->getPath();
  }

  /**
   * {@inheritdoc}
   */
  public function isRenderedInCurrentRequest() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataDefinition($field_name) {
    $field = $this->getIndex()->getField($field_name);
    if ($field) {
      return $field->getDataDefinition();
    }
    throw new \Exception("Field with name {$field_name} does not have a definition");
  }

  /**
   * {@inheritdoc}
   */
  public function fillFacetsWithResults(array $facets) {

  }

}
