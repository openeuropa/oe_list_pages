<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\MultiselectFilterField;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\Result;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\MultiSelectFilterFieldPluginBase;

/**
 * Defines the boolean field type multiselect filter plugin.
 *
 * @MultiselectFieldFilter(
 *   id = "boolean",
 *   label = @Translation("Boolean field"),
 *   field_types = {
 *     "boolean",
 *   },
 *   weight = 100
 * )
 */
class BooleanField extends MultiSelectFilterFieldPluginBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(): array {
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

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(ListPresetFilter $filter): string {
    $facet = $this->configuration['facet'];
    if (!$facet instanceof FacetInterface) {
      return '';
    }
    $results = [
      new Result($facet, 1, 1, 1),
      new Result($facet, 0, 0, 1),
    ];

    $facet->setResults($results);
    return $this->getDefaultFilterValuesLabel($facet, $filter);
  }

}
