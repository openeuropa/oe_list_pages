<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;

/**
 * The date widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_date",
 *   label = @Translation("List pages date"),
 *   description = @Translation("A date search widget."),
 * )
 */
class ListPagesDateWidget extends ListPagesWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $operators = [
      'gt' => $this->t('Greater than'),
      'lt' => $this->t('Lower than'),
      'bt' => $this->t('In between'),
    ];

    $build[$facet->id() . '_op'] = [
      '#type' => 'select',
      '#title' => $facet->getName(),
      '#options' => $operators,
      '#default_value' => $this->getValueFromActiveFilters($facet, '0'),
    ];

    $build[$facet->id() . '_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Date'),
      '#format' => 'm/d/Y',
      '#default_value' => $this->getValueFromActiveFilters($facet, '1'),
    ];

    $build[$facet->id() . '_end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('End Date'),
      '#format' => 'm/d/Y',
      '#default_value' => $this->getValueFromActiveFilters($facet, '2') ?? NULL,
      '#states' => [
        'visible' => [
          ':input[name="' . $facet->id() . '_op"]' => [
            'value' => 'bt',
          ],
        ],
      ],
    ];

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
    $value_keys = [
      'operator' => $facet->id() . '_op',
      'date' => $facet->id() . '_date',
      'end_date' => $facet->id() . '_end_date',
    ];
    $values = [];
    foreach ($value_keys as $key) {
      if ($key === $value_keys['end_date'] && $form_state->getValue($value_keys['operator'], NULL) !== 'bt') {
        continue;
      }
      $values[] = $form_state->getValue($key, NULL);
    }

    return [implode('|', $values)];
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'date_comparison';
  }

}
