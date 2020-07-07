<?php

namespace Drupal\oe_list_pages\Plugin\facets\query_type;

use Drupal\facets\QueryType\QueryTypePluginBase;
use Drupal\facets\Result\Result;
use Drupal\search_api\Query\QueryInterface;

/**
 * Provides support for Title search.
 *
 * @FacetsQueryType(
 *   id = "title_query_type",
 *   label = @Translation("Title"),
 * )
 */
class Title extends QueryTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $query = $this->query;

    // Only alter the query when there's an actual query object to alter.
    if (!empty($query)) {
      $operator = $this->facet->getQueryOperator();
      $field_identifier = $this->facet->getFieldIdentifier();

      // Add the filter to the query if there are active values.
      $active_items = $this->facet->getActiveItems();

      if (count($active_items)) {
        foreach ($active_items as $value) {
          $query->keys($value);
          $query->setFulltextFields([$field_identifier]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
  }
}