<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\query_type;

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
    'lt' => '<',
    'gt' => '>',
    'bt' => 'BETWEEN',
  ];

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $query = $this->query;

    // Only alter the query when there's an actual query object to alter.
    if (!empty($query)) {
      if (empty($this->facet->getActiveItems()[0])) {
        return;
      }
      // Add the filter to the query if there are active values.
      $active_items = $this->facet->getActiveItems();
      if (count($active_items) && isset($active_items[0]) && isset($active_items[1])) {
        $operator = self::OPERATORS[$active_items[0]];
        $value = strtotime($active_items[1]);
        if ($operator === 'BETWEEN' && isset($active_items[2])) {
          $value = [$value, strtotime($active_items[2])];
        }
        $query->addCondition($this->facet->getFieldIdentifier(), $value, $operator);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->facet;
  }

}
