<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\Result;
use Drupal\oe_list_pages\ListPresetFilter;

/**
 * The checkbox widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_checkbox",
 *   label = @Translation("List pages checkbox"),
 *   description = @Translation("A checkbox filter widget"),
 * )
 */
class MultiselectCheckboxWidget extends MultiselectWidget implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $results = $this->prepareResults($facet->getResults());
    $options = $this->transformResultsToOptions($results);

    if ($options) {
      $build[$facet->id()] = [
        '#type' => 'checkboxes',
        '#title' => $facet->getName(),
        '#options' => $options,
        '#default_value' => $facet->getActiveItems(),
      ];
    }

    $build['#cache']['contexts'] = [
      'url.query_args',
      'url.path',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareValueForUrl(FacetInterface $facet, array &$form, FormStateInterface $form_state): array {
    $values = $form_state->getValue($facet->id());

    if (!$values) {
      return [];
    }

    if (!is_array($values)) {
      return [$values];
    }

    // Remove 0 (integer) values, that are unchecked checkboxes.
    // "0" (string) can be a value (for boolean fields).
    $parameters = [];
    foreach ($values as $key => $value) {
      if($value !== 0) {
        $parameters[] = $key;
      }
    }
    return $parameters;
  }

}
