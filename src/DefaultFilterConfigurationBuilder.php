<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\oe_list_pages\Plugin\facets\widget\ListPagesWidgetInterface;

/**
 * Builder service for preset filters.
 */
class DefaultFilterConfigurationBuilder extends FilterConfigurationFormBuilderBase {

  /**
   * {@inheritdoc}
   */
  protected function getAjaxWrapperId(array $form): string {
    return 'list-page-default_filter_values-' . ($form['#parents'] ? '-' . implode('-', $form['#parents']) : '');
  }

  /**
   * {@inheritdoc}
   */
  protected static function getFilterType(): string {
    return 'default';
  }

  /**
   * Builds the form for adding/editing/removing default filter values.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The parent form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The configuration.
   *
   * @return array
   *   The form elements.
   */
  public function buildDefaultFilters(array $form, FormStateInterface $form_state, ListSourceInterface $list_source, ListPageConfiguration $configuration): array {
    $form_state->set('list_source', $list_source);

    $ajax_wrapper_id = $this->getAjaxWrapperId($form);

    $form['wrapper'] = [
      '#type' => 'container',
      '#title' => $this->t('Default filter values'),
      '#tree' => TRUE,
      '#attributes' => [
        'id' => $ajax_wrapper_id,
      ],
    ];

    $form['wrapper']['label'] = [
      '#title' => $this->t('Default filter values'),
      '#type' => 'label',
    ];

    $this->initializeCurrentFilterValues($form_state, $configuration);

    $current_filters = static::getCurrentValues($form_state, $list_source);
    // Set the current filters on the form so they can be used in the submit.
    $form['current_filters'] = [
      '#type' => 'value',
      '#value' => $current_filters,
    ];

    $facet_id = $form_state->get('default_facet_id');

    // If we could not determine a facet ID, we default to showing the
    // summary of default values.
    if (!$facet_id) {
      return $this->buildSummaryPresetFilters($form, $form_state, $list_source, $list_source->getAvailableFilters());
    }

    $filter_id = $form_state->get('default_filter_id');
    if (!isset($filter_id)) {
      $filter_id = static::generateFilterId($facet_id, array_keys($current_filters));
    }

    $form = $this->buildEditPresetFilter($form, $form_state, $facet_id, $filter_id);

    return $form;
  }

  /**
   * Builds the edit filter section.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $facet_id
   *   The facet ID.
   * @param string $filter_id
   *   The filter ID.
   *
   * @return array
   *   The built form.
   */
  protected function buildEditPresetFilter(array $form, FormStateInterface $form_state, string $facet_id, string $filter_id) {
    $ajax_wrapper_id = $this->getAjaxWrapperId($form);

    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $current_filters = static::getCurrentValues($form_state, $list_source);
    $available_filters = $list_source->getAvailableFilters();

    $form['wrapper']['edit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Set default value for :filter', [':filter' => $available_filters[$facet_id] ?? '']),
    ];

    // Store the filter IDs on the form state in case we need to rebuild the
    // form.
    $form_state->set(static::getFilterType() . '_filter_id', $filter_id);

    $facet = $this->getFacetById($list_source, $facet_id);
    if (!empty($facet) && ($widget = $facet->getWidgetInstance()) && ($widget instanceof ListPagesWidgetInterface)) {
      $filter = NULL;
      if (!empty($current_filters[$filter_id])) {
        $filter = $current_filters[$filter_id];
      }

      $ajax_definition = [
        'callback' => [$this, 'setDefaultValueAjax'],
        'wrapper' => $ajax_wrapper_id,
      ];

      $form['wrapper']['edit'][$filter_id] = [
        '#parents' => array_merge($form['#parents'], [
          'wrapper',
          'edit',
          $filter_id,
        ]),
        '#tree' => TRUE,
      ];

      $subform_state = SubformState::createForSubform($form['wrapper']['edit'][$filter_id], $form, $form_state);
      $form['wrapper']['edit'][$filter_id] = $widget->buildDefaultValueForm($form['wrapper']['edit'][$filter_id], $subform_state, $facet, $filter);

      $form['wrapper']['edit'][$filter_id]['set_value'] = [
        '#value' => $this->t('Set default value'),
        '#type' => 'button',
        '#limit_validation_errors' => [
          array_merge($form['#parents'], ['wrapper', 'edit']),
        ],
        '#ajax' => $ajax_definition,
        '#filter_id' => $filter_id,
        '#facet_id' => $facet_id,
        '#executes_submit_callback' => TRUE,
        '#submit' => [[$this, 'setDefaultValueSubmit']],
      ];

      $form['wrapper']['edit'][$filter_id]['cancel_value'] = [
        '#value' => $this->t('Cancel'),
        '#type' => 'button',
        '#name' => static::getFilterType() . '-cancel-' . $filter_id,
        '#limit_validation_errors' => [
          array_merge($form['#parents'], [
            'wrapper',
            'edit',
            $filter_id,
            'cancel_value',
          ]),
        ],
        '#ajax' => $ajax_definition,
        '#executes_submit_callback' => TRUE,
        '#submit' => [[$this, 'cancelValueSubmit']],
      ];
    }

