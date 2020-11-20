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
  public function buildDefaultFilters(array $form, FormStateInterface $form_state, ListSourceInterface $list_source, ListPageConfiguration $configuration): array {
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

    $this->initializeCurrentFilterValues($form_state, $configuration);

    $current_filters = static::getListSourceCurrentFilterValues($form_state, $list_source);
    // Set the current filters on the form so they can be used in the submit.
    $form['current_filters'] = [
      '#type' => 'value',
      '#value' => $current_filters,
    ];

    $facet_id = $form_state->get('facet_id');

    // If we could not determine a filter key, we default to showing the
    // summary of default values.
    if (!$facet_id) {
      return $this->buildSummaryPresetFilters($form, $form_state, $ajax_wrapper_id);
    }

    $filter_id = $form_state->get('filter_id');
    if (!isset($filter_id)) {
      $filter_id = self::generateFilterId($facet_id);
      $inc = 1;
      while (isset($current_filters[$filter_id])) {
        $filter_id = self::generateFilterId($facet_id . $inc);
        $inc++;
      }
    }

    $form = $this->buildEditPresetFilter($form, $form_state, $ajax_wrapper_id, $facet_id, $filter_id);

    return $form;
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
    $current_filters = static::getListSourceCurrentFilterValues($form_state, $list_source);

    $available_filters = $list_source->getAvailableFilters();

    $header = [
      ['data' => $this->t('Filter')],
      ['data' => $this->t('Default value')],
      ['data' => $this->t('Operations')],
    ];

    $rows = [];
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
        '#limit_validation_errors' => [
          array_merge($form['#parents'], [
            'wrapper',
            'edit',
            $filter_id,
            'edit-' . $filter_id,
          ]),
        ],
        '#ajax' => [
          'callback' => [$this, 'refreshDefaultValue'],
          'wrapper' => $ajax_wrapper_id,
        ],
        '#executes_submit_callback' => TRUE,
        '#submit' => [[$this, 'editDefaultValueSubmit']],
      ];

      $form['wrapper']['buttons'][$filter_id]['delete-' . $filter_id] = [
        '#type' => 'button',
        '#value' => $this->t('Delete'),
        '#name' => 'delete-' . $filter_id,
        '#filter_id' => $filter_id,
        '#facet_id' => $filter->getFacetId(),
        '#op' => 'remove-default-value',
        '#limit_validation_errors' => [
          array_merge($form['#parents'], [
            'wrapper',
            'edit',
            $filter_id,
            'delete-' . $filter_id,
          ]),
        ],
        '#ajax' => [
          'callback' => [$this, 'refreshDefaultValue'],
          'wrapper' => $ajax_wrapper_id,
        ],
        '#executes_submit_callback' => TRUE,
        '#submit' => [[$this, 'deleteDefaultValueSubmit']],
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
        'callback' => [$this, 'addDefaultValueAjax'],
        'wrapper' => $ajax_wrapper_id,
      ],
      '#executes_submit_callback' => TRUE,
      '#submit' => [[$this, 'addDefaultValueSubmit']],
      '#limit_validation_errors' => [
        array_merge($form['#parents'], [
          'wrapper',
          'summary',
          'add_new',
        ]),
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
   *   The filter ID.
   *
   * @return array
   *   The built form.
   */
  protected function buildEditPresetFilter(array $form, FormStateInterface $form_state, string $ajax_wrapper_id, string $facet_id, string $filter_id) {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $current_filters = static::getListSourceCurrentFilterValues($form_state, $list_source);
    $available_filters = $list_source->getAvailableFilters();

    $form['wrapper']['edit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Set default value for :filter', [':filter' => $available_filters[$facet_id]]),
    ];

    // Store the filter IDs on the form state in case we need to rebuild the
    // form.
    $form_state->set('filter_id', $filter_id);

    $facet = $this->getFacetById($list_source, $facet_id);
    if (!empty($facet) && ($widget = $facet->getWidgetInstance()) && ($widget instanceof ListPagesWidgetInterface)) {
      // Set the active items on the facet so that the widget can build the
      // form with default values.
      if (!empty($current_filters[$filter_id])) {
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
        '#executes_submit_callback' => TRUE,
        '#submit' => [[$this, 'setDefaultValueSubmit']],
      ];

      $form['wrapper']['edit'][$filter_id]['cancel_value'] = [
        '#value' => $this->t('Cancel'),
        '#type' => 'button',
        '#op' => 'cancel-default-value',
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
        '#submit' => [[$this, 'cancelDefaultValueSubmit']],
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
   * Submit callback for adding a new default value for a filter.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addDefaultValueSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->set('facet_id', $triggering_element['#value']);
    $form_state->setRebuild(TRUE);
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
  public function addDefaultValueAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -3));
    return $element['wrapper'];
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
    $current_filters = static::getListSourceCurrentFilterValues($form_state, $list_source);
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
    static::setListSourceCurrentFilterValues($form_state, $list_source, $current_filters);
    $form_state->set('facet_id', NULL);
    $form_state->set('filter_id', NULL);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit callback for cancelling on the default value form..
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function cancelDefaultValueSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->set('facet_id', NULL);
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
   * Submit callback for editing existing values for filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function editDefaultValueSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->set('facet_id', $triggering_element['#facet_id']);
    $form_state->set('filter_id', $triggering_element['#filter_id']);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit callback for deleting existing values for filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function deleteDefaultValueSubmit(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $current_filters = static::getListSourceCurrentFilterValues($form_state, $list_source);
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
    static::setListSourceCurrentFilterValues($form_state, $list_source, $current_filters);
    $form_state->set('filter_id', NULL);
    $form_state->set('facet_id', NULL);

    $form_state->setRebuild(TRUE);
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
    return $element['wrapper'];
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

  /**
   * Set the current filter values on the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source the filter values belong to.
   * @param array $current_filter_values
   *   The filter values.
   */
  protected static function setListSourceCurrentFilterValues(FormStateInterface $form_state, ListSourceInterface $list_source, array $current_filter_values): void {
    $storage = &$form_state->getStorage();
    NestedArray::setValue($storage, ['current_filters', $list_source->getSearchId()], $current_filter_values);
  }

  /**
   * Gets the current filter values from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source the filter values belong to.
   *
   * @return ListPresetFilter[]
   *   The filter values.
   */
  protected static function getListSourceCurrentFilterValues(FormStateInterface $form_state, ListSourceInterface $list_source): array {
    $storage = $form_state->getStorage();
    $current_filter_values = NestedArray::getValue($storage, ['current_filters', $list_source->getSearchId()]);
    return $current_filter_values ?? [];
  }

  /**
   * Checks if the current filter values from the form state were emptied.
   *
   * If the form starts with default values from configuration but the user
   * deletes them all, we need to keep track of that so we can know not to
   * default to values from the previously saved configuration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source the filter values belong to.
   *
   * @return bool
   *   Whether the values have been emptied..
   */
  protected static function isCurrentFilterValuesEmpty(FormStateInterface $form_state, ListSourceInterface $list_source): bool {
    $storage = $form_state->getStorage();
    $values = NestedArray::getValue($storage, ['current_filters', $list_source->getSearchId()]);
    // If we have an empty array, it means we removed all the values.
    return is_array($values) && empty($values) ?? FALSE;
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
    $values = static::getListSourceCurrentFilterValues($form_state, $list_source);
    if ($values) {
      return;
    }

    // Otherwise, we need to check if the current list source matches the
    // passed configuration and set the ones from the configuration if they do.
    // We also check if the values have not been emptied in the current
    // "session".
    if ($list_source->getEntityType() === $configuration->getEntityType() && $list_source->getBundle() === $configuration->getBundle() && !static::isCurrentFilterValuesEmpty($form_state, $list_source)) {
      $values = $configuration->getDefaultFiltersValues();
      static::setListSourceCurrentFilterValues($form_state, $list_source, $values);
      return;
    }

    static::setListSourceCurrentFilterValues($form_state, $list_source, []);
  }

}
