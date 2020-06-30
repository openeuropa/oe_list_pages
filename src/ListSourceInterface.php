<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\search_api\IndexInterface;

/**
 * Interface for List source class.
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
   * Get search id.
   *
   * @return string
   *   The search id.
   */
  public function getSearchId();

  /**
   * Get the associated index.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search api index.
   */
  public function getIndex(): IndexInterface;

}
