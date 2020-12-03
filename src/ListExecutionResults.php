<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;

/**
 * Default list execution results implementation.
 */
class ListExecutionResults implements ListExecutionResultsInterface {

  /**
   * The query.
   *
   * @var \Drupal\search_api\Query\QueryInterface
   */
  protected $query;

  /**
   * The Search API results.
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
   * The list page configuration.
   *
   * @var \Drupal\oe_list_pages\ListPageConfiguration
   */
  protected $configuration;

  /**
   * ListExecutionResults constructor.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param \Drupal\search_api\Query\ResultSetInterface $results
   *   The result set.
   * @param \Drupal\oe_list_pages\ListSourceInterface $listSource
   *   The list source.
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The list page configuration.
   */
  public function __construct(QueryInterface $query, ResultSetInterface $results, ListSourceInterface $listSource, ListPageConfiguration $configuration) {
    $this->query = $query;
    $this->results = $results;
    $this->listSource = $listSource;
    $this->configuration = $configuration;
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
  public function getConfiguration(): ListPageConfiguration {
    return $this->configuration;
  }

}
