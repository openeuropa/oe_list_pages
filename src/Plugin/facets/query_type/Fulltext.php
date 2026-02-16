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
    // @phpstan-ignore-next-line
    if (!empty($query)) {
      $field_identifier = $this->facet->getFieldIdentifier();
      // Add the filter to the query if there are active values.
      $active_items = $this->facet->getActiveItems();
      // For unstemmed text fields, we can add the condition directly.
      $field_type = $this->facet->getFacetSource()->getIndex()->getField($field_identifier)->getType();
      $backend_id = $this->facet->getFacetSource()->getIndex()->getServerInstance()->getBackendId();
      if (!empty($active_items) && $field_type === 'solr_text_unstemmed') {
        $query->addCondition($field_identifier, $active_items);
        return;
      }
      $widget_config = $this->facet->getWidgetInstance()->getConfiguration();
      foreach ($active_items as $value) {
        // To guarantee several facets can check for keywords.
        if (in_array($backend_id, ['search_api_solr', 'search_api_db'])) {
          $value = mb_strtolower($value);
        }
        if (!empty($query->getKeys())) {
          $query->keys(array_merge($query->getKeys(), [$value]));
        }
        else {
          $query->keys($value);
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
