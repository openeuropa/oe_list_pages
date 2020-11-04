<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element;
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
   * Ajax request handler for editing default values for filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function editDefaultValue(array &$form, FormStateInterface $form_state): array {
    $key = $form_state->getValue('oe_list_pages_form_key');
    $form[$key]['preset_filters_wrapper']['#open'] = TRUE;
    return $form[$key];
  }

  /**
   * Ajax request handler for updating default value for a filter.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function setDefaultValues(array &$form, FormStateInterface $form_state): array {
    $key = $form_state->getValue('oe_list_pages_form_key');
    $form[$key]['preset_filters_wrapper']['#open'] = TRUE;
    return $form[$key];
  }

  /**
   * Build form component for default filter values edition.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function buildDefaultFilters(array &$form, FormStateInterface &$form_state, string $form_key, ListSourceInterface $list_source = NULL, array $available_filters = [], array $preset_filters = []) {

    // List source doesn't exist yet.
    if (empty($list_source)) {
      return $form;
    }

    // Store the form key for ajax processing.
    $form['oe_list_pages_form_key'] = [
      '#type' => 'value',
      '#value' => $form_key,
    ];

    $form[$form_key]['preset_filters_wrapper'] = [
      '#type' => 'container',
      '#title' => $this->t('Default filter values'),
      '#tree' => TRUE,
      '#attributes' => [
        'id' => 'list-page-default-filters',
      ],
    ];

    $form[$form_key]['preset_filters_wrapper']['label'] = [
      '#title' => $this->t('Default filter values'),
      '#type' => 'label',
    ];

    $current_filters = $form_state->getValue('preset_filters_wrapper')['current_filters'] ?? $preset_filters;
    $triggering_element = $form_state->getTriggeringElement();

    // Adding default filter value.
    if (!empty($triggering_element) && $triggering_element['#name'] == 'set-default-filter') {
      $filter_key = $form_state->getValue('preset_filters_wrapper')['edit']['filter_key'];
      $filter_id = $form_state->getValue('preset_filters_wrapper')['edit']['filter_id'];
      // Replace correct labels.
      $facet = $this->getFacetById($list_source, $filter_key);
      $submitted_form = $form_state->getCompleteForm();
      $subform_state = SubformState::createForSubform($submitted_form['emr_plugins_oe_list_page']['preset_filters_wrapper']['edit'][$filter_id], $submitted_form, $form_state);
      if (!empty($facet)) {
        $widget = $facet->getWidgetInstance();
        if ($widget instanceof ListPagesWidgetInterface) {
          $current_filters[$filter_id] = [
            'facet_id' => $filter_key,
            'values' => $widget->prepareDefaultValueFilter($facet, $form, $subform_state),
          ];
        }
      }
    }
    // Removing default filter value.
    elseif (!empty($triggering_element) && $triggering_element['#op'] == 'remove-default-filter') {
      $delete_filter_id = $triggering_element['#filter_id'];
      unset($current_filters[$delete_filter_id]);
    }

    $form[$form_key]['preset_filters_wrapper']['current_filters'] = [
      '#type' => 'value',
      '#value' => $current_filters,
    ];

    $filter_key = !empty($triggering_element['#filter_facet_id']) ? $triggering_element['#filter_facet_id'] : $form_state->getValue('preset_filters_wrapper')['summary']['add_new'];

    if (empty($filter_key)) {
      $form = $this->buildSummaryPresetFilters($form, $form_state, $form_key, $list_source, $available_filters, $current_filters);
    }
    else {
      $filter_id = !empty($triggering_element['#filter_id']) ? $triggering_element['#filter_id'] : '';
      $form = $this->buildEditPresetFilter($form, $form_state, $form_key, $list_source, $available_filters, $current_filters, $filter_key, $filter_id);
    }

    return $form;
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

  /**
   * Builds the summary for the default filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_key
   *   The form key.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param array $available_filters
   *   An array of available filters.
   * @param array $current_filters
   *   An array of currently set filters.
   *
   * @return array
   *   The built form.
   */
  protected function buildSummaryPresetFilters(array $form, FormStateInterface $form_state, string $form_key, ListSourceInterface $list_source, array $available_filters, array $current_filters) {
    $header = [
      ['data' => $this->t('Filter')],
      ['data' => $this->t('Default value')],
      ['data' => $this->t('Operations')],
    ];

    $rows = [];

    foreach ($current_filters as $filter_key => $filter) {
      $facet = $this->getFacetById($list_source, $filter['facet_id']);
      $widget = $facet->getWidgetInstance();
      $filter_value_label = '';
      if ($widget instanceof ListPagesWidgetInterface) {
        $filter_value_label = $widget->getDefaultValuesLabel($facet, $list_source, $filter['values']);
      }

      $rows[] = [
        [
          'data' => $available_filters[$filter['facet_id']],
          'filter_key' => $filter_key,
        ],
        ['data' => $filter_value_label],
        ['data' => ''],
      ];

      $limit_validation_errors = [
        ['bundle'],
        ['preset_filters_wrapper', 'edit'],
        ['preset_filters_wrapper', 'buttons'],
        ['preset_filters_wrapper', 'current_filters'],
      ];

      $form[$form_key]['preset_filters_wrapper']['buttons'][$filter_key]['edit-' . $filter_key] = [
        '#type' => 'button',
        '#value' => $this->t('Edit'),
        '#name' => 'edit-' . $filter_key,
        '#filter_id' => $filter_key,
        '#filter_facet_id' => $filter['facet_id'],
        '#limit_validation_errors' => $limit_validation_errors,
        '#ajax' => [
          'callback' => [$this, 'editDefaultValue'],
          'wrapper' => $form[$form_key]['#id'],
        ],
      ];

      $form[$form_key]['preset_filters_wrapper']['buttons'][$filter_key]['delete-' . $filter_key] = [
        '#type' => 'button',
        '#value' => $this->t('Delete'),
        '#name' => 'delete-' . $filter_key,
        '#filter_id' => $filter_key,
        '#op' => 'remove-default-filter',
        '#limit_validation_errors' => $limit_validation_errors,
        '#ajax' => [
          'callback' => [$this, 'editDefaultValue'],
          'wrapper' => $form[$form_key]['#id'],
        ],
      ];
    }

    $form[$form_key]['preset_filters_wrapper']['summary'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Summary'),
    ];

    $form[$form_key]['preset_filters_wrapper']['summary']['table'] = [
      '#type' => 'table',
      '#title' => $this->t('Default filter values'),
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No default values set.'),
    ];

    $form[$form_key]['preset_filters_wrapper']['summary']['add_new'] = [
      '#type' => 'select',
      '#title' => $this->t('Add default value for:'),
      '#options' => ['' => $this->t('- None -')] + $available_filters,
      '#ajax' => [
        'callback' => [$this, 'editDefaultValue'],
        'wrapper' => $form[$form_key]['#id'],
      ],
    ];

    $form[$form_key]['preset_filters_wrapper']['#pre_render'][] = [get_class($this), 'preRenderOperationButtons'];
    return $form;
  }

  /**
   * Uses prerender to move operation buttons to table rows.
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
      $filter_key = $rows[$i][0]['filter_key'];
      $rows[$i][2]['data'] = [
        'edit-' . $filter_key => $form['buttons'][$filter_key]['edit-' . $filter_key],
        'delete-' . $filter_key => $form['buttons'][$filter_key]['delete-' . $filter_key],
      ];
    }
    unset($form['buttons']);
    return $form;
  }

  /**
   * Builds the edit filter section.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_key
   *   The form key.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param array $available_filters
   *   An array of available filters.
   * @param array $current_filters
   *   An array of currently set filters.
   * @param string $filter_key
   *   The filter key.
   * @param string $filter_id
   *   The filter id.
   *
   * @return array
   *   The built form.
   */
  protected function buildEditPresetFilter(array $form, FormStateInterface $form_state, string $form_key, ListSourceInterface $list_source, array $available_filters, array $current_filters, string $filter_key, string $filter_id = '') {
    if (empty($filter_id)) {
      $filter_id = self::generateFilterId($filter_key);
    }

    $form[$form_key]['preset_filters_wrapper']['edit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Set default value for :filter', [':filter' => $available_filters[$filter_key]]),
    ];

    $facet = $this->getFacetById($list_source, $filter_key);
    if (!empty($facet) && ($widget = $facet->getWidgetInstance()) && ($widget instanceof ListPagesWidgetInterface)) {
      // Set active item for value edition.
      if (!empty($current_filters[$filter_id])) {
        $facet->setActiveItems($current_filters[$filter_id]['values']);
      }

      $form[$form_key]['preset_filters_wrapper']['edit'][$filter_id] = $widget->buildDefaultValuesWidget($facet, $list_source, [
        'preset_filters_wrapper',
        'edit',
        $filter_id,
      ]);
      $form[$form_key]['preset_filters_wrapper']['edit'][$filter_id]['#type'] = 'container';
    }

    $form[$form_key]['preset_filters_wrapper']['edit']['filter_id'] = [
      '#value' => $filter_id,
      '#type' => 'hidden',
    ];

    $form[$form_key]['preset_filters_wrapper']['edit']['filter_key'] = [
      '#value' => $filter_key,
      '#type' => 'value',
    ];

    $ajax_definition = [
      'callback' => [$this, 'setDefaultValues'],
      'wrapper' => $form[$form_key]['#id'],
    ];

    $facet_widget_keys = $this->getWidgetKeys($form[$form_key]['preset_filters_wrapper']['edit'][$filter_id]);
    $limit_validation_errors = array_merge($facet_widget_keys, [
      ['bundle'],
      ['preset_filters_wrapper', 'edit'],
      ['preset_filters_wrapper', 'current_filters'],
    ]);

    $form[$form_key]['preset_filters_wrapper']['edit']['set_value'] = [
      '#value' => $this->t('Set default value'),
      '#type' => 'button',
      '#name' => 'set-default-filter',
      '#limit_validation_errors' => $limit_validation_errors,
      '#ajax' => $ajax_definition,
    ];

    $form[$form_key]['preset_filters_wrapper']['edit']['cancel_value'] = [
      '#value' => $this->t('Cancel'),
      '#type' => 'button',
      '#name' => 'cancel-default-filter',
      '#limit_validation_errors' => $limit_validation_errors,
      '#ajax' => $ajax_definition,
    ];

    return $form;
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
  }

  /**
   * Get all the keys used by elements in the widget.
   *
   * @param array $element
   *   The widget element.
   *
   * @return array
   *   The keys.
   */
  protected function getWidgetKeys(array $element) {
    $keys = [];
    $children = Element::Children($element);

    foreach ($children as $child_key) {
      $keys[] = [$child_key];
      if (!empty($element[$child_key])) {
        $child_keys = $this->getWidgetKeys($element[$child_key]);
        if (!empty($child_keys)) {
          $keys = array_merge($keys, $child_keys);
        }
      }
    }

    return $keys;
  }

}
