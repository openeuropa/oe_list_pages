<?php

namespace Drupal\oe_list_pages\Plugin\facets\query_type;

use Drupal\facets\QueryType\QueryTypePluginBase;
use Drupal\facets\Result\Result;
use Drupal\search_api\Query\QueryInterface;

/**
 * Provides support for date search.
 *
 * @FacetsQueryType(
 *   id = "date_query_type",
 *   label = @Translation("Title"),
 * )
 */
class Date extends QueryTypePluginBase {

  const OPERATORS = [
    'lt' => '<',
    'gt' => '>',
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
      $active_items = explode('|', $this->facet->getActiveItems()[0]);
      if (count($active_items) && isset($active_items[0]) && isset($active_items[1])) {
        $query->addCondition($this->facet->getFieldIdentifier(), strtotime($active_items[1]), self::OPERATORS[$active_items[0]]);
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