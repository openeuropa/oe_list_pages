<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Plugin\facets\query_type;

use Drupal\facets\QueryType\QueryTypePluginBase;

/**
 * Provides support for Fulltext search.
 *
 * @FacetsQueryType(
 *   id = "fulltext_query_type",
 *   label = @Translation("Fulltext query type"),
 * )
 */
class Fulltext extends QueryTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $query = $this->query;
    // Only alter the query when there's an actual query object to alter.
    if (!empty($query)) {
      $field_identifier = $this->facet->getFieldIdentifier();
      // Add the filter to the query if there are active values.
      $active_items = $this->facet->getActiveItems();
      $widget_config = $this->facet->getWidgetInstance()->getConfiguration();
      foreach ($active_items as $value) {
        // To guarantee several facets can check for keywords.
        if (!empty($query->getKeys())) {
          $query->keys(array_merge($query->getKeys(), [mb_strtolower($value)]));
        }
        else {
          $query->keys(mb_strtolower($value));
        }

        if (isset($widget_config['fulltext_all_fields']) && !$widget_config['fulltext_all_fields']) {
          // Search on specific field.
          $fulltext_fields = $query->getFulltextFields() ?? [];
          $query->setFulltextFields(array_merge([$field_identifier], $fulltext_fields));
        }
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
