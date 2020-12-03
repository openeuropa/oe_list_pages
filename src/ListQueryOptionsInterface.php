<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

/**
 * Interface for List source query options value object.
 *
 * Used to store list page options that are applied to search api query.
 */
interface ListQueryOptionsInterface {

  /**
   * Gets the ignored filters.
   *
   * @return array
   *   The ignored filters.
   */
  public function getIgnoredFilters(): array;

  /**
   * Sets the ignored filters.
   *
   * @param array $ignoredFilters
   *   The ignored filters.
   */
  public function setIgnoredFilters(array $ignoredFilters): void;

  /**
   * Gets the preset filters.
   *
   * @return \Drupal\oe_list_pages\ListPresetFilter[]
   *   The preset filters.
   */
  public function getPresetFiltersValues(): array;

  /**
   * Sets the preset filters.
   *
   * @param array $presetFiltersValues
   *   The preset filters.
   */
  public function setPresetFiltersValues(array $presetFiltersValues): void;

}
