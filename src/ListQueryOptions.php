<?php

declare(strict_types=1);

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
   * Extra configuration from the list page.
   *
   * @var array
   */
  protected $extra;

  /**
   * ListQueryOptions constructor.
   *
   * @param array $ignored_filters
   *   The ignored filters.
   * @param array $preset_filters
   *   The preset filters.
   * @param array $extra
   *   Extra configuration from the list page.
   */
  public function __construct(array $ignored_filters, array $preset_filters, array $extra) {
    $this->ignoredFilters = $ignored_filters;
    $this->presetFiltersValues = $preset_filters;
    $this->extra = $extra;
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

  /**
   * Returns the extra configuration from the list page.
   *
   * @return array
   *   Extra configuration from the list page.
   */
  public function getExtra(): array {
    return $this->extra;
  }

  /**
   * Sets the extra configuration from the list page.
   *
   * @param array $extra
   *   Extra configuration from the list page.
   */
  public function setExtra(array $extra): void {
    $this->extra = $extra;
  }

}
