<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\facets\FacetInterface;

/**
 * The date widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_date",
 *   label = @Translation("List pages date"),
 *   description = @Translation("A date filter widget."),
 * )
 */
class ListPagesDateWidget extends ListPagesWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $date_type = $facet->getWidgetInstance()->getConfiguration()['date_type'];

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
      '#type' => $date_type,
      '#title' => $this->t('Date'),
      '#default_value' => $this->getValueFromActiveFilters($facet, '1'),
    ];

    // In any cases, we are going to ignore value of end_date,
    // if operator is not 'In between'.
    $end_date_value = NULL;
    if ($this->getValueFromActiveFilters($facet, '0') === 'bt') {
      $end_date_value = $this->getValueFromActiveFilters($facet, '2') ?? NULL;
    }

    $build[$facet->id() . '_end_date'] = [
      '#type' => $date_type,
      '#title' => $this->t('End Date'),
      '#default_value' => $end_date_value,
      '#states' => [
        // Doesn't work for form element datetime.
        // @todo Fix or prepare patch for datetime type.
        // @see https://www.drupal.org/project/drupal/issues/2419131
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

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'date_type' => DateTimeItem::DATETIME_TYPE_DATE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $form['date_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Date type'),
      '#options' => [
        DateTimeItem::DATETIME_TYPE_DATE => $this->t('Date only'),
        DateTimeItem::DATETIME_TYPE_DATETIME => $this->t('Date and time'),
      ],
      '#description' => $this->t('Choose the type of date to filter.'),
      '#default_value' => $this->getConfiguration()['date_type'],
    ];

    return $form;
  }

}
