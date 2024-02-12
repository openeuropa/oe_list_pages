<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;

/**
 * Interface for ListExecution results value object.
 *
 * Used to store information about query, search api results and original list
 * source.
 */
interface ListExecutionResultsInterface {

  /**
   * Gets the Query.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The query.
   */
  public function getQuery(): QueryInterface;

  /**
   * Gets the result set.
   *
   * @return Drupal\search_api\Query\ResultSetInterface
   *   The result set interface.
   */
  public function getResults(): ResultSetInterface;

  /**
   * Gets the list source.
   *
   * @return \Drupal\oe_list_pages\ListSourceInterface
   *   The list source.
   */
  public function getListSource(): ListSourceInterface;

  /**
   * Returns the list page configuration.
   *
   * @return \Drupal\oe_list_pages\ListPageConfiguration
   *   The list page configuration.
   */
  public function getConfiguration(): ListPageConfiguration;

}
