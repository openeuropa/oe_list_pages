<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Defines the interface for list source implementations.
 */
interface ListSourceInterface {

  /**
   * Get available filters for the list source.
   *
   * @return array
   *   The filters.
   */
  public function getAvailableFilters(): array;

  /**
   * Gets the bundle.
   *
   * @return string
   *   The bundle.
   */
  public function getBundle(): string;

  /**
   * Gets the bundle key.
   *
   * @return string
   *   The bundle key.
   */
  public function getBundleKey(): string;

  /**
   * Gets the entity type.
   *
   * @return string
   *   The entity type.
   */
  public function getEntityType(): string;

  /**
   * Gets the search id.
   *
   * @return string
   *   The search id.
   */
  public function getSearchId();

  /**
   * Gets the associated index.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search api index.
   */
  public function getIndex(): IndexInterface;

  /**
   * Gets the query.
   *
   * @param int $limit
   *   The query limit.
   * @param int $page
   *   The query offset.
   * @param array $sort
   *   Sorting criteria.
   * @param array $ignored_filters
   *   Ignored filter keys.
   * @param array $preset_filters
   *   Preset filter values.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The search api query.
   */
  public function getQuery(int $limit = 10, int $page = 0, array $sort = [], array $ignored_filters = [], array $preset_filters = []): QueryInterface;

}
