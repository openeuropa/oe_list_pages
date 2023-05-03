<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityTypeInterface;
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
   */
  public function __construct(ListPageConfiguration $configuration, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EventDispatcherInterface $eventDispatcher, ListSourceFactoryInterface $listSourceFactory, DefaultFilterConfigurationBuilder $presetFiltersBuilder, ListPageSortOptionsResolver $sortOptionsResolver) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->eventDispatcher = $eventDispatcher;
    $this->listSourceFactory = $listSourceFactory;
    $this->configuration = $configuration;
    $this->presetFiltersBuilder = $presetFiltersBuilder;
    $this->sortOptionsResolver = $sortOptionsResolver;
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
    $configuration_sort = $this->configuration->getSort();
    $configuration_exposed_sort = $this->configuration->isExposedSort();
    if ($configuration_sort) {
      $configuration_sort = ListPageSortOptionsResolver::generateSortMachineName($configuration_sort);
    }
    else {
      $configuration_sort = NULL;
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
    $selected_sort = $form_state->has('sort') ? $form_state->get('sort') : $configuration_sort;
    $selected_exposed_sort = $form_state->has('exposed_sort') ? $form_state->get('exposed_sort') : $configuration_exposed_sort;
    if (!$form_state->has('entity_type')) {
      $form_state->set('entity_type', $selected_entity_type);
    }

    $form['wrapper']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Source entity type'),
      '#description' => $this->t('Select the entity type that will be used as the source for this list.'),
      '#options' => $entity_type_options,
      // If there is no selection, the default entity type will be Node, due to
      // self::fillDefaultEntityMetaValues().
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
        array_merge($form['#parents'], [
          'wrapper',
          'entity_type',
        ]),
      ],
    ];

    if (!empty($selected_entity_type)) {
      $bundle_options = $this->getBundleOptions($selected_entity_type);

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
          array_merge($form['#parents'], [
            'wrapper',
            'bundle',
          ]),
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

      // Try to get the list source for the selected entity type and bundle.
      $list_source = $this->listSourceFactory->get($selected_entity_type, $selected_bundle);

      if ($list_source) {
        $sort_options = $this->sortOptionsResolver->getSortOptions($list_source);
        $form_state->set('default_bundle_sort', $this->sortOptionsResolver->getBundleDefaultSort($list_source));
        if ($selected_sort && !isset($sort_options[$selected_sort])) {
          // In case we no longer have the sort option, we should not show
          // any default value.
          $selected_sort = NULL;
        }
        $form['wrapper']['sort'] = [
          '#type' => 'select',
          '#options' => $sort_options,
          '#title' => $this->t('Sort'),
          '#default_value' => $selected_sort,
          '#access' => count($sort_options) > 1,
        ];

        $form['wrapper']['exposed_sort'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Expose sort'),
          '#description' => $this->t('Check this box if you would like sorting options to be exposed. Please note that frontend sorting options may differ from the ones available in the backend.'),
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
          // If the exposed filters are not overridden, the configuration should
          // be empty so we want to default to the defaults set in the bundle
          // third party setting.
          $exposed_filters = $bundle_default_exposed_filters;
        }

        // Override checkbox.
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
            '#parents' => array_merge($parents, [
              'wrapper',
              'default_filter_values',
            ]),
            '#tree' => TRUE,
          ];

          $subform_state = SubformState::createForSubform($form['wrapper']['default_filter_values'], $form, $form_state);
          $form['wrapper']['default_filter_values'] = $this->presetFiltersBuilder->buildDefaultFilters($form['wrapper']['default_filter_values'], $subform_state, $list_source, $this->getConfiguration());
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

    // In this form we embed the default filters form as well so if we change
    // entity types, we need to reset any filter selection.
    $form_state->set('default_facet_id', NULL);
    $form_state->set('default_filter_id', NULL);
    $form_state->set('contextual_facet_id', NULL);
    $form_state->set('contextual_filter_id', NULL);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Submit callback when changing the bundle.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function bundleSelectSubmit(array &$form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $form_state->set('bundle', $triggering_element['#value']);
    $form_state->setRebuild(TRUE);

    // In this form we embed the default filters form as well so if we change
    // entity types, we need to reset any filter selection.
    $form_state->set('default_facet_id', NULL);
    $form_state->set('default_filter_id', NULL);
    $form_state->set('contextual_facet_id', NULL);
    $form_state->set('contextual_filter_id', NULL);

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
    $this->configuration->setEntityType($entity_type);
    $this->configuration->setBundle($entity_bundle);
    $this->configuration->setExposedFiltersOverridden($exposed_filters_overridden);
    $this->configuration->setDefaultFilterValues($default_filter_values);
    if (!$exposed_filters_overridden) {
      $exposed_filters = [];
    }

    $sort = $form_state->getValue(['wrapper', 'sort']);
    $default_bundle_sort = $form_state->get('default_bundle_sort');
    if ($default_bundle_sort) {
      // In case a bundle is not properly configured with a default sort, we
      // need to check if the value is there before we generate a machine name.
      $default_bundle_sort = ListPageSortOptionsResolver::generateSortMachineName($default_bundle_sort);
    }

    if ($sort) {
      [$name, $direction] = explode('__', $sort);
      $this->configuration->setSort([
        'name' => $name,
        'direction' => strtoupper($direction),
      ]);
    }

    if ($sort === $default_bundle_sort || $sort == "") {
      // If the user configured the default bundle sort, we remove the value
      // from the configuration in case later we change the defaults, we want
      // the defaults to still take effect.
      $this->configuration->setSort([]);
    }
    $this->configuration->setExposedSort((bool) $form_state->getValue([
      'wrapper',
      'exposed_sort',
    ]));

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

}
