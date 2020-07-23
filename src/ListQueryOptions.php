<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

/**
 * List source query options value object.
 *
 * Used to store list options that are applied to search api query.
 */
class ListQueryOptions implements ListQueryOptionsInterface {

  /**
   * The ignored filters.
   *
   * @var array
   */
  protected $ignoredFilters = [];

  /**
   * The preset filters.
   *
   * @var array
   */
  protected $presetFiltersValues = [];

  /**
   * ListQueryOptions constructor.
   *
   * @param array $ignored_filters
   *   The ignored filters.
   * @param array $preset_filters
   *   The preset filters.
   */
  public function __construct(array $ignored_filters, array $preset_filters) {
    $this->ignoredFilters = $ignored_filters;
    $this->presetFiltersValues = $preset_filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getIgnoredFilters(): array {
    return $this->ignoredFilters;
  }

  /**
   * {@inheritdoc}
   */
  public function setIgnoredFilters(array $ignoredFilters): void {
    $this->ignoredFilters = $ignoredFilters;
  }

  /**
   * {@inheritdoc}
   */
  public function getPresetFiltersValues(): array {
    return $this->presetFiltersValues;
  }

  /**
   * {@inheritdoc}
   */
  public function setPresetFiltersValues(array $presetFiltersValues): void {
    $this->presetFiltersValues = $presetFiltersValues;
  }

}
