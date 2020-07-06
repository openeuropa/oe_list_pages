<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\IndexInterface;

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
   *   The bundle
   */
  public function getBundle(): string;

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

}
