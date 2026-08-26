<?php

declare(strict_types=1);

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
   * @param array $options
   *   An array of options.
   *   Use 'limit' for the number of items per page.
   *   If 'limit' is NULL, a default limit will be used.
   *   If 'limit' is zero, every page will have all items.
   *   Use 'page' for the page number, starting from zero.
   *   if 'page' is NULL, it will fall back to zero.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The search api query.
   */
  public function getQuery(array $options = []): QueryInterface;

}
