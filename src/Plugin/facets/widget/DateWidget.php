<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\facets\FacetInterface;
use Drupal\oe_list_pages\ListPresetFilter;
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
class DateWidget extends ListPagesWidgetBase implements TrustedCallbackInterface {

  /**
   * The ID of the facet.
   *
   * @var string
   */
  protected $facetId;

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
  public function buildDefaultValueForm(array $form, FormStateInterface $form_state, FacetInterface $facet, ListPresetFilter $preset_filter = NULL): array {
    if ($preset_filter) {
      $facet->setActiveItems($preset_filter->getValues());
    }
    $build = $this->doBuildDateWidgetElements($facet, $form, $form_state);
    $build[$facet->id() . '_op']['#required'] = TRUE;
    $build[$facet->id() . '_first_date_wrapper'][$facet->id() . '_first_date']['#required'] = TRUE;
    $build['#element_validate'] = [[$this, 'validateDefaultValueForm']];
    $this->facetId = $facet->id();
    return $build;
  }

  /**
   * Validation handler for the date form elements.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateDefaultValueForm(array $element, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element) {
      return;
    }

    $facet_id = $this->facetId;
    $parents = array_slice($triggering_element['#parents'], 0, -1);
    $values = $form_state->getValue($parents);
    $operator = $values[$facet_id . '_op'] ?? NULL;
    if (!$operator) {
      return;
    }

    if ($operator !== 'bt') {
      return;
    }

    // Check that if we select BETWEEN, we have two dates.
    $second_date = $values[$facet_id . '_second_date_wrapper'][$facet_id . '_second_date'];
    if (!$second_date) {
      $form_state->setError($element[$facet_id . '_second_date_wrapper'][$facet_id . '_second_date'], $this->t('The second date is required.'));
    }

    // Check that if we select BETWEEN, the second date is after the first one.
    $first_date = $values[$facet_id . '_first_date_wrapper'][$facet_id . '_first_date'];
    if ($second_date < $first_date) {
      $form_state->setError($element[$facet_id . '_second_date_wrapper'][$facet_id . '_second_date'], $this->t('The second date cannot be before the first date.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    return $this->doBuildDateWidgetElements($facet);
  }

  /**
   * Builds the elements for the Date widget.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param array $form
   *   A form if the widget is built inside a form.
   * @param \Drupal\Core\Form\FormStateInterface|null $form_state
   *   A form state if the widget is built inside a form.
   *
   * @return array
   *   The widget elements.
   */
  protected function doBuildDateWidgetElements(FacetInterface $facet, array $form = [], FormStateInterface $form_state = NULL): array {
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
      '#default_value' => $this->getOperatorFromActiveFilters($facet),
      '#empty_option' => $this->t('Select'),
    ];

    $parents = $form['#parents'] ?? [];
    $name = $facet->id() . '_op';
    if ($parents) {
      $first_parent = array_shift($parents);
      $name = $first_parent . '[' . implode('][', array_merge($parents, [$name])) . ']';
    }

    $build[$facet->id() . '_first_date_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          [
            ':input[name="' . $name . '"]' => [
              'value' => 'lt',
            ],
          ],
          [
            ':input[name="' . $name . '" ]' => [
              'value' => 'gt',
            ],
          ],
          [
            ':input[name="' . $name . '"]' => [
              'value' => 'bt',
            ],
          ],
        ],
      ],
    ];

    $first_date_default = $this->getDateFromActiveFilters($facet, 'first');

    $build[$facet->id() . '_first_date_wrapper'][$facet->id() . '_first_date'] = [
      '#type' => 'datetime',
      '#date_date_element' => 'date',
      '#date_time_element' => $date_type === 'date' ? 'none' : 'time',
      '#date_date_callbacks' => [[$this, 'setTitleDisplayVisible']],
      '#default_value' => $first_date_default,
    ];

    // We only care about the second date if the operator is "bt".
    $second_date_default = NULL;
    if ($this->getOperatorFromActiveFilters($facet) === 'bt') {
      $second_date_default = $this->getDateFromActiveFilters($facet, 'second');
    }

    $build[$facet->id() . '_second_date_wrapper'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      // We only show the second date if the operator is "bt".
      '#states' => [
        'visible' => [
          ':input[name="' . $name . '"]' => [
            'value' => 'bt',
          ],
        ],
      ],
    ];

    $build[$facet->id() . '_second_date_wrapper'][$facet->id() . '_second_date'] = [
      '#type' => 'datetime',
      '#date_date_element' => 'date',
      '#date_time_element' => $date_type === 'date' ? 'none' : 'time',
      '#date_date_callbacks' => [
        [$this, 'setTitleDisplayVisible'],
        [$this, 'setEndDateTitle'],
      ],
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
      'first_date' => [
        $facet->id() . '_first_date_wrapper',
        $facet->id() . '_first_date',
      ],
      'second_date' => [
        $facet->id() . '_second_date_wrapper',
        $facet->id() . '_second_date',
      ],
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

    if ($operator === 'bt' && count($values) === 2) {
      // If we are missing one of the date values, we cannot do a BETWEEN
      // filter.
      return [];
    }

    return [implode('|', $values)];
  }

  /**
   * Returns the operator from the active filter.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet to check the active items from.
   *
   * @return string|null
   *   The operator.
   */
  public function getOperatorFromActiveFilters(FacetInterface $facet): ?string {
    $active_filters = Date::getActiveItems($facet);
    return $active_filters['operator'] ?? NULL;
  }

  /**
   * Returns a date part from the active filter.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet to check the active items from.
   * @param string $part
   *   The date part to return.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The date object.
   */
  public function getDateFromActiveFilters(FacetInterface $facet, string $part): ?DrupalDateTime {
    $active_filters = Date::getActiveItems($facet);
    return $active_filters[$part] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'date_comparison';
  }

  /**
   * Sets title visible.
   *
   * @param array $element
   *   The form element whose value is being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Datetime\DrupalDateTime|null $date
   *   The date object.
   */
  public function setTitleDisplayVisible(array &$element, FormStateInterface $form_state, ?DrupalDateTime $date) {
    $element['date']['#title_display'] = 'before';
  }

  /**
   * Sets title label for end date element.
   *
   * @param array $element
   *   The form element whose value is being processed.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Datetime\DrupalDateTime|null $date
   *   The date object.
   */
  public function setEndDateTitle(array &$element, FormStateInterface $form_state, ?DrupalDateTime $date) {
    $element['date']['#title'] = $this->t('End Date');
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'setTitleDisplayVisible',
      'setEndDateTitle',
    ];
  }

}
