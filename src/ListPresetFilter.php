<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages;

/**
 * List preset filter value object.
 *
 * Used to store preset filter values and operators.
 */
class ListPresetFilter implements ListPresetFilterInterface {

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
   * ListPresetFilter constructor.
   *
   * @param string $facet_id
   *   The facet id.
   * @param array $values
   *   The operator.
   * @param string $operator
   *   The operator.
   */
  public function __construct(string $facet_id, array $values, string $operator = self::OR_OPERATOR) {
    $this->facetId = $facet_id;
    $this->operator = $operator;
    $this->values = $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getFacetId(): string {
    return $this->facetId;
  }

  /**
   * {@inheritdoc}
   */
  public function setFacetId(string $facet_id): void {
    $this->facetId = $facet_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperator(): string {
    return $this->operator;
  }

  /**
   * {@inheritdoc}
   */
  public function setOperator(string $operator): void {
    $this->operator = $operator;
  }

  /**
   * {@inheritdoc}
   */
  public function getValues(): array {
    return $this->values;
  }

  /**
   * {@inheritdoc}
   */
  public function setValues(array $values): void {
    $this->values = $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function getOperators(): array {
    return [
      self::AND_OPERATOR => t('All of'),
      self::OR_OPERATOR => t('Any of'),
      self::NOT_OPERATOR => t('None of'),
    ];
  }

}