    return $form;
  }

  /**
   * Submit callback for setting a default value for a filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function setDefaultValueSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $current_filters = static::getCurrentValues($form_state, $list_source);
    $triggering_element = $form_state->getTriggeringElement();
    $facet_id = $triggering_element['#facet_id'];
    $filter_id = $triggering_element['#filter_id'];

    if (!$facet_id) {
      return;
    }

    $facet = $this->getFacetById($list_source, $facet_id);
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -4));
    $current_filters[$filter_id] = [
      '#parents' => array_merge($element['#parents'], [
        'wrapper',
        'edit',
        $filter_id,
      ]),
      '#tree' => TRUE,
    ];

    $subform_state = SubformState::createForSubform($current_filters[$filter_id], $form, $form_state);
    $widget = $facet->getWidgetInstance();

    $preset_filter = $widget->prepareDefaultFilterValue($facet, $current_filters[$filter_id], $subform_state);
    $current_filters[$filter_id] = new ListPresetFilter($facet_id, $preset_filter['values'], $preset_filter['operator']);

    // Set the current filters on the form state so they can be used elsewhere.
    static::setCurrentValues($form_state, $list_source, $current_filters);
    $form_state->set('default_facet_id', NULL);
    $form_state->set('default_filter_id', NULL);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax request handler for setting a default value for a filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function setDefaultValueAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -4));
    return $element['wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFilterValueSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $current_filters = static::getCurrentValues($form_state, $list_source);
    $triggering_element = $form_state->getTriggeringElement();
    $facet_id = $triggering_element['#facet_id'];
    $filter_id = $triggering_element['#filter_id'];

    if (!$facet_id) {
      return;
    }

    $facet = $this->getFacetById($list_source, $facet_id);
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -4));
    $current_filters[$filter_id] = [
      '#parents' => array_merge($element['#parents'], [
        'wrapper',
        'edit',
        $filter_id,
      ]),
      '#tree' => TRUE,
    ];

    $subform_state = SubformState::createForSubform($current_filters[$filter_id], $form, $form_state);
    $widget = $facet->getWidgetInstance();

    $widget->prepareDefaultFilterValue($facet, $current_filters[$filter_id], $subform_state);
    unset($current_filters[$filter_id]);
    static::setCurrentValues($form_state, $list_source, $current_filters);
    $form_state->set('default_filter_id', NULL);
    $form_state->set('default_facet_id', NULL);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Initialize the form state with the values of the current list source.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The current configuration.
   */
  protected function initializeCurrentFilterValues(FormStateInterface $form_state, ListPageConfiguration $configuration): void {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');

    // If we have current filter values for this list source, we can keep them
    // going forward.
    $values = static::getCurrentValues($form_state, $list_source);
    if ($values) {
      return;
    }

    // Otherwise, we need to check if the current list source matches the
    // passed configuration and set the ones from the configuration if they do.
    // We also check if the values have not been emptied in the current
    // "session".
    if ($list_source->getEntityType() === $configuration->getEntityType() && $list_source->getBundle() === $configuration->getBundle() && !static::areCurrentValuesEmpty($form_state, $list_source)) {
      $values = $configuration->getDefaultFiltersValues();
      static::setCurrentValues($form_state, $list_source, $values);
      return;
    }

    static::setCurrentValues($form_state, $list_source, []);
  }

}
