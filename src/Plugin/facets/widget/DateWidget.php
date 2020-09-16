<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\FacetInterface;
use Drupal\oe_list_pages\Plugin\facets\query_type\Date;

/**
 * The date widget that allows to filter by one or two dates.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_date",
 *   label = @Translation("List pages date"),
 *   description = @Translation("A date filter widget."),
 * )
 */
class DateWidget extends ListPagesWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'date_type' => Date::DATETIME_TYPE_DATE,
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
        Date::DATETIME_TYPE_DATE => $this->t('Date only'),
        Date::DATETIME_TYPE_DATETIME => $this->t('Date and time'),
      ],
      '#description' => $this->t('Choose the type of date to filter.'),
      '#default_value' => $this->getConfiguration()['date_type'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $date_type = $facet->getWidgetInstance()->getConfiguration()['date_type'];

    $operators = [
      'gt' => $this->t('After'),
      'lt' => $this->t('Before'),
      'bt' => $this->t('In between'),
    ];

    $build[$facet->id() . '_op'] = [
      '#type' => 'select',
      '#title' => $facet->getName(),
      '#options' => $operators,
      '#default_value' => $this->getValueFromActiveFilters($facet, '0'),
      '#empty_option' => $this->t('Select'),
    ];

    $build[$facet->id() . '_first_date_wrapper'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          [
            ':input[name="' . $facet->id() . '_op"]' => [
              'value' => 'lt',
            ],
          ],
          [
            ':input[name="' . $facet->id() . '_op"]' => [
              'value' => 'gt',
            ],
          ],
          [
            ':input[name="' . $facet->id() . '_op"]' => [
              'value' => 'bt',
            ],
          ],
        ],
      ],
    ];

    $first_date_default = $this->getValueFromActiveFilters($facet, '1');
    $first_date_default = $first_date_default ? new DrupalDateTime($first_date_default) : NULL;

    $build[$facet->id() . '_first_date_wrapper'][$facet->id() . '_first_date'] = [
      '#type' => 'datetime',
      '#date_date_element' => 'date',
      '#date_time_element' => $date_type === 'date' ? 'none' : 'time',
      '#title' => $this->t('Date'),
      '#default_value' => $first_date_default,
    ];

    // We only care about the second date if the operator is "bt".
    $second_date_default = NULL;
    if ($this->getValueFromActiveFilters($facet, '0') === 'bt') {
      $second_date_default = $this->getValueFromActiveFilters($facet, '2');
      $second_date_default = new DrupalDateTime($second_date_default);
    }

    $build[$facet->id() . '_second_date_wrapper'] = [
      '#type' => 'container',
      // We only show the second date if the operator is "bt".
      '#states' => [
        'visible' => [
          ':input[name="' . $facet->id() . '_op"]' => [
            'value' => 'bt',
          ],
        ],
      ],
    ];

    $build[$facet->id() . '_second_date_wrapper'][$facet->id() . '_second_date'] = [
      '#type' => 'datetime',
      '#date_date_element' => 'date',
      '#date_time_element' => $date_type === 'date' ? 'none' : 'time',
      '#title' => $this->t('End Date'),
      '#default_value' => $second_date_default,
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
      'first_date' => $facet->id() . '_first_date',
      'second_date' => $facet->id() . '_second_date',
    ];

    $values = [];
    $operator = $form_state->getValue($value_keys['operator'], NULL);
    if (!$operator) {
      return [];
    }

    $values[] = $operator;
    if ($operator !== 'bt') {
      unset($value_keys['second_date']);
    }
    unset($value_keys['operator']);

    foreach ($value_keys as $key) {
      $value = $form_state->getValue($key);
      if (!$value) {
        continue;
      }

      if (!$value instanceof DrupalDateTime) {
        $value = new DrupalDateTime($value);
      }

      $values[] = $value->format(\DateTimeInterface::ATOM);
    }

    if (count($values) === 1) {
      // If we only have the operator, it means no dates have been specified.
      return [];
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
