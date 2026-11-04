<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_extra_displays;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_list_pages\ListPageWrapper;
use Drupal\oe_list_pages_link_list_displays\ListDisplaySelectionBuilder;

/**
 * Builds the extra displays configuration on the list pages form.
 */
class ExtraDisplaysFormBuilder {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The list display selection builder.
   *
   * @var \Drupal\oe_list_pages_link_list_displays\ListDisplaySelectionBuilder
   */
  protected $listDisplaySelectionBuilder;

  /**
   * Constructs an ExtraDisplaysFormBuilder.
   *
   * @param \Drupal\oe_list_pages_link_list_displays\ListDisplaySelectionBuilder $listDisplaySelectionBuilder
   *   The list display selection builder.
   */
  public function __construct(ListDisplaySelectionBuilder $listDisplaySelectionBuilder) {
    $this->listDisplaySelectionBuilder = $listDisplaySelectionBuilder;
  }

  /**
   * Builds the form elements for configuring the extra displays.
   *
   * @param array $form
   *   The list pages form section.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\Core\Entity\ContentEntityInterface $host_entity
   *   The host entity carrying the list page configuration.
   */
  public function form(array &$form, FormStateInterface $form_state, ContentEntityInterface $host_entity): void {
    $wrapper = new ListPageWrapper($host_entity);
    $configuration = $wrapper->getConfiguration();
    $extra_displays = ExtraDisplaysHelper::getExtraDisplays($configuration['extra'] ?? []);
    $rows = static::getRows($form_state, $extra_displays);

    $parents = array_merge($form['#parents'] ?? [], ['extra_displays']);
    $ajax_wrapper_id = $form['#attributes']['id'];

    $form['extra_displays'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Extra displays'),
      '#description' => $this->t('Visitors can switch between the main display and the extra displays using tabs.'),
    ];

    $form['extra_displays']['primary_title'] = [
      '#type' => 'container',
    ];
    $this->buildTitleElements($form['extra_displays']['primary_title'], 'primary', $extra_displays['primary_title'] ?? []);
    $form['extra_displays']['primary_title']['title']['#title'] = $this->t('Main display tab title');
    $form['extra_displays']['primary_title']['title']['#description'] = $this->t('Required when there are extra displays.');

    $form['extra_displays']['displays'] = [
      '#type' => 'table',
      '#header' => [
        [
          'data' => $this->t('Tab title'),
          'class' => ['extra-displays__title'],
        ],
        [
          'data' => $this->t('Display'),
          'class' => ['extra-displays__display'],
        ],
        $this->t('Weight'),
        [
          'data' => $this->t('Operations'),
          'class' => ['extra-displays__operations'],
        ],
      ],
      '#attributes' => [
        'class' => ['extra-displays'],
      ],
      '#attached' => [
        'library' => ['oe_list_pages_link_list_extra_displays/extra_displays_form'],
      ],
      '#empty' => $this->t('There are no extra displays.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'extra-display-weight',
        ],
      ],
    ];

    $position = 0;
    foreach ($rows as $row_id => $display) {
      $row = [
        '#attributes' => ['class' => ['draggable']],
        '#weight' => $position,
      ];

      // The tab title select and the custom title share the same cell.
      $row['tab_title'] = [
        '#type' => 'container',
      ];
      $this->buildTitleElements($row['tab_title'], 'extra-display-' . $row_id, $display);
      $row['tab_title']['title']['#title_display'] = 'invisible';
      $row['tab_title']['title']['#required'] = TRUE;

      $row['display'] = [
        '#type' => 'container',
      ];
      $this->listDisplaySelectionBuilder->buildPluginElement($row['display'], $form_state, 'extra_display_' . $row_id, array_merge($parents, [
        'displays',
        $row_id,
        'display',
      ]), $display['plugin'] ?? NULL, $display['plugin_configuration'] ?? [], $ajax_wrapper_id);
      $row['display']['plugin']['#title_display'] = 'invisible';
      $row['display']['plugin']['#ajax']['callback'] = [$this, 'ajaxCallback'];
      $row['display']['plugin']['#extra_displays_slice'] = -5;

      $row['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => $position,
        '#delta' => max(10, count($rows)),
        '#attributes' => ['class' => ['extra-display-weight']],
      ];

      $row['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'extra_display_remove_' . $row_id,
        '#submit' => [[static::class, 'removeDisplay']],
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => [$this, 'ajaxCallback'],
          'wrapper' => $ajax_wrapper_id,
        ],
        '#row_id' => $row_id,
        '#extra_displays_slice' => -4,
      ];

