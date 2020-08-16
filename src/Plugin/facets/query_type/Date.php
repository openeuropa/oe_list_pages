<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\query_type;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\facets\QueryType\QueryTypePluginBase;

/**
 * Provides support for Date search.
 *
 * @FacetsQueryType(
 *   id = "date_query_type",
 *   label = @Translation("Date query type"),
 * )
 */
class Date extends QueryTypePluginBase {

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

    // Only alter the query when there's an actual query object to alter.
    if (empty($query) || empty($this->facet->getActiveItems()[0])) {
      return;
    }
    $widget_config = $this->facet->getWidgetInstance()->getConfiguration();

    // Add the filter to the query if there are active values.
    $active_items = $this->facet->getActiveItems();
    if (isset($active_items[1])) {
      $operator = self::OPERATORS[$active_items[0]];
      $timezone = new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE);
      $datetime = new DateTimePlus($active_items[1], $timezone);
      if ($operator === 'BETWEEN' && isset($active_items[2])) {
        $end_datetime = new DrupalDateTime($active_items[2], $timezone);
        if ($widget_config['date_type'] === DateTimeItem::DATETIME_TYPE_DATE) {
          $this->adaptDatesPerOperator($operator, $datetime, $end_datetime);
        }
        $value = [$datetime->getTimestamp(), $end_datetime->getTimestamp()];
      }
      else {
        if ($widget_config['date_type'] === DateTimeItem::DATETIME_TYPE_DATE) {
          $this->adaptDatesPerOperator($operator, $datetime);
        }
        $value = $datetime->getTimestamp();
      }
      $query->addCondition($this->facet->getFieldIdentifier(), $value, $operator);
    }
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
  protected function adaptDatesPerOperator(string $operator, DateTimePlus &$start_date, DateTimePlus &$end_date = NULL): void {
    switch ($operator) {
      case '>':
        // Next day after selected day.
        $start_date->setTime(23, 59, 59);
        break;

      case '<':
        // Previous day.
        $start_date->setTime(0, 0, 0);
        break;

      case 'BETWEEN':
        $start_date->setTime(0, 0, 0);
        $end_date->setTime(23, 59, 59);
        break;
    }
  }

}
