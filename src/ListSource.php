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
   * The data source.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $dataSource;

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
   * ListSource constructor.
   *
   * @param string $search_id
   *   The id.
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search api index.
   * @param array $filters
   *   The filters.
   */
  public function __construct(string $search_id, string $entity_type, string $bundle, IndexInterface $index, array $filters) {
    $this->searchId = $search_id;
    $this->entityType = $entity_type;
    $this->bundle = $bundle;
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
  public function getIndex(): IndexInterface {
    return $this->index;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery($limit = 10, $page = 0): QueryInterface {

    $query = $this->index->query([
      'limit' => $limit,
      'offset' => ($limit * $page),
    ]);

    $query->setOption('hernani', 'dois');

    $query->setSearchId($this->getSearchId());
    return $query;
  }

}
