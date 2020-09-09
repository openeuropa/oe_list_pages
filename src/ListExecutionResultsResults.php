<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;

/**
 * List source execution results value object.
 *
 * Used to store information about query, search api results and original list
 * source.
 */
class ListExecutionResultsResults implements ListExecutionResultsInterface {

  /**
   * The query.
   *
   * @var \Drupal\search_api\Query\QueryInterface
   */
  protected $query;

  /**
   * The resultset interface.
   *
   * @var \Drupal\search_api\Query\ResultSetInterface
   */
  protected $results;

  /**
   * The list source.
   *
   * @var \Drupal\oe_list_pages\ListSourceInterface
   */
  protected $listSource;

  /**
   * The wrapper for entity meta plugin.
   *
   * @var \Drupal\oe_list_pages\ListPageWrapper
   */
  protected $listPluginWrapper;

  /**
   * ListExecution constructor.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The result set.
   * @param \Drupal\oe_list_pages\ListSourceInterface $listSource
   *   The list source.
   * @param \Drupal\oe_list_pages\ListPageWrapper $listPluginWrapper
   *   The wrapper for the entity meta plugin.
   */
  public function __construct(QueryInterface $query, ResultSetInterface $results, ListSourceInterface $listSource, ListPageWrapper $listPluginWrapper) {
    $this->query = $query;
    $this->results = $results;
    $this->listSource = $listSource;
    $this->listPluginWrapper = $listPluginWrapper;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuery(): QueryInterface {
    return $this->query;
  }

  /**
   * {@inheritdoc}
   */
  public function getResults(): ResultSetInterface {
    return $this->results;
  }

  /**
   * {@inheritdoc}
   */
  public function getListSource(): ListSourceInterface {
    return $this->listSource;
  }

  /**
   * {@inheritdoc}
   */
  public function getListPluginWrapper(): ListPageWrapper {
    return $this->listPluginWrapper;
  }

}
