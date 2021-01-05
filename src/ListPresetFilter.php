<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

/**
 * List preset filter value object.
 *
 * Used to store preset filter values and operators.
 */
class ListPresetFilter {

  const AND_OPERATOR = 'and';
  const OR_OPERATOR = 'or';
  const NOT_OPERATOR = 'not';

  /**
   * The facet id.
   *
   * @var string
   */
  protected $facetId;

  /**
   * The operator.
   *
   * @var string
   */
  protected $operator;

  /**
   * The values.
   *
   * @var array
   */
  protected $values = [];

  /**
   * The type.
   *
   * @var string
   */
  protected $type;

  /**
   * ListPresetFilter constructor.
   *
   * @param string $facet_id
   *   The facet id.
   * @param array $values
   *   The operator.
   * @param string $operator
   *   The operator.
   * @param string $type
   *   The type.
   */
  public function __construct(string $facet_id, array $values, string $operator = self::OR_OPERATOR, string $type = 'static') {
    $this->facetId = $facet_id;
    $this->operator = $operator;
    $this->values = $values;
    $this->type = $type;
  }

  /**
   * Gets the facet id.
   *
   * @return string
   *   The facet id.
   */
  public function getFacetId(): string {
    return $this->facetId;
  }

  /**
   * Sets the facet id.
   *
   * @param string $facet_id
   *   The facet id.
   */
  public function setFacetId(string $facet_id): void {
    $this->facetId = $facet_id;
  }

  /**
   * Gets the filter operator.
   *
   * @return string
   *   The operator.
   */
  public function getOperator(): string {
    return $this->operator;
  }

  /**
   * Sets the filter operator.
   *
   * @param string $operator
   *   The operator.
   */
  public function setOperator(string $operator): void {
    $this->operator = $operator;
  }

  /**
   * Gets the values.
   *
   * @return array
   *   The filter values.
   */
  public function getValues(): array {
    return $this->values;
  }

  /**
   * Sets the values for the filter.
   *
   * @param array $values
   *   The filter values.
   */
  public function setValues(array $values): void {
    $this->values = $values;
  }

  /**
   * Get available operators.
   *
   * @return array
   *   The available operators.
   */
  public static function getOperators(): array {
    return [
      self::AND_OPERATOR => t('All of'),
      self::OR_OPERATOR => t('Any of'),
      self::NOT_OPERATOR => t('None of'),
    ];
  }

  /**
   * Gets the type.
   *
   * @return string
   *   The type.
   */
  public function getType(): string {
    return $this->type;
  }

  /**
   * Sets the type.
   *
   * @param string $type
   *   The type.
   */
  public function setType(string $type): void {
    $this->type = $type;
  }

}
