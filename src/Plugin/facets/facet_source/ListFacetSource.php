<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Plugin\facets\facet_source;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\facets\Exception\InvalidQueryTypeException;
use Drupal\facets\FacetSource\SearchApiFacetSourceInterface;
use Drupal\facets\Plugin\facets\facet_source\SearchApiBaseFacetSource;
use Drupal\search_api\Display\DisplayPluginManager;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Represents a facet source to be used for lists.
 *
 * @FacetsFacetSource(
 *   id = "list_facet_source",
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
   * The search api index to use.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The display plugin manager.
   *
   * @var \Drupal\search_api\Display\DisplayPluginManager
   */
  protected $displayPluginManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, $query_type_plugin_manager, $search_results_cache, RequestStack $request_stack, CurrentPathStack $current_path_stack, EntityTypeManagerInterface $entity_type_manager, DisplayPluginManager $display_plugin_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $query_type_plugin_manager, $search_results_cache);

    $this->currentPathStack = $current_path_stack;
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    // This needs to be loaded as search api doesn't rely on getIndex().
    $this->index = $this->entityTypeManager->getStorage('search_api_index')->load($plugin_definition['index']);
    $this->displayPluginManager = $display_plugin_manager;
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
      $container->get('path.current'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.search_api.display')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex() {
    return $this->index;
  }

  /**
   * Sets the search_api index.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   */
  public function setIndex(IndexInterface $index): void {
    $this->index = $index;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplay() {
    return $this->displayPluginManager->createInstance($this->pluginDefinition['display_id']);
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
    throw new \Exception(sprintf("Field with name %s does not have a definition", $field_name));
  }

  /**
   * {@inheritdoc}
   */
  public function fillFacetsWithResults(array $facets) {
    $plugin_definition_id = $this->getPluginDefinition()['id'];
    $results = $this->searchApiQueryHelper->getResults($plugin_definition_id);

    $facet_results = $results instanceof ResultSetInterface ? $results->getExtraData('search_api_facets') : [];
    foreach ($facets as $facet) {
      $configuration = [
        'query' => NULL,
        'facet' => $facet,
        'results' => $facet_results[$facet->getFieldIdentifier()] ?? [],
      ];
      try {
        $query_type = $this->queryTypePluginManager->createInstance($facet->getQueryType(), $configuration);
        $query_type->build();
      }
      catch (InvalidQueryTypeException $exception) {
        // Do nothing.
      }

    }
  }

}
