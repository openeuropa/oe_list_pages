<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\oe_list_pages\Plugin\facets\widget\ListPagesWidgetInterface;

/**
 * Base class for configuration form builders for the list pages.
 */
abstract class FilterConfigurationFormBuilderBase {

  use StringTranslationTrait;
  use DependencySerializationTrait;
  use FacetManipulationTrait;

  /**
   * The facets manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetsManager;

  /**
   * FilterConfigurationFormBuilderBase constructor.
   *
   * @param \Drupal\facets\FacetManager\DefaultFacetManager $facetManager
   *   The facets manager.
   */
  public function __construct(DefaultFacetManager $facetManager) {
    $this->facetsManager = $facetManager;
  }

  /**
   * Returns the AJAX wrapper ID to use for this form section.
   *
   * @param array $form
   *   The current form.
   *
   * @return string
   *   The wrapper ID.
   */
  abstract protected function getAjaxWrapperId(array $form): string;

  /**
   * The type of filter this builder applies to.
   *
   * @return string
   *   The filter type.
   */
  abstract protected static function getFilterType(): string;

  /**
   * Submit callback deleting a filter value.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  abstract public function deleteFilterValueSubmit(array &$form, FormStateInterface $form_state): void;

  /**
   * Get a facet by id.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param string $id
   *   The facet id.
   *
   * @return \Drupal\facets\FacetInterface|null
   *   The facet if found.
   */
  public function getFacetById(ListSourceInterface $list_source, string $id): ?FacetInterface {
    $facets = $this->facetsManager->getFacetsByFacetSourceId($list_source->getSearchId());
    foreach ($facets as $facet) {
      if ($id === $facet->id()) {
        return $facet;
      }
    }

    return NULL;
  }

  /**
   * Builds the summary for the filters.
   *
   * The summary lists which of the facets have been configured to be used as
   * filters and provides buttons to edit/delete them, as well as to add new
   * filters from the available facets.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param array $available_filters
   *   The available filters the user can choose from.
   *
   * @return array
   *   The built form.
   */
  protected function buildSummaryPresetFilters(array $form, FormStateInterface $form_state, ListSourceInterface $list_source, array $available_filters = []): array {
    $ajax_wrapper_id = $this->getAjaxWrapperId($form);
    $current_filters = static::getCurrentValues($form_state, $list_source);

    $header = [
      ['data' => $this->t('Filter')],
      ['data' => $this->t('Default value')],
      ['data' => $this->t('Operations')],
    ];

    $rows = [];
    foreach ($current_filters as $filter_id => $filter) {
      $facet = $this->getFacetById($list_source, $filter->getFacetId());
      if (empty($facet)) {
        continue;
      }
      $widget = $facet->getWidgetInstance();
      $filter_value_label = '';
      if ($widget instanceof ListPagesWidgetInterface) {
        // Ensure the facet is built before we ask for the labels.
        $clone = clone $facet;
        $this->rebuildFacet($clone, $filter->getValues());
        $filter_value_label = $widget->getDefaultValuesLabel($clone, $list_source, $filter);
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
        '#name' => static::getFilterType() . '-edit-' . $filter_id,
        '#filter_id' => $filter_id,
        '#facet_id' => $filter->getFacetId(),
        '#limit_validation_errors' => [
          array_merge($form['#parents'], [
            'wrapper',
            'edit',
            $filter_id,
            static::getFilterType() . '-edit-' . $filter_id,
          ]),
        ],
        '#ajax' => [
          'callback' => [$this, 'refreshEditFormAjax'],
          'wrapper' => $ajax_wrapper_id,
        ],
        '#executes_submit_callback' => TRUE,
        '#submit' => [[$this, 'editFilterSubmit']],
      ];

      $form['wrapper']['buttons'][$filter_id]['delete-' . $filter_id] = [
        '#type' => 'button',
        '#value' => $this->t('Delete'),
        '#name' => static::getFilterType() . '-delete-' . $filter_id,
        '#filter_id' => $filter_id,
        '#facet_id' => $filter->getFacetId(),
        '#limit_validation_errors' => [
          array_merge($form['#parents'], [
            'wrapper',
            'edit',
            $filter_id,
            static::getFilterType() . '-delete-' . $filter_id,
          ]),
        ],
        '#ajax' => [
          'callback' => [$this, 'refreshEditFormAjax'],
          'wrapper' => $ajax_wrapper_id,
        ],
        '#executes_submit_callback' => TRUE,
        '#submit' => [[$this, 'deleteFilterValueSubmit']],
      ];
    }

    $form['wrapper']['summary'] = [
      '#type' => 'fieldset',
      '#open' => TRUE,
      '#title' => $this->t('Summary'),
    ];

    $form['wrapper']['summary']['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No @type values set.', ['@type' => static::getFilterType()]),
      '#attributes' => [
        'class' => [static::getFilterType() . '-filters-table'],
      ],
    ];

