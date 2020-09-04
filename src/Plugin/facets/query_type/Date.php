<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\query_type;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\facets\QueryType\QueryTypePluginBase;
use Drupal\oe_list_pages\Plugin\facets\processor\DateUrlProcessor;

/**
 * Query type plugin that filters the result by the date active filters.
 *
 * @FacetsQueryType(
 *   id = "date_query_type",
 *   label = @Translation("Date query type"),
 * )
 */
class Date extends QueryTypePluginBase {

  /**
   * A map of operators to their SQL counterparts.
   */
  const OPERATORS = [
    'gt' => '>',
    'lt' => '<',
    'bt' => 'BETWEEN',
  ];

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $query = $this->query;

    $active_items = DateUrlProcessor::structureActiveItems($this->facet);

    // Only alter the query when there's an actual query object to alter.
    if (empty($query) || !$active_items) {
      return;
    }

    $widget_config = $this->facet->getWidgetInstance()->getConfiguration();

    $operator = $active_items['operator'];

    $first_date = new DateTimePlus($active_items['first']);
    $second_date = isset($active_items['second']) ? new DateTimePlus($active_items['second']) : NULL;

    // Handle the BETWEEN case first where we have two dates to compare.
    if ($operator === 'bt' && $second_date) {
      if ($widget_config['date_type'] === DateTimeItem::DATETIME_TYPE_DATE) {
        $this->adaptDatesPerOperator($operator, $first_date, $second_date);
      }

      $value = [$first_date->getTimestamp(), $second_date->getTimestamp()];
      $query->addCondition($this->facet->getFieldIdentifier(), $value, static::OPERATORS[$operator]);
      return;
    }

    // Handle the single date comparison.
    if ($widget_config['date_type'] === DateTimeItem::DATETIME_TYPE_DATE) {
      $this->adaptDatesPerOperator($operator, $first_date);
    }

    $query->addCondition($this->facet->getFieldIdentifier(), $first_date->getTimestamp(), static::OPERATORS[$operator]);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->facet;
  }

  /**
   * Adapt date per operator.
   *
   * @param string $operator
   *   The operator.
   * @param \Drupal\Component\Datetime\DateTimePlus $start_date
   *   The start date.
   * @param \Drupal\Component\Datetime\DateTimePlus|null $end_date
   *   The end date.
   */
  protected function adaptDatesPerOperator(string $operator, DateTimePlus $start_date, DateTimePlus $end_date = NULL): void {
    switch ($operator) {
      case 'gt':
        // Next day after selected day.
        $start_date->setTime(23, 59, 59);
        break;

      case 'lt':
        // Previous day.
        $start_date->setTime(0, 0, 0);
        break;

      case 'bt':
        $start_date->setTime(0, 0, 0);
        $end_date->setTime(23, 59, 59);
        break;
    }
  }

}
