<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\oe_list_pages\Plugin\facets\widget\ListPagesWidgetInterface;

/**
 * Builder service for preset filters.
 */
class ListPresetFiltersBuilder {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The facets manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetsManager;

  /**
   * ListPresetFiltersBuilder constructor.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facets manager.
   */
  public function __construct(DefaultFacetManager $facetManager) {
    $this->facetsManager = $facetManager;
  }

  /**
   * Build form component for default filter values edition.
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
  public function buildDefaultFilters(array $form, FormStateInterface $form_state, ListSourceInterface $list_source, ListPageConfiguration $configuration) {
    $preset_filters = $configuration->getDefaultFiltersValues();
    $form_state->set('list_source', $list_source);

    $ajax_wrapper_id = 'list-page-default_filter_values-' . ($form['#parents'] ? '-' . implode('-', $form['#parents']) : '');

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

    $current_filters = $form_state->get('current_filters') ?? $preset_filters;
    $form_state->set('current_filters', $current_filters);

    $triggering_element = $form_state->getTriggeringElement();
    $this->handleDefaultValueSubmit($form, $form_state);

    // Set the current filters on the form so they can be used in the submit.
    $form['current_filters'] = [
      '#type' => 'value',
      '#value' => $form_state->get('current_filters'),
    ];

    // Determine if the triggered operation was one of ours.
    $relevant_operation = $triggering_element &&
      isset($triggering_element['#op']) &&
      in_array($triggering_element['#op'], [
        'cancel-default-value',
        'remove-default-value',
        'set-default-value',
      ]);

    // Now we need to determine which form part to show: either the summary
    // of selected default values, or the edit form of a given filter. For the
    // form elements, it can happen that we edit a value or add a new value.
    // Moreover, we can also have form rebuilds coming from the filter widget
    // elements in which case we need to keep the form open.
    // We start with the 'Edit' button of a given filter, which we mark on the
    // button itself.
    $facet_id = $triggering_element['#facet_id'] ?? NULL;
    $filter_id = $triggering_element['#filter_id'] ?? '';
    // Otherwise, check if we are adding a new filter widget form.
    if (!$facet_id) {
      $facet_id = $form_state->getValue(['wrapper', 'summary', 'add_new']);
    }
    // Finally, we may be in the middle of editing a filter value so we need to
    // keep its form element open.
    if (!$facet_id) {
      $facet_id = $form_state->get('facet_id');
      $filter_id = $form_state->get('filter_id');
    }

    // If we could not determine a filter key, we default to showing the
    // summary of default values.
    if (!$facet_id || $relevant_operation) {
      return $this->buildSummaryPresetFilters($form, $form_state, $ajax_wrapper_id);
    }

    $form = $this->buildEditPresetFilter($form, $form_state, $ajax_wrapper_id, $facet_id, $filter_id);

    return $form;
  }

  /**
   * Handles the save or removal of default facet values.
   *
   * Handling for when the user hits the "Set default value" or "Cancel" button.
   *
   * @param array $form
   *   The parent form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The parent form state.
   *
   * @see self::buildDefaultFilters()
   */
  protected function handleDefaultValueSubmit(array $form, FormStateInterface $form_state): void {
    $current_filters = $form_state->get('current_filters');
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element) {
      return;
    }

    $op = $triggering_element['#op'] ?? NULL;
    if (!$op) {
      return;
    }

    $facet_id = $triggering_element['#facet_id'] ?? NULL;
    $filter_id = $triggering_element['#filter_id'] ?? NULL;
    if (!$facet_id) {
      return;
    }

    $facet = $this->getFacetById($list_source, $facet_id);
    $current_filters[$filter_id] = [
      '#parents' => array_merge($form['#parents'], [
        'wrapper',
        'edit',
        $filter_id,
      ]),
      '#tree' => TRUE,
    ];

    $subform_state = SubformState::createForSubform($current_filters[$filter_id], $form, $form_state);
    $widget = $facet->getWidgetInstance();

