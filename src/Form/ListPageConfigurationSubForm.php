<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Render\Element\Select;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListPageConfigurationSubformInterface;
use Drupal\oe_list_pages\ListPageEvents;
use Drupal\oe_list_pages\ListPageSourceAlterEvent;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Drupal\oe_list_pages\ListSourceInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Default list page configuration subform.
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
   */
  public function __construct(ListPageConfiguration $configuration, EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EventDispatcherInterface $eventDispatcher, ListSourceFactoryInterface $listSourceFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->eventDispatcher = $eventDispatcher;
    $this->listSourceFactory = $listSourceFactory;
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): ListPageConfiguration {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $input = $form_state->getUserInput();
    $entity_type_options = $this->getEntityTypeOptions();
    $entity_type_id = $this->configuration->getEntityType();
    $entity_type_bundle = $this->configuration->getBundle();

    $ajax_wrapper_id = 'list-page-configuration-' . ($form['#parents'] ? '-' . implode('-', $form['#parents']) : '');

    $form['wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => $ajax_wrapper_id,
      ],
    ];

    $selected_entity_type = NestedArray::getValue($input, array_merge($form['#parents'], ['wrapper', 'entity_type'])) ?? $entity_type_id;
    $selected_bundle = NestedArray::getValue($input, array_merge($form['#parents'], ['wrapper', 'bundle'])) ?? $entity_type_bundle;

    $form['wrapper']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Source entity type'),
      '#description' => $this->t('Select the entity type that will be used as the source for this list page.'),
      '#options' => $entity_type_options,
      // If there is no selection, the default entity type will be Node, due to
      // self::fillDefaultEntityMetaValues().
      '#default_value' => $selected_entity_type,
      '#empty_value' => '',
      '#required' => TRUE,
      '#op' => 'entity-type',
      '#ajax' => [
        'callback' => [get_class($this), 'updateEntityBundles'],
        'wrapper' => $ajax_wrapper_id,
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
          'callback' => [$this, 'updateExposedFilters'],
          'wrapper' => $ajax_wrapper_id,
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
        $name = $first_parent . '[' . implode('][', array_merge($parents, ['wrapper', 'exposed_filters_override'])) . ']';
        $form['wrapper']['exposed_filters'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Exposed filters'),
          '#default_value' => $exposed_filters,
          '#options' => $available_filters,
          '#process' => [
            [Checkboxes::class, 'processCheckboxes'],
            [$this, 'processExposedFilters'],
          ],
          '#states' => [
            'visible' => [
              ':input[name="' . $name . '"]' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $entity_type = $form_state->getValue(['wrapper', 'entity_type']);
    $entity_bundle = $form_state->getValue(['wrapper', 'bundle']);
    $exposed_filters_overridden = (bool) $form_state->getValue(['wrapper', 'exposed_filters_override']);
    $exposed_filters = array_filter($form_state->getValue([
      'wrapper',
      'exposed_filters',
    ], []));
    $this->configuration->setEntityType($entity_type);
    $this->configuration->setBundle($entity_bundle);
    $this->configuration->setExposedFiltersOverridden($exposed_filters_overridden);
    if (!$exposed_filters_overridden) {
      $exposed_filters = [];
    }
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
   * Process callback for the exposed filters selection element.
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
  public function processExposedFilters(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $triggering_element = $form_state->getTriggeringElement();
    // If we change the value of the entity type or bundle, we want to reset
    // the value of the submitted exposed filters checkbox. Moreover, we want
    // to set the default exposed filter values to the user input so that the
    // checkboxes can be checked when the user changes the bundle.
    if ($triggering_element && isset($triggering_element['#op']) && in_array($triggering_element['#op'], ['entity-type', 'bundle'])) {
      $values = $entity_type = $form_state->getValue(array_slice($element['#parents'], 0, -1));
      $entity_type = $values['entity_type'];
      $bundle = $values['bundle'];
      if ($entity_type && $bundle) {
        $list_source = $this->listSourceFactory->get($entity_type, $bundle);
        $default_exposed_filters = $this->getBundleDefaultExposedFilters($list_source);
        $input = $form_state->getUserInput();
        NestedArray::setValue($input, $element['#parents'], $default_exposed_filters);
        $form_state->setUserInput($input);
      }

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
  public static function updateEntityBundles(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
    return $element['wrapper'];
  }

  /**
   * Ajax request handler for updating the exposed filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function updateExposedFilters(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $element = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -2));
    // We have to clear #value and #checked manually after processing of
    // checkboxes form element.
    // @see \Drupal\Core\Render\Element\Checkbox.
    if (isset($element['wrapper']['exposed_filters'])) {
      $options = $element['wrapper']['exposed_filters']['#options'];
      $parents = array_merge(array_slice($triggering_element['#array_parents'], 0, -2), ['wrapper', 'exposed_filters']);
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
    foreach ($entity_types as $entity_type_key => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }
      $entity_type_options[$entity_type_key] = $entity_type->getLabel();
    }

    $event = new ListPageSourceAlterEvent(array_keys($entity_type_options));
    $this->eventDispatcher->dispatch(ListPageEvents::ALTER_ENTITY_TYPES, $event);
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
      $bundle_options[$bundle_key] = $bundle['label'];
    }

    $event = new ListPageSourceAlterEvent();
    $event->setBundles($selected_entity_type, array_keys($bundle_options));
    $this->eventDispatcher->dispatch(ListPageEvents::ALTER_BUNDLES, $event);
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

}
