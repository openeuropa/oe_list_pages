<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;

/**
 * The multiselect list widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_multiselect",
 *   label = @Translation("List pages multislect"),
 *   description = @Translation("A multislect search widget."),
 * )
 */
class MultiselectWidget extends ListPagesWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $results = $facet->getResults();

    $options = [];
    array_walk($results, function (ResultInterface &$result) use (&$options) {
      $options[$result->getRawValue()] = $result->getDisplayValue();
    });

    if ($options) {
      $build[$facet->id()] = [
        '#type' => 'select',
        '#title' => $facet->getName(),
        '#options' => $options,
        '#multiple' => TRUE,
        '#default_value' => $facet->getActiveItems(),
      ];
    }

    $build['#cache']['contexts'] = [
      'url.query_args',
      'url.path',
    ];

    return $build;
  }

}
