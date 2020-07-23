<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * List sources are associated with a facet source.
 *
 * They contain all required fields to allow to execute searches on bundles
 * that have associated filterable lists.
 */
class ListSource implements ListSourceInterface {

  /**
   * The search id for the list source.
   *
   * @var string
   */
  protected $searchId;

  /**
   * The entity type.
   *
   * @var string
   */
  protected $entityType;

  /**
   * The bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The associated search api index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * The list of available filters.
   *
   * @var array
   */
  protected $filters;

  /**
   * The bundle field id.
   *
   * @var string
   */
  protected $bundleFieldId;

  /**
   * ListSource constructor.
   *
   * @param string $search_id
   *   The id.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $bundle_field_id
   *   The bundle field id.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search api index.
   * @param array $filters
   *   The filters.
   */
  public function __construct(string $search_id, string $entity_type, string $bundle, string $bundle_field_id, IndexInterface $index, array $filters) {
    $this->searchId = $search_id;
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
    $this->bundleFieldId = $bundle_field_id;
    $this->index = $index;
    $this->filters = $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableFilters(): array {
    return $this->filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle(): string {
    return $this->bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(): string {
    return $this->entityType;
  }

  /**
   * {@inheritdoc}
   */
  public function getSearchId(): string {
    return $this->searchId;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundleFieldId(): string {
    return $this->bundleFieldId;
  }

  /**
   * {@inheritdoc}
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery(int $limit = 10, int $page = 0, array $ignored_filters = [], array $preset_filters_values = []): QueryInterface {

    $query = $this->index->query([
      'limit' => $limit,
      'offset' => ($limit * $page),
    ]);
    $query_options = new ListQueryOptions($ignored_filters, $preset_filters_values);
    $query->setOption('oe_list_page_query_options', $query_options);
    $query->setSearchId($this->getSearchId());
    $query->addCondition($this->getBundleFieldId(), $this->getBundle());
    $query->addCondition('search_api_datasource', 'entity:' . $this->getEntityType());
    return $query;
  }

}
