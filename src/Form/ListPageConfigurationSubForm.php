<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Render\Element\Select;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListPageConfigurationSubformInterface;
use Drupal\oe_list_pages\ListPageEvents;
use Drupal\oe_list_pages\ListPageSortOptionsResolver;
use Drupal\oe_list_pages\ListPageSourceAlterEvent;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Drupal\oe_list_pages\ListSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Default list page configuration subform.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class ListPageConfigurationSubForm implements ListPageConfigurationSubformInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The list page configuration.
   *
   * @var \Drupal\oe_list_pages\ListPageConfiguration
   */
  protected $configuration;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The list source factory.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactoryInterface
   */
  protected $listSourceFactory;

  /**
   * The preset filters builder.
   *
   * @var \Drupal\oe_list_pages\DefaultFilterConfigurationBuilder
   */
  protected $presetFiltersBuilder;

  /**
   * The sort options resolver.
   *
   * @var \Drupal\oe_list_pages\ListPageSortOptionsResolver
   */
  protected $sortOptionsResolver;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * ListPageConfigurationSubformFactory constructor.
   *
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The list page configuration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\oe_list_pages\ListSourceFactoryInterface $listSourceFactory
   *   The list source factory.
   * @param \Drupal\oe_list_pages\DefaultFilterConfigurationBuilder $presetFiltersBuilder
   *   The preset filters builder.
   * @param \Drupal\oe_list_pages\ListPageSortOptionsResolver $sortOptionsResolver
   *   The sort options resolver.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager.
   */
  public function __construct(ListPageConfiguration $configuration, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EventDispatcherInterface $eventDispatcher, ListSourceFactoryInterface $listSourceFactory, DefaultFilterConfigurationBuilder $presetFiltersBuilder, ListPageSortOptionsResolver $sortOptionsResolver, EntityFieldManagerInterface $entityFieldManager) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->eventDispatcher = $eventDispatcher;
    $this->listSourceFactory = $listSourceFactory;
    $this->configuration = $configuration;
    $this->presetFiltersBuilder = $presetFiltersBuilder;
    $this->sortOptionsResolver = $sortOptionsResolver;
    $this->entityFieldManager = $entityFieldManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): ListPageConfiguration {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $entity_type_options = $this->getEntityTypeOptions();
    $entity_type_id = $this->configuration->getEntityType();
    $entity_type_bundle = $this->configuration->getBundle();
    $configuration_exposed_sort = $this->configuration->isExposedSort();


    // Initialize sort criteria if not set (backward compatibility).
    $sort_criteria = $this->configuration->getDefaultSort();
    if (empty($sort_criteria)) {
      $this->configuration->setDefaultSort([
        [
          'name' => 'title',
          'direction' => 'ASC',
          'weight' => 0,
        ],
      ]);
    }

    $ajax_wrapper_id = 'list-page-configuration-' . ($form['#parents'] ? '-' . implode('-', $form['#parents']) : '');

    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $ajax_wrapper_id,
      ],
    ];

    $selected_entity_type = $form_state->has('entity_type') ? $form_state->get('entity_type') : $entity_type_id;
    $selected_bundle = $form_state->has('bundle') ? $form_state->get('bundle') : $entity_type_bundle;
    $selected_exposed_sort = $form_state->has('exposed_sort') ? $form_state->get('exposed_sort') : $configuration_exposed_sort;

    if (!$form_state->has('entity_type')) {
      $form_state->set('entity_type', $selected_entity_type);
    }

    // Entity type selection.
    $form['wrapper']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Source entity type'),
      '#description' => $this->t('Select the entity type that will be used as the source for this list.'),
      '#options' => $entity_type_options,
      '#default_value' => $selected_entity_type,
      '#empty_value' => '',
      '#required' => TRUE,
      '#op' => 'entity-type',
      '#ajax' => [
        'callback' => [get_class($this), 'entityTypeSelectAjax'],
        'wrapper' => $ajax_wrapper_id,
      ],
      '#executes_submit_callback' => TRUE,
      '#submit' => [[$this, 'entityTypeSelectSubmit']],
      '#limit_validation_errors' => [
        array_merge($form['#parents'], ['wrapper', 'entity_type']),
      ],
    ];

    if (!empty($selected_entity_type)) {
      $bundle_options = $this->getBundleOptions($selected_entity_type);

      // Bundle selection.
      $form['wrapper']['bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Source bundle'),
        '#default_value' => isset($bundle_options[$entity_type_bundle]) ? $entity_type_bundle : '',
        '#options' => $bundle_options,
        '#empty_value' => '',
        '#op' => 'bundle',
        '#required' => TRUE,
        '#ajax' => [
          'callback' => [$this, 'bundleSelectAjax'],
          'wrapper' => $ajax_wrapper_id,
        ],
        '#executes_submit_callback' => TRUE,
        '#submit' => [[$this, 'bundleSelectSubmit']],
        '#limit_validation_errors' => [
          array_merge($form['#parents'], ['wrapper', 'bundle']),
        ],
        '#process' => [
          [Select::class, 'processSelect'],
          [Select::class, 'processAjaxForm'],
          [$this, 'processBundle'],
        ],
      ];

      if (!$selected_bundle) {
        return $form;
      }

      // Get the list source for the selected entity type and bundle.
      $list_source = $this->listSourceFactory->get($selected_entity_type, $selected_bundle);

      if ($list_source) {
        $form_state->set('default_bundle_sort', $this->sortOptionsResolver->getBundleDefaultSort($list_source));

        // Sort criteria table with drag-and-drop support.
        $form_parents = $form['#parents'] ?? [];
        $this->buildSortCriteria($form['wrapper'], $form_state, $list_source, $form_parents);

        // Expose sort checkbox (only if multiple sort criteria are allowed).
        $form['wrapper']['exposed_sort'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Expose sort'),
          '#description' => $this->t('Check this box if you would like sorting options to be exposed.'),
          '#default_value' => $selected_exposed_sort,
          '#access' => $this->sortOptionsResolver->isExposedSortAllowed($list_source),
        ];
      }

      // Get available filters.
      if ($list_source && $available_filters = $list_source->getAvailableFilters()) {
        $exposed_filters = $this->getExposedFilters($list_source);
        $exposed_filters_overridden = $this->areExposedFiltersOverridden($list_source);
        $bundle_default_exposed_filters = $this->getBundleDefaultExposedFilters($list_source);

        if (!$exposed_filters_overridden && !$exposed_filters) {
          // If exposed filters are not overridden, default to bundle settings.
          $exposed_filters = $bundle_default_exposed_filters;
        }

        // Override checkbox for exposed filters.
        $form['wrapper']['exposed_filters_override'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Override default exposed filters'),
          '#description' => $this->t('Configure which exposed filters should show up on this page.'),
          '#default_value' => $exposed_filters_overridden,
        ];

        $parents = $form['#parents'];
        $first_parent = array_shift($parents);
        $name = $first_parent . '[' . implode('][', array_merge($parents, [
          'wrapper',
          'exposed_filters_override',
        ])) . ']';

        $form['wrapper']['exposed_filters'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Exposed filters'),
          '#default_value' => $exposed_filters,
          '#options' => $available_filters,
          '#states' => [
            'visible' => [
              ':input[name="' . $name . '"]' => ['checked' => TRUE],
            ],
          ],
        ];

        if ($this->getConfiguration()->areDefaultFilterValuesAllowed()) {
          $parents = $form['#parents'] ?? [];
          $form['wrapper']['default_filter_values'] = [
            '#parents' => array_merge($parents, ['wrapper', 'default_filter_values']),
            '#tree' => TRUE,
          ];

          $subform_state = SubformState::createForSubform($form['wrapper']['default_filter_values'], $form, $form_state);
          $form['wrapper']['default_filter_values'] = $this->presetFiltersBuilder->buildDefaultFilters(
            $form['wrapper']['default_filter_values'],
            $subform_state,
            $list_source,
            $this->getConfiguration()
          );
        }
      }
    }

    return $form;
  }

  /**
   * Submit callback when changing the entity type.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function entityTypeSelectSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->set('entity_type', $triggering_element['#value']);
    $form_state->set('bundle', NULL);

    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -4));
    $ajax_wrapper_id_part = ($element['#parents'] ? '-' . implode('-', $element['#parents']) : '');

    // In this form we embed the default filters form as well so if we change
    // entity types, we need to reset any filter selection.
    $remove = [];
    foreach ($form_state->getStorage() as $name => $value) {
      if (strpos($name, $ajax_wrapper_id_part) === FALSE) {
        continue;
      }

      if (str_starts_with($name, 'default_facet_id') || str_starts_with($name, 'default_filter_id') || str_starts_with($name, 'contextual_facet_id') || str_starts_with($name, 'contextual_filter_id')) {
        $remove[] = $name;
      }
    }

    foreach ($remove as $name) {
      $form_state->set($name, NULL);
    }

    // Reset sort criteria when entity type changes (fields differ).
    $form_state->set('sort_criteria', NULL);

    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit callback when changing the bundle.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function bundleSelectSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->set('bundle', $triggering_element['#value']);
    $form_state->setRebuild(TRUE);

    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -4));
    $ajax_wrapper_id_part = ($element['#parents'] ? '-' . implode('-', $element['#parents']) : '');

    // In this form we embed the default filters form as well so if we change
    // entity types, we need to reset any filter selection.
    $remove = [];
    foreach ($form_state->getStorage() as $name => $value) {
      if (strpos($name, $ajax_wrapper_id_part) === FALSE) {
        continue;
      }

      if (str_starts_with($name, 'default_facet_id') || str_starts_with($name, 'default_filter_id') || str_starts_with($name, 'contextual_facet_id') || str_starts_with($name, 'contextual_filter_id')) {
        $remove[] = $name;
      }
    }

    foreach ($remove as $name) {
      $form_state->set($name, NULL);
    }

    // Reset sort criteria when bundle changes (fields may differ).
    $form_state->set('sort_criteria', NULL);

    // When we change the bundle, we want to set the default exposed filter
    // values to the user input so that the checkboxes can be checked when the
    // user changes the bundle.
    $parents = array_merge(array_slice($triggering_element['#parents'], 0, -1), ['exposed_filters']);
    $entity_type = $form_state->get('entity_type');
    $list_source = $this->listSourceFactory->get($entity_type, $triggering_element['#value']);
    if ($list_source instanceof ListSourceInterface) {
      $default_exposed_filters = $this->getBundleDefaultExposedFilters($list_source);
      $input = $form_state->getUserInput();
      NestedArray::setValue($input, $parents, $default_exposed_filters);
      $form_state->setUserInput($input);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity_type = $form_state->getValue(['wrapper', 'entity_type']);
    $entity_bundle = $form_state->getValue(['wrapper', 'bundle']);
    $exposed_filters_overridden = (bool) $form_state->getValue([
      'wrapper',
      'exposed_filters_override',
    ]);
    $exposed_filters = array_filter($form_state->getValue([
      'wrapper',
      'exposed_filters',
    ], []));
    $default_filter_values = array_filter($form_state->getValue([
      'wrapper',
      'default_filter_values',
      'current_filters',
    ], []));

    // Save entity type and bundle.
    $this->configuration->setEntityType($entity_type);
    $this->configuration->setBundle($entity_bundle);
    $this->configuration->setExposedFiltersOverridden($exposed_filters_overridden);
    $this->configuration->setDefaultFilterValues($default_filter_values);

    if (!$exposed_filters_overridden) {
      $exposed_filters = [];
    }

    // Process promotion settings.
    $promotion = $this->collectPromotionFromFormState($form_state);
    if (!empty($promotion['enabled']) && !empty($promotion['values'])) {
      // Filter out incomplete rows (missing field or value).
      $promotion['values'] = array_filter($promotion['values'], function ($pv) {
        return !empty($pv['field']) && !empty($pv['value']);
      });
      // Sort promoted values by weight.
      uasort($promotion['values'], fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
      $promotion['values'] = array_values($promotion['values']);
    }
    $this->configuration->setPromotion($promotion);

    // Process sort criteria.
    $raw_criteria = $this->collectSortCriteriaFromFormState($form_state);
    $sort_criteria = [];
    foreach ($raw_criteria as $criterion) {
      if (!empty($criterion['name'])) {
        $sort_criteria[] = [
          'name' => $criterion['name'],
          'direction' => $criterion['direction'] ?? 'ASC',
          'weight' => $criterion['weight'] ?? 0,
        ];
      }
    }

    // Sort by weight before saving.
    uasort($sort_criteria, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
    $this->configuration->setDefaultSort(array_values($sort_criteria));

    // Save exposed sort setting.
    $this->configuration->setExposedSort((bool) $form_state->getValue([
      'wrapper',
      'exposed_sort',
    ]));

    // Save exposed filters.
    $this->configuration->setExposedFilters($exposed_filters);
  }

  /**
   * Process callback for the bundle selection element.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public function processBundle(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $triggering_element = $form_state->getTriggeringElement();
    // If we change the value of the entity type, we need to reset the value of
    // the already selected bundle.
    if ($triggering_element && isset($triggering_element['#op']) && $triggering_element['#op'] === 'entity-type') {
      $element['#value'] = [];
    }

    return $element;
  }

  /**
   * Ajax request handler for updating the entity bundles.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public static function entityTypeSelectAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
    return $element['wrapper'];
  }

  /**
   * Ajax callback for when the bundle is selected.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function bundleSelectAjax(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
    // We have to clear #value and #checked manually after processing of
    // checkboxes form element.
    // @see \Drupal\Core\Render\Element\Checkbox.
    if (isset($element['wrapper']['exposed_filters'])) {
      $options = $element['wrapper']['exposed_filters']['#options'];
      $parents = array_merge(array_slice($triggering_element['#array_parents'], 0, -2), [
        'wrapper',
        'exposed_filters',
      ]);
      foreach (array_keys($options) as $option) {
        NestedArray::setValue($form, array_merge($parents, [
          $option,
          '#value',
        ]), 0);
      }
    }
    return $element['wrapper'];
  }

  /**
   * Returns the exposed filters from configuration.
   *
   * It checks that the configuration values matches the one from the selected
   * list source in case the user has made a change in the form.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   *
   * @return array
   *   The exposed filters.
   */
  protected function getExposedFilters(ListSourceInterface $list_source): array {
    $exposed_filters = [];
    if ($list_source->getEntityType() === $this->configuration->getEntityType() && $list_source->getBundle() === $this->configuration->getBundle()) {
      return $this->configuration->getExposedFilters();
    }

    return $exposed_filters;
  }

  /**
   * Returns whether the exposed filters are overridden.
   *
   * It checks that the configuration values matches the one from the selected
   * list source in case the user has made a change in the form.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   *
   * @return bool
   *   The exposed filters.
   */
  protected function areExposedFiltersOverridden(ListSourceInterface $list_source): bool {
    $overridden = FALSE;
    if ($list_source->getEntityType() === $this->configuration->getEntityType() && $list_source->getBundle() === $this->configuration->getBundle()) {
      return $this->configuration->isExposedFiltersOverridden();
    }

    return $overridden;
  }

  /**
   * Get the available entity types.
   *
   * @return array
   *   The array of entity type labels keyed by machine name.
   */
  protected function getEntityTypeOptions(): array {
    $entity_type_options = [];
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface || !$this->listSourceFactory->isEntityTypeSourced($entity_type_id)) {
        continue;
      }
      $entity_type_options[$entity_type_id] = $entity_type->getLabel();
    }

    $event = new ListPageSourceAlterEvent(array_keys($entity_type_options));
    if ($this->configuration->getListSource()) {
      $event->setListSource($this->configuration->getListSource());
    }
    $this->eventDispatcher->dispatch($event, ListPageEvents::ALTER_ENTITY_TYPES);
    return array_intersect_key($entity_type_options, array_combine($event->getEntityTypes(), $event->getEntityTypes()));
  }

  /**
   * Get the available bundles for a given entity type.
   *
   * @param string|null $selected_entity_type
   *   The entity type id.
   *
   * @return array
   *   The array of bundles keyed by machine name.
   */
  protected function getBundleOptions(string $selected_entity_type): array {
    $bundle_options = [];
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($selected_entity_type);
    foreach ($bundles as $bundle_key => $bundle) {
      $list_source = $this->listSourceFactory->get($selected_entity_type, $bundle_key);
      if (!$list_source instanceof ListSourceInterface) {
        continue;
      }

      $bundle_options[$bundle_key] = $bundle['label'];
    }

    $event = new ListPageSourceAlterEvent();
    $event->setBundles($selected_entity_type, array_keys($bundle_options));
    if ($this->configuration->getListSource()) {
      $event->setListSource($this->configuration->getListSource());
    }
    $this->eventDispatcher->dispatch($event, ListPageEvents::ALTER_BUNDLES);
    return array_intersect_key($bundle_options, array_combine($event->getBundles(), $event->getBundles()));
  }

  /**
   * Get the default exposed filters configuration from the bundle.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The selected list source.
   *
   * @return array
   *   The exposed filters.
   */
  protected function getBundleDefaultExposedFilters(ListSourceInterface $list_source): array {
    $bundle_entity_type = $this->entityTypeManager->getDefinition($list_source->getEntityType())->getBundleEntityType();
    $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
    $bundle = $storage->load($list_source->getBundle());
    return $bundle->getThirdPartySetting('oe_list_pages', 'default_exposed_filters', []);
  }

  /**
   * Generates a sort machine name.
   *
   * @param array $sort
   *   The sort information.
   *
   * @deprecated. Use ListPageSortOptionsResolver::generateSortMachineName()
   * instead.
   *
   * @return string
   *   The machine name.
   */
  public static function generateSortMachineName(array $sort): string {
    return ListPageSortOptionsResolver::generateSortMachineName($sort);
  }

  /**
   * Builds the promotion and sort criteria sections.
   *
   * Structure:
   * 1. Sorting fieldset (wrapper)
   *    1.1. Promotion section (optional) - items to show first
   *    1.2. Sort criteria section - how to sort remaining items
   *
   * @param array $form
   *   The form array (the 'wrapper' container).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   * @param array $form_parents
   *   The parent form's #parents array for building #states selectors.
   */
  protected function buildSortCriteria(array &$form, FormStateInterface $form_state, ListSourceInterface $list_source, array $form_parents = []): void {
    $wrapper_id = $this->getSortWrapperId();
    $field_options = $this->getSortFieldOptions($list_source);

    $form['sorting_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Default sorting'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#attributes' => ['id' => $wrapper_id],
    ];

    // ==========================================
    // SECTION 1: PROMOTION (items to show first)
    // ==========================================
    $this->buildPromotionSection($form['sorting_container'], $form_state, $field_options, $form_parents, $wrapper_id);

    // ==========================================
    // SECTION 2: SORT CRITERIA (for other items)
    // ==========================================
    $this->buildSortSection($form['sorting_container'], $form_state, $field_options, $wrapper_id);
  }

  /**
   * Builds the promotion section of the form.
   *
   * @param array $form
   *   The form array (the sorting_container).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $field_options
   *   Available field options.
   * @param array $form_parents
   *   The parent form's #parents array.
   * @param string $wrapper_id
   *   The AJAX wrapper ID.
   */
  protected function buildPromotionSection(array &$form, FormStateInterface $form_state, array $field_options, array $form_parents, string $wrapper_id): void {
    $promotion = $this->getPromotionFromState($form_state);
    $promotion_enabled = !empty($promotion['enabled']);
    $promoted_values = $promotion['values'] ?? [];

    // Build checkbox name for #states.
    $checkbox_name = $this->buildFormElementName(array_merge($form_parents, [
      'wrapper',
      'sorting_container',
      'promotion',
      'enabled',
    ]));

    $form['promotion'] = [
      '#type' => 'details',
      '#title' => $this->t('Promotion (highlight specific items first)'),
      '#open' => $promotion_enabled,
      '#tree' => TRUE,
    ];

    $form['promotion']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Promote specific values first'),
      '#description' => $this->t('Items matching promoted values will appear at the top of the list, before sorted items.'),
      '#default_value' => $promotion_enabled,
    ];

    $form['promotion']['settings'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="' . $checkbox_name . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['promotion']['settings']['values'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Field'),
        $this->t('Value to promote'),
        ['data' => $this->t('Weight'), 'class' => ['element-hidden']],
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No promoted values. Add values that should appear first.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'promoted-value-weight',
        ],
      ],
    ];

    // Sort by weight.
    if (!empty($promoted_values)) {
      uasort($promoted_values, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
    }

    foreach ($promoted_values as $pv_delta => $pv) {
      $form['promotion']['settings']['values'][$pv_delta] = [
        '#attributes' => ['class' => ['draggable']],
        'field' => [
          '#type' => 'select',
          '#title' => $this->t('Field'),
          '#title_display' => 'invisible',
          '#options' => ['' => $this->t('- Select field -')] + $field_options,
          '#default_value' => $pv['field'] ?? '',
        ],
        'value' => [
          '#type' => 'textfield',
          '#title' => $this->t('Value'),
          '#title_display' => 'invisible',
          '#default_value' => $pv['value'] ?? '',
          '#size' => 30,
          '#placeholder' => $this->t('Enter exact value to promote'),
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $pv['weight'] ?? $pv_delta,
          '#attributes' => ['class' => ['promoted-value-weight']],
        ],
        'remove' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_promoted_value_' . $pv_delta,
          '#submit' => [[$this, 'removePromotedValue']],
          '#ajax' => [
            'callback' => [$this, 'updateSortCriteria'],
            'wrapper' => $wrapper_id,
          ],
          '#limit_validation_errors' => [],
        ],
      ];
    }

    $form['promotion']['settings']['add_promoted'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add promoted value'),
      '#name' => 'add_promoted_value',
      '#submit' => [[$this, 'addPromotedValue']],
      '#ajax' => [
        'callback' => [$this, 'updateSortCriteria'],
        'wrapper' => $wrapper_id,
      ],
      '#limit_validation_errors' => [],
    ];
  }

  /**
   * Builds the sort criteria section of the form.
   *
   * @param array $form
   *   The form array (the sorting_container).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $field_options
   *   Available field options.
   * @param string $wrapper_id
   *   The AJAX wrapper ID.
   */
  protected function buildSortSection(array &$form, FormStateInterface $form_state, array $field_options, string $wrapper_id): void {
    $sort_criteria = $this->getSortCriteria($form_state);

    $form['default_sort'] = [
      '#type' => 'details',
      '#title' => $this->t('Sort criteria'),
      '#description' => $this->t('Define how items are sorted. Promoted items (if any) will appear first, then remaining items will be sorted according to these criteria.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['default_sort']['criteria'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Field'),
        $this->t('Direction'),
        ['data' => $this->t('Weight'), 'class' => ['element-hidden']],
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No sort criteria defined.'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'sort-criteria-weight',
        ],
      ],
    ];

    // Sort by weight.
    uasort($sort_criteria, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));

    foreach ($sort_criteria as $delta => $criterion) {
      $form['default_sort']['criteria'][$delta] = [
        '#attributes' => ['class' => ['draggable']],
        'name' => [
          '#type' => 'select',
          '#title' => $this->t('Field'),
          '#title_display' => 'invisible',
          '#options' => $field_options,
          '#default_value' => $criterion['name'] ?? '',
        ],
        'direction' => [
          '#type' => 'select',
          '#title' => $this->t('Direction'),
          '#title_display' => 'invisible',
          '#options' => [
            'ASC' => $this->t('Ascending'),
            'DESC' => $this->t('Descending'),
          ],
          '#default_value' => $criterion['direction'] ?? 'ASC',
        ],
        'weight' => [
          '#type' => 'weight',
          '#title' => $this->t('Weight'),
          '#title_display' => 'invisible',
          '#default_value' => $criterion['weight'] ?? $delta,
          '#attributes' => ['class' => ['sort-criteria-weight']],
        ],
        'operations' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#name' => 'remove_sort_criterion_' . $delta,
          '#submit' => [[$this, 'removeSortCriterion']],
          '#ajax' => [
            'callback' => [$this, 'updateSortCriteria'],
            'wrapper' => $wrapper_id,
          ],
          '#limit_validation_errors' => [],
        ],
      ];
    }

    $form['default_sort']['add_criterion'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add sort criterion'),
      '#submit' => [[$this, 'addSortCriterion']],
      '#ajax' => [
        'callback' => [$this, 'updateSortCriteria'],
        'wrapper' => $wrapper_id,
      ],
      '#limit_validation_errors' => [],
    ];
  }

  /**
   * Gets the promotion settings from form state or configuration.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The promotion settings.
   */
  protected function getPromotionFromState(FormStateInterface $form_state): array {
    if ($form_state->has('promotion')) {
      return $form_state->get('promotion');
    }
    return $this->configuration->getPromotion() ?: [];
  }

  /**
   * Builds a form element name from an array of parents.
   *
   * @param array $parents
   *   The array of parent keys.
   *
   * @return string
   *   The form element name (e.g., "parent[child][subchild]").
   */
  protected function buildFormElementName(array $parents): string {
    $first = array_shift($parents);
    if (empty($parents)) {
      return $first;
    }
    return $first . '[' . implode('][', $parents) . ']';
  }

  /**
   * Gets the current sort criteria from form state or configuration.
   *
   * After an add/remove AJAX round-trip the updated array is stored in
   * form_state storage so that it survives the rebuild.  On initial load
   * the saved configuration is used instead.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The sort criteria.
   */
  protected function getSortCriteria(FormStateInterface $form_state): array {
    if ($form_state->has('sort_criteria')) {
      return $form_state->get('sort_criteria');
    }

    return $this->configuration->getDefaultSort() ?: [];
  }

  /**
   * Gets the AJAX wrapper ID for sort criteria.
   *
   * @return string
   *   The wrapper ID.
   */
  protected function getSortWrapperId(): string {
    return 'list-page-sort-criteria';
  }

  /**
   * Form submission handler for adding a new sort criterion.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addSortCriterion(array &$form, FormStateInterface $form_state): void {
    $sort_criteria = $this->collectSortCriteriaFromFormState($form_state);
    $this->collectPromotionFromFormState($form_state);

    $sort_criteria[] = [
      'name' => '',
      'direction' => 'ASC',
      'weight' => count($sort_criteria),
    ];

    $form_state->set('sort_criteria', $sort_criteria);
    $form_state->setRebuild();
  }

  /**
   * Form submission handler for removing a sort criterion.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removeSortCriterion(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#array_parents'];
    $criteria_index = array_search('criteria', $parents);
    $delta = $parents[$criteria_index + 1];

    $sort_criteria = $this->collectSortCriteriaFromFormState($form_state);
    $this->collectPromotionFromFormState($form_state);
    unset($sort_criteria[$delta]);

    // Reindex and reassign sequential weights.
    $sort_criteria = array_values($sort_criteria);
    foreach ($sort_criteria as $i => &$criterion) {
      $criterion['weight'] = $i;
    }

    $form_state->set('sort_criteria', $sort_criteria);
    $form_state->setRebuild();
  }

  /**
   * Form submission handler for adding a promoted value.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function addPromotedValue(array &$form, FormStateInterface $form_state): void {
    $promotion = $this->collectPromotionFromFormState($form_state);
    $this->collectSortCriteriaFromFormState($form_state);

    $promotion['values'][] = [
      'field' => '',
      'value' => '',
      'weight' => count($promotion['values'] ?? []),
    ];

    $form_state->set('promotion', $promotion);
    $form_state->setRebuild();
  }

  /**
   * Form submission handler for removing a promoted value.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function removePromotedValue(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $button_name = $triggering_element['#name'];
    preg_match('/remove_promoted_value_(\d+)/', $button_name, $matches);
    $pv_delta = (int) $matches[1];

    $promotion = $this->collectPromotionFromFormState($form_state);
    $this->collectSortCriteriaFromFormState($form_state);

    if (isset($promotion['values'][$pv_delta])) {
      unset($promotion['values'][$pv_delta]);
      $promotion['values'] = array_values($promotion['values']);
      foreach ($promotion['values'] as $i => &$pv) {
        $pv['weight'] = $i;
      }
    }

    $form_state->set('promotion', $promotion);
    $form_state->setRebuild();
  }

  /**
   * Collects sort criteria from form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The sort criteria array.
   */
  protected function collectSortCriteriaFromFormState(FormStateInterface $form_state): array {
    $criteria_values = $form_state->getValue(['wrapper', 'sorting_container', 'default_sort', 'criteria'], []);
    if (empty($criteria_values) || !is_array($criteria_values)) {
      $sort_criteria = $this->configuration->getDefaultSort() ?: [];
      $form_state->set('sort_criteria', $sort_criteria);
      return $sort_criteria;
    }

    $sort_criteria = [];
    foreach ($criteria_values as $delta => $row) {
      if (!is_array($row)) {
        continue;
      }
      $sort_criteria[$delta] = [
        'name' => $row['name'] ?? '',
        'direction' => $row['direction'] ?? 'ASC',
        'weight' => $row['weight'] ?? $delta,
      ];
    }

    $form_state->set('sort_criteria', $sort_criteria);
    return $sort_criteria;
  }

  /**
   * Collects promotion settings from form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The promotion settings.
   */
  protected function collectPromotionFromFormState(FormStateInterface $form_state): array {
    $promo_values = $form_state->getValue(['wrapper', 'sorting_container', 'promotion'], []);
    if (empty($promo_values) || !is_array($promo_values)) {
      $promotion = $this->configuration->getPromotion() ?: [];
      $form_state->set('promotion', $promotion);
      return $promotion;
    }

    $promotion = [
      'enabled' => !empty($promo_values['enabled']),
      'values' => [],
    ];

    $values = $promo_values['settings']['values'] ?? [];
    if (is_array($values)) {
      foreach ($values as $pv_delta => $pv) {
        if (is_array($pv)) {
          // Keep all rows during collection (even incomplete ones for AJAX).
          // Filtering happens in submitForm.
          $promotion['values'][] = [
            'field' => $pv['field'] ?? '',
            'value' => $pv['value'] ?? '',
            'weight' => $pv['weight'] ?? $pv_delta,
          ];
        }
      }
    }

    $form_state->set('promotion', $promotion);
    return $promotion;
  }

  /**
   * Gets available sort field options for the select element.
   *
   * The fields come from TWO sources that are intersected:
   *
   * 1. Search API Index fields: Only fields that are indexed in Search API
   *    can be used for sorting. These are configured in the Search API index
   *    configuration (admin/config/search/search-api/index/[index_name]/fields).
   *
   * 2. Entity field definitions: We filter the index fields to only show
   *    those that belong to the selected bundle. This is done by comparing
   *    the index field's "property path" with the entity's field definitions.
   *    - Base fields (title, created, changed, langcode, etc.) are available
   *      for all bundles.
   *    - Bundle-specific fields (field_*) are only shown for their bundle.
   *
   * The field's "property path" in Search API corresponds to the Drupal field
   * name (e.g., "created", "field_publication_date"). For nested fields like
   * entity references, the path may be "field_ref:entity:title".
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   *
   * @return array
   *   Field options keyed by Search API field identifier.
   */
  protected function getSortFieldOptions(ListSourceInterface $list_source): array {
    $field_options = [];
    $index = $list_source->getIndex();
    $entity_type = $list_source->getEntityType();
    $bundle = $list_source->getBundle();

    // Fields to exclude from sort options (not useful for sorting).
    $excluded_fields = [
      'status',
      // The 'type' field is the bundle field - not useful for sorting within
      // a single bundle context.
      'type',
      // Search API internal fields.
      'search_api_datasource',
      'search_api_language',
    ];

    // Get the field definitions for this bundle. This includes:
    // - Base fields: title, created, changed, uid, langcode, etc.
    // - Bundle-specific fields: field_* custom fields for this bundle.
    $bundle_field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $bundle_field_names = array_keys($bundle_field_definitions);

    foreach ($index->getFields() as $field_id => $field) {
      // Skip excluded fields.
      if (in_array($field_id, $excluded_fields)) {
        continue;
      }

      // Get the property path to determine which entity field this index field
      // corresponds to. The property path is the Drupal field name.
      // For nested fields (e.g., "field_ref:entity:title"), we check the
      // first part which is the main field on this entity.
      $property_path = $field->getPropertyPath();
      $field_name = explode(':', $property_path)[0];

      // Only include fields that exist on the selected bundle.
      // This filters out fields from other bundles in the same index.
      if (in_array($field_name, $bundle_field_names)) {
        $field_options[$field_id] = $field->getLabel();
      }
    }

    // Sort alphabetically by label for better UX.
    asort($field_options);

    return $field_options;
  }

  /**
   * AJAX callback to update the sort criteria table.
   *
   * Simply returns the rebuilt container element that carries the AJAX
   * wrapper ID.  The form has already been rebuilt at this point because
   * both add and remove callbacks call setRebuild().
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The updated sort criteria container.
   */
  public function updateSortCriteria(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#array_parents'];
    // Find the sorting_container in the parents array.
    $index = array_search('sorting_container', $parents);
    if ($index === FALSE) {
      // Fallback to default_sort for backward compatibility.
      $index = array_search('default_sort', $parents);
    }

    return NestedArray::getValue($form, array_slice($parents, 0, $index + 1));
  }

}
