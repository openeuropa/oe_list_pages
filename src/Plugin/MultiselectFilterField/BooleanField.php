<?php

namespace Drupal\oe_list_pages\Plugin\MultiselectFilterField;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\Result;
use Drupal\oe_list_pages\MultiSelectFilterFieldPluginBase;

/**
 * Defines the boolean field type multiselect filter plugin.
 *
 * @PageHeaderMetadata(
 *   id = "boolean",
 *   label = @Translation("Boolean field"),
 *   weight = 100
 * )
 */
class BooleanField extends MultiSelectFilterFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function applies(): bool {
    $field_definition = $this->configuration['field_definition'];
    if (!$field_definition instanceof FieldDefinitionInterface) {
      return FALSE;
    }
    if ($field_definition->getType() === 'boolean') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(): array {
    $field_definition = $this->configuration['field_definition'];
    if (!$field_definition instanceof FieldDefinitionInterface) {
      return [];
    }
    $facet = $this->configuration['facet'];
    if (!$facet instanceof FacetInterface) {
      return [];
    }

    // Create some dummy results for each boolean type (on/off) then process
    // the results to ensure we have display labels.
    $results = [
      new Result($facet, 1, 1, 1),
      new Result($facet, 0, 0, 1),
    ];

    $facet->setResults($results);
    $results = $this->processFacetResults($facet);
    $options = $this->transformResultsToOptions($results);

    return [
      '#type' => 'select',
      '#options' => $options,
      '#empty_option' => $this->t('Select'),
    ];
  }

}