      $form['extra_displays']['displays'][$row_id] = $row;
      $position++;
    }

    $form['extra_displays']['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add display'),
      '#name' => 'extra_display_add',
      '#submit' => [[static::class, 'addDisplay']],
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => $ajax_wrapper_id,
      ],
      '#extra_displays_slice' => -2,
    ];
  }

  /**
   * Validates the extra displays.
   *
   * @param array $form
   *   The list pages form section.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validate(array $form, FormStateInterface $form_state): void {
    if (!isset($form['extra_displays'])) {
      return;
    }

    $element = $form['extra_displays'];
    $row_ids = Element::children($element['displays']);
    foreach ($row_ids as $row_id) {
      $row = $element['displays'][$row_id];
      $this->validateCustomTitle($row['tab_title'], $form_state);
      $this->listDisplaySelectionBuilder->validatePluginConfiguration('display', $row, $form_state);
    }

    if (!$row_ids) {
      return;
    }

    if (!$form_state->getValue($element['primary_title']['title']['#parents'])) {
      $form_state->setError($element['primary_title']['title'], $this->t('The main display tab title is required when there are extra displays.'));
    }
    $this->validateCustomTitle($element['primary_title'], $form_state);
  }

  /**
   * Extracts the extra displays configuration from the submitted values.
   *
   * @param array $form
   *   The list pages form section.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The extra displays configuration, empty if there are no extra displays.
   */
  public function extractConfiguration(array $form, FormStateInterface $form_state): array {
    if (!isset($form['extra_displays'])) {
      return [];
    }

    $element = $form['extra_displays'];
    $displays = [];
    $weights = [];
    foreach (Element::children($element['displays']) as $row_id) {
      $row = $element['displays'][$row_id];
      $display_configuration = $this->listDisplaySelectionBuilder->extractPluginConfiguration('display', $row, $form_state);
      if (!$display_configuration) {
        continue;
      }

      $values = $form_state->getValue($row['#parents']);
      $displays[$row_id] = static::extractTitleValues($values['tab_title'] ?? []) + $display_configuration;
      $weights[$row_id] = (int) ($values['weight'] ?? 0);
    }

    if (!$displays) {
      return [];
    }

    uksort($displays, function ($a, $b) use ($weights) {
      return $weights[$a] <=> $weights[$b];
    });

    return [
      'primary_title' => static::extractTitleValues($form_state->getValue($element['primary_title']['#parents'])),
      'displays' => array_values($displays),
    ];
  }

  /**
   * Submit callback for adding an extra display.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function addDisplay(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $storage = static::sortRowsFromInput($form_state, array_slice($triggering_element['#parents'], 0, -1));
    $storage['rows'][$storage['next_id']] = [];
    $storage['next_id']++;
    $form_state->set('oe_list_pages_link_list_extra_displays', $storage);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit callback for removing an extra display.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function removeDisplay(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $storage = static::sortRowsFromInput($form_state, array_slice($triggering_element['#parents'], 0, -3));
    unset($storage['rows'][$triggering_element['#row_id']]);
    $form_state->set('oe_list_pages_link_list_extra_displays', $storage);
    $form_state->setRebuild(TRUE);
  }

  /**
   * The Ajax callback for the extra displays buttons and plugin selects.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The list page form section.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    return NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, $triggering_element['#extra_displays_slice']));
  }

  /**
   * Builds the tab title select and the custom title elements.
   *
   * @param array $element
   *   The element onto which to build the title elements.
   * @param string $states_id
   *   A unique ID used to toggle the custom title visibility.
   * @param array $default
   *   The stored title values.
   */
  protected function buildTitleElements(array &$element, string $states_id, array $default): void {
    $element['title'] = [
      '#type' => 'select',
      '#title' => $this->t('Tab title'),
      '#options' => ExtraDisplaysHelper::getTitleOptions(),
      '#empty_option' => $this->t('- Select -'),
      '#empty_value' => '',
      '#default_value' => $default['title'] ?? '',
      '#attributes' => [
        'data-extra-display-title' => $states_id,
      ],
    ];

    $element['custom_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom title'),
      '#default_value' => $default['custom_title'] ?? '',
      '#size' => 15,
      '#states' => [
        'visible' => [
          'select[data-extra-display-title="' . $states_id . '"]' => ['value' => 'custom'],
        ],
      ],
    ];
  }

  /**
   * Validates that a custom title is provided when it was chosen.
   *
   * @param array $element
   *   The element containing the title elements.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function validateCustomTitle(array $element, FormStateInterface $form_state): void {
    if ($form_state->getValue($element['title']['#parents']) !== 'custom') {
      return;
    }

    if (trim((string) $form_state->getValue($element['custom_title']['#parents'])) === '') {
      $form_state->setError($element['custom_title'], $this->t('The custom title is required.'));
    }
  }

  /**
   * Extracts the title values to store.
   *
   * @param array|null $values
   *   The submitted values containing the "title" and "custom_title".
   *
   * @return array
   *   The title values.
   */
  protected static function extractTitleValues(?array $values): array {
    $title = $values['title'] ?? '';
    return [
      'title' => $title,
      'custom_title' => $title === 'custom' ? trim((string) ($values['custom_title'] ?? '')) : '',
    ];
  }

  /**
   * Returns the extra display rows of the form, keyed by row ID.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $extra_displays
   *   The stored extra displays configuration.
   *
   * @return array
   *   The rows.
   */
  protected static function getRows(FormStateInterface $form_state, array $extra_displays): array {
    $storage = $form_state->get('oe_list_pages_link_list_extra_displays');
    if (!$storage) {
      $rows = array_values($extra_displays['displays'] ?? []);
      $storage = [
        'rows' => $rows,
        'next_id' => count($rows),
      ];
      $form_state->set('oe_list_pages_link_list_extra_displays', $storage);
    }

    return $storage['rows'];
  }

  /**
   * Sorts the rows in the storage by the submitted weights.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $parents
   *   The parents of the extra displays element.
   *
   * @return array
   *   The storage with the sorted rows.
   */
  protected static function sortRowsFromInput(FormStateInterface $form_state, array $parents): array {
    $storage = $form_state->get('oe_list_pages_link_list_extra_displays');
    $input = NestedArray::getValue($form_state->getUserInput(), array_merge($parents, ['displays'])) ?? [];
    uksort($storage['rows'], function ($a, $b) use ($input) {
      return (int) ($input[$a]['weight'] ?? 0) <=> (int) ($input[$b]['weight'] ?? 0);
    });

    return $storage;
  }

}