    // The op can represent a saving of a value or the removal of one.
    $op = $triggering_element['#op'];
    if ($op === 'remove-default-value') {
      // Before deleting the values from the current filter store, call the
      // widget prepare method in case it needs to do any processing on the
      // form state.
      $widget->prepareDefaultFilterValue($facet, $current_filters[$filter_id], $subform_state);
      $delete_filter_id = $triggering_element['#filter_id'];
      unset($current_filters[$delete_filter_id]);
      $form_state->set('current_filters', $current_filters);
      return;
    }

    if ($op === 'set-default-value') {
      $preset_filter = $widget->prepareDefaultFilterValue($facet, $current_filters[$filter_id], $subform_state);
      $current_filters[$filter_id] = new ListPresetFilter($facet_id, $preset_filter['operator'], $preset_filter['values']);
    }

    // Set the current filters on the form state so they can be used elsewhere.
    $form_state->set('current_filters', $current_filters);
  }

  /**
   * Builds the summary for the default filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $ajax_wrapper_id
   *   The AJAX wrapper ID.
   *
   * @return array
   *   The built form.
   */
  protected function buildSummaryPresetFilters(array $form, FormStateInterface $form_state, string $ajax_wrapper_id) {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $current_filters = $form_state->get('current_filters');

    $available_filters = $list_source->getAvailableFilters();

    $header = [
      ['data' => $this->t('Filter')],
      ['data' => $this->t('Default value')],
      ['data' => $this->t('Operations')],
    ];

    $rows = [];
    /** @var \Drupal\oe_list_pages\ListPresetFilter $filter */
    foreach ($current_filters as $filter_id => $filter) {
      $facet = $this->getFacetById($list_source, $filter->getFacetId());
      $widget = $facet->getWidgetInstance();
      $filter_value_label = '';
      if ($widget instanceof ListPagesWidgetInterface) {
        $filter_value_label = $widget->getDefaultValuesLabel($facet, $list_source, $filter);
      }

      $rows[] = [
        [
          'data' => $available_filters[$filter->getFacetId()],
          'facet_id' => $filter_id,
        ],
        ['data' => $filter_value_label],
        ['data' => ''],
      ];

      $form['wrapper']['buttons'][$filter_id]['edit-' . $filter_id] = [
        '#type' => 'button',
        '#value' => $this->t('Edit'),
        '#name' => 'edit-' . $filter_id,
        '#filter_id' => $filter_id,
        '#facet_id' => $filter->getFacetId(),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'refreshDefaultValue'],
          'wrapper' => $ajax_wrapper_id,
        ],
      ];

      $form['wrapper']['buttons'][$filter_id]['delete-' . $filter_id] = [
        '#type' => 'button',
        '#value' => $this->t('Delete'),
        '#name' => 'delete-' . $filter_id,
        '#filter_id' => $filter_id,
        '#facet_id' => $filter->getFacetId(),
        '#op' => 'remove-default-value',
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'refreshDefaultValue'],
          'wrapper' => $ajax_wrapper_id,
        ],
      ];
    }

    $form['wrapper']['summary'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Summary'),
    ];

    $form['wrapper']['summary']['table'] = [
      '#type' => 'table',
      '#title' => $this->t('Default filter values'),
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No default values set.'),
      '#attributes' => [
        'class' => ['default-filter-values-table'],
      ],
    ];

    $form['wrapper']['summary']['add_new'] = [
      '#type' => 'select',
      '#title' => $this->t('Add default value for:'),
      '#options' => ['' => $this->t('- None -')] + $available_filters,
      '#ajax' => [
        'callback' => [$this, 'addDefaultValue'],
        'wrapper' => $ajax_wrapper_id,
      ],
    ];

    $form['wrapper']['#pre_render'][] = [get_class($this), 'preRenderOperationButtons'];
    return $form;
  }

  /**
   * Builds the edit filter section.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $ajax_wrapper_id
   *   The AJAX wrapper ID.
   * @param string $facet_id
   *   The facet ID.
   * @param string $filter_id
   *   The filter id.
   *
   * @return array
   *   The built form.
   */
  protected function buildEditPresetFilter(array $form, FormStateInterface $form_state, string $ajax_wrapper_id, string $facet_id, string $filter_id = '') {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $current_filters = $form_state->get('current_filters');
    $available_filters = $list_source->getAvailableFilters();

    if (empty($filter_id)) {
      $filter_id = self::generateFilterId($facet_id);
    }

    $form['wrapper']['edit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Set default value for :filter', [':filter' => $available_filters[$facet_id]]),
    ];

    // Store the facet and filter IDs on the form state in case we need to
    // rebuild the form.
    $form_state->set('facet_id', $facet_id);
    $form_state->set('filter_id', $filter_id);

    $facet = $this->getFacetById($list_source, $facet_id);
    if (!empty($facet) && ($widget = $facet->getWidgetInstance()) && ($widget instanceof ListPagesWidgetInterface)) {
      // Set the active items on the facet so that the widget can build the
      // form with default values.
      if (!empty($current_filters[$filter_id])) {
        /** @var \Drupal\oe_list_pages\ListPresetFilter $filter */
        $filter = $current_filters[$filter_id];
        $form_state->setValue([
          'wrapper',
          'edit',
          $filter_id,
          'oe_list_pages_filter_operator',
        ], $filter->getOperator());
        $facet->setActiveItems($filter->getValues());
      }

      $ajax_definition = [
        'callback' => [$this, 'setDefaultValue'],
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
      $form['wrapper']['edit'][$filter_id] = $widget->buildDefaultValueForm($form['wrapper']['edit'][$filter_id], $subform_state, $facet);

      $form['wrapper']['edit'][$filter_id]['set_value'] = [
        '#value' => $this->t('Set default value'),
        '#type' => 'button',
        '#op' => 'set-default-value',
        '#limit_validation_errors' => [
          array_merge($form['#parents'], ['wrapper', 'edit']),
        ],
        '#ajax' => $ajax_definition,
        '#filter_id' => $filter_id,
        '#facet_id' => $facet_id,
      ];

      $form['wrapper']['edit'][$filter_id]['cancel_value'] = [
        '#value' => $this->t('Cancel'),
        '#type' => 'button',
        '#op' => 'cancel-default-value',
        '#limit_validation_errors' => [],
        '#ajax' => $ajax_definition,
      ];
    }

    return $form;
  }

  /**
   * Pre-render callback to move the operation buttons to table rows.
   *
   * This is needed for ajax to properly work in these buttons.
   *
   * @param array $form
   *   The form to alter.
   *
   * @return array
   *   The altered array.
   */
  public static function preRenderOperationButtons(array $form) {
    $rows =& $form['summary']['table']['#rows'];
    for ($i = 0; $i < count($rows); $i++) {
      $facet_id = $rows[$i][0]['facet_id'];
      $rows[$i][2]['data'] = [
        'edit-' . $facet_id => $form['buttons'][$facet_id]['edit-' . $facet_id],
        'delete-' . $facet_id => $form['buttons'][$facet_id]['delete-' . $facet_id],
      ];
    }
    unset($form['buttons']);
    return $form;
  }

  /**
   * Ajax request handler for adding a new default value for a filter.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function addDefaultValue(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -3));
    return $element['wrapper'];
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
  public function setDefaultValue(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -4));
    return $element['wrapper'];
  }

  /**
   * Ajax request handler for editing/deleting existing values for filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function refreshDefaultValue(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -4));
    $element['wrapper']['#open'] = TRUE;
    return $element;
  }

  /**
   * Get a facet by id.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $listSource
   *   The list source.
   * @param string $id
   *   The facet id.
   *
   * @return \Drupal\facets\FacetInterface|null
   *   The facet if found.
   */
  public function getFacetById(ListSourceInterface $listSource, string $id): ?FacetInterface {
    $facets = $this->facetsManager->getFacetsByFacetSourceId($listSource->getSearchId());
    foreach ($facets as $facet) {
      if ($id === $facet->id()) {
        return $facet;
      }
    }

    return NULL;
  }

  /**
   * Generates the filter id.
   *
   * @param string $id
   *   The facet id.
   *
   * @return string
   *   The filter id.
   */
  public static function generateFilterId(string $id): string {
    return md5($id);
  }

}