    $form['wrapper']['summary']['add_new'] = [
      '#type' => 'select',
      '#title' => $this->t('Add @type value for:', ['@type' => static::getFilterType()]),
      '#options' => ['' => $this->t('- None -')] + $available_filters,
      '#ajax' => [
        'callback' => [$this, 'addFacetAjax'],
        'wrapper' => $ajax_wrapper_id,
      ],
      '#executes_submit_callback' => TRUE,
      '#submit' => [[$this, 'addFacetSubmit']],
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
  public static function preRenderOperationButtons(array $form): array {
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
   * Generates the filter id.
   *
   * @param string $id
   *   The facet id.
   * @param array $existing_filters
   *   Existing filters to check for duplicates.
   *
   * @return string
   *   The filter id.
   */
  public static function generateFilterId(string $id, array $existing_filters = []): string {
    $filter_id = md5($id);
    $inc = 1;
    while (in_array($filter_id, $existing_filters)) {
      $filter_id = md5($id . $inc);
      $inc++;
    }

    return $filter_id;
  }

  /**
   * Submit callback for selecting a facet for a new value.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addFacetSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $filter_type = static::getFilterType();
    $key = $filter_type . '_facet_id';
    $form_state->set($key, $triggering_element['#value']);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Ajax request handler for adding a new value.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function addFacetAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -3));
    return $element['wrapper'];
  }

  /**
   * Ajax request handler for editing/deleting existing values.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function refreshEditFormAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -4));
    return $element['wrapper'];
  }

  /**
   * Submit callback for editing existing values from the summary list.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function editFilterSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->set(static::getFilterType() . '_facet_id', $triggering_element['#facet_id']);
    $form_state->set(static::getFilterType() . '_filter_id', $triggering_element['#filter_id']);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit callback for cancelling on the value form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function cancelValueSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->set(static::getFilterType() . '_facet_id', NULL);
    $form_state->set(static::getFilterType() . '_filter_id', NULL);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Set the current values on the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source the filter values belong to.
   * @param array $current_filter_values
   *   The filter values.
   */
  protected static function setCurrentValues(FormStateInterface $form_state, ListSourceInterface $list_source, array $current_filter_values): void {
    $key = 'current_values_' . static::getFilterType();
    $storage = &$form_state->getStorage();
    NestedArray::setValue($storage, [$key, $list_source->getSearchId()], $current_filter_values);
  }

  /**
   * Gets the current values from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source the filter values belong to.
   *
   * @return ListPresetFilter[]
   *   The filter values.
   */
  protected static function getCurrentValues(FormStateInterface $form_state, ListSourceInterface $list_source): array {
    $storage = $form_state->getStorage();
    $key = 'current_values_' . static::getFilterType();
    $current_filter_values = NestedArray::getValue($storage, [$key, $list_source->getSearchId()]);
    return $current_filter_values ?? [];
  }

  /**
   * Checks if the current values from the form state were emptied.
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
  protected static function areCurrentValuesEmpty(FormStateInterface $form_state, ListSourceInterface $list_source): bool {
    $storage = $form_state->getStorage();
    $key = 'current_values_' . static::getFilterType();
    $values = NestedArray::getValue($storage, [$key, $list_source->getSearchId()]);
    // If we have an empty array, it means we removed all the values.
    return is_array($values) && empty($values) ?? FALSE;
  }

}
