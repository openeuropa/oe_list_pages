<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Plugin\facets\query_type;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\facets\FacetInterface;
use Drupal\facets\QueryType\QueryTypePluginBase;
use Drupal\facets\Result\Result;

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
   * Value for the 'datetime_type' setting: store only a date.
   */
  const DATETIME_TYPE_DATE = 'date';

  /**
   * Value for the 'datetime_type' setting: store a date and time.
   */
  const DATETIME_TYPE_DATETIME = 'datetime';

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
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function execute() {
    $query = $this->query;

    $active_items = static::getActiveItems($this->facet);

    // Only alter the query when there's an actual query object to alter.
    if (empty($query) || !$active_items) {
      return;
    }

    $widget_config = $this->facet->getWidgetInstance()->getConfiguration();

    $operator = $active_items['operator'];

    $first_date = $active_items['first'];
    $second_date = $active_items['second'] ?? NULL;

    // Handle the BETWEEN case first where we have two dates to compare.
    if ($operator === 'bt' && $second_date) {
      if ($widget_config['date_type'] === self::DATETIME_TYPE_DATE) {
        $this->adaptDatesPerOperator($operator, $first_date, $second_date);
      }

      $value = [$first_date->getTimestamp(), $second_date->getTimestamp()];
      $query->addCondition($this->facet->getFieldIdentifier(), $value, static::OPERATORS[$operator]);
      return;
    }

    // Handle the single date comparison.
    if ($widget_config['date_type'] === self::DATETIME_TYPE_DATE) {
      $this->adaptDatesPerOperator($operator, $first_date);
    }

    $query->addCondition($this->facet->getFieldIdentifier(), $first_date->getTimestamp(), static::OPERATORS[$operator]);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $facet_results = [];
    $active_filters = Date::getActiveItems($this->facet);

    if (!$active_filters) {
      return $this->facet;
    }

    $operator = $active_filters['operator'];
    $first_date = $active_filters['first'];

    $operators = [
      'gt' => $this->t('After'),
      'lt' => $this->t('Before'),
      'bt' => $this->t('Between'),
    ];

    if (!isset($operators[$operator])) {
      return $facet_results;
    }

    if ($operator === 'bt') {
      $second_date = $active_filters['second'];
      $display = new FormattableMarkup('@operator @first and @second', [
        '@operator' => $operators[$operator],
        '@first' => $first_date->format('j F Y'),
        '@second' => $second_date->format('j F Y'),
      ]);
      $result = new Result($this->facet, $active_filters['_raw'], $display, 0);
      $facet_results[] = $result;
      $this->facet->setResults($facet_results);
      return $facet_results;
    }

    $display = new FormattableMarkup('@operator @first', [
      '@operator' => $operators[$operator],
      '@first' => $first_date->format('j F Y'),
    ]);
    $result = new Result($this->facet, $active_filters['_raw'], $display, 0);
    $facet_results[] = $result;

    $this->facet->setResults($facet_results);

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
  protected function adaptDatesPerOperator(string $operator, DateTimePlus $start_date, ?DateTimePlus $end_date = NULL): void {
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

  /**
   * Prepares the active items from this facet.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   *
   * @return array
   *   The array of structured filter values.
   */
  public static function getActiveItems(FacetInterface $facet) {
    $active_filters = $facet->getActiveItems();
    if (!$active_filters) {
      return [];
    }
    // The date facet comes with an active filter in the form of gt|2020-08-21
    // or bt|2020-08-21|2020-08-23. So we need to explode this and structure
    // this information so that the query plugin and widget can better
    // understand it.
    $active_items = [];
    // Normally we should only have one filter.
    $active_filter_values = explode('|', $active_filters[0]);
    foreach ($active_filter_values as $value) {
      $active_items[] = $value;
    }

    if (!isset($active_items[1])) {
      // Normally should not happen, at least 1 filter value needs to be
      // present so we behave as no filter exists.
      return [];
    }

    $items = [
      'operator' => $active_items[0],
      'first' => $active_items[1],
    ];

    $first_date = new DrupalDateTime($active_items[1]);
    if (!$first_date instanceof DrupalDateTime || $first_date->hasErrors()) {
      // If a wrong first date is passed, we have an invalid filter.
      return [];
    }

    $items['first'] = $first_date;
    // Add also the raw representation of the active filter so it can be used
    // by others.
    $items['_raw'] = $active_filters[0];

    if (!isset($active_items[2])) {
      return $items;
    }

    $second_date = new DrupalDateTime($active_items[2]);
    if (!$second_date instanceof DrupalDateTime || $second_date->hasErrors()) {
      // If a wrong second date is passed, we have an invalid filter.
      return [];
    }

    $items['second'] = $second_date;

    return $items;
  }

}
