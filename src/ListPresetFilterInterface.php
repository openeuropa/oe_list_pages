<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

/**
 * Interface for preset filters in list pages.
 */
interface ListPresetFilterInterface {

  /**
   * Gets the facet id.
   *
   * @return string
   *   The facet id.
   */
  public function getFacetId(): string;

  /**
   * Sets the facet id.
   *
   * @param string $facet_id
   *   The facet id.
   */
  public function setFacetId(string $facet_id);

  /**
   * Gets the filter operator.
   *
   * @return string
   *   The operator.
   */
  public function getOperator(): string;

  /**
   * Sets the filter operator.
   *
   * @param string $operator
   *   The operator.
   */
  public function setOperator(string $operator): void;

  /**
   * Gets the values.
   *
   * @return array
   *   The filter values.
   */
  public function getValues(): array;

  /**
   * Sets the values for the filter.
   *
   * @param array $values
   *   The filter values.
   */
  public function setValues(array $values): void;

  /**
   * Get available operators.
   *
   * @return array
   *   The available operators.
   */
  public static function getOperators(): array;

}
