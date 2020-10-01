<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\EntityMetaRelation;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\emr\Entity\EntityMetaInterface;
use Drupal\emr\EntityMetaWrapper;
use Drupal\emr\Plugin\EntityMetaRelationContentFormPluginBase;
use Drupal\oe_list_pages\ListPageEvents;
use Drupal\oe_list_pages\ListPageSourceAlterEvent;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\ListSourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Plugin implementation of the entity_meta_relation.
 *
 * @EntityMetaRelation(
 *   id = "oe_list_page",
 *   label = @Translation("List Page"),
 *   entity_meta_bundle = "oe_list_page",
 *   content_form = TRUE,
 *   description = @Translation("List Page."),
 *   attach_by_default = TRUE,
 *   entity_meta_wrapper_class = "\Drupal\oe_list_pages\ListPageWrapper",
 * )
 */
class ListPage extends EntityMetaRelationContentFormPluginBase {

  use StringTranslationTrait;
  use DependencySerializationTrait;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  private $entityTypeBundleInfo;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The list source factory.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactory
   */
  private $listSourceFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, EventDispatcherInterface $dispatcher, ListSourceFactory $list_source_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager, $entity_type_manager);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->eventDispatcher = $dispatcher;
    $this->listSourceFactory = $list_source_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('event_dispatcher'),
      $container->get('oe_list_pages.list_source.factory')
    );
  }

  /**
   * Ajax request handler for updating entity bundles.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function updateEntityBundles(array &$form, FormStateInterface $form_state): array {
    $key = $this->getFormKey();
    return $form[$key];
  }

  /**
   * Ajax request handler for updating exposed filters.
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
    $key = $this->getFormKey();
    // We have to clear #value and #checked manually after processing of
    // checkboxes form element.
    // @see \Drupal\Core\Render\Element\Checkbox.
    if (isset($form[$key]['bundle_wrapper']['exposed_filters_wrapper']['exposed_filters'])) {
      $options = $form[$key]['bundle_wrapper']['exposed_filters_wrapper']['exposed_filters']['#options'];
      $parents = [
        $key,
        'bundle_wrapper',
        'exposed_filters_wrapper',
        'exposed_filters',
      ];
      foreach (array_keys($options) as $option) {
        NestedArray::setValue($form, array_merge($parents, [
          $option,
          '#value',
        ]), 0);
      }
    }
    return $form[$key];
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function build(array $form, FormStateInterface $form_state, ContentEntityInterface $entity): array {
    $key = $this->getFormKey();
    $this->buildFormContainer($form, $form_state, $key);
    $entity_meta_bundle = $this->getPluginDefinition()['entity_meta_bundle'];

    $entity_meta = $this->getListPageEntityMeta($entity, $entity_meta_bundle);
    /** @var \Drupal\oe_list_pages\ListPageWrapper $entity_meta_wrapper */
    $entity_meta_wrapper = $entity_meta->getWrapper();

    $entity_type_options = $this->getEntityTypeOptions();
    $entity_type_id = $entity_meta_wrapper->getSourceEntityType();

    $form[$key]['#id'] = $form[$key]['#id'] ?? Html::getUniqueId($key);

    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element && $triggering_element['#name'] === 'entity_type') {
      $form_state->setValue('bundle', NULL);
      $form_state->setValue(['exposed_filters_wrapper', 'exposed_filters'], []);
    }

    $form[$key]['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Source entity type'),
      '#description' => $this->t('Select the entity type that will be used as the source for this list page.'),
      '#options' => $entity_type_options,
      // If there is no selection, the default entity type will be Node, due to
      // self::fillDefaultEntityMetaValues().
      '#default_value' => $form_state->getValue('entity_type') ?? $entity_type_id,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'updateEntityBundles'],
        'wrapper' => $form[$key]['#id'],
      ],
    ];

    $selected_entity_type = $form[$key]['entity_type']['#default_value'];

    $form[$key]['bundle_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'list-page-entity-bundles',
      ],
    ];

    if (!empty($selected_entity_type)) {
      $bundle_options = $this->getBundleOptions($selected_entity_type);

      $default_bundle = $form_state->getValue('bundle') ?? $entity_meta_wrapper->getSourceEntityBundle();
      if (!isset($default_bundle, $bundle_options) && $entity_meta->isNew()) {
        $default_bundle = NULL;
      }

      $form[$key]['bundle_wrapper']['bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Source bundle'),
        '#default_value' => $default_bundle,
        '#options' => $bundle_options,
        '#required' => TRUE,
        '#ajax' => [
          'callback' => [$this, 'updateExposedFilters'],
          'wrapper' => $form[$key]['#id'],
        ],
      ];
    }

    $form[$key]['bundle_wrapper']['exposed_filters_wrapper'] = [
      '#tree' => TRUE,
      '#type' => 'container',
      '#attributes' => [
        'id' => 'list-page-exposed-filters',
      ],
    ];

    $selected_bundle = $form[$key]['bundle_wrapper']['bundle']['#default_value'];
    if (!$selected_bundle) {
      return $form;
    }

    // Try to get the list source for the selected entity type and bundle.
    $list_source = $this->listSourceFactory->get($selected_entity_type, $selected_bundle);

    // Get available filters.
    if ($list_source && $available_filters = $list_source->getAvailableFilters()) {
      $configuration = $this->getExposedFiltersConfiguration($list_source, $entity_meta_wrapper);
      $overridden = $this->areExposedFiltersOverridden($list_source, $entity_meta_wrapper);
      if (!$overridden && !$configuration) {
        // If the exposed filters are not overridden, the $configuration should
        // be empty so we want to default to the defaults set in the bundle
        // third party setting.
        $configuration = $this->getBundleDefaultExposedFilters($list_source, $entity_meta_wrapper);
      }

      // Override checkbox.
      $form[$key]['bundle_wrapper']['exposed_filters_wrapper']['exposed_filters_override'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Override default exposed filters'),
        '#description' => $this->t('Configure which exposed filters should show up on this page.'),
        '#default_value' => $overridden,
      ];

      $input = $form_state->getUserInput();
      $input['exposed_filters_wrapper']['exposed_filters'] = $configuration;
      $form_state->setUserInput($input);

      $form[$key]['bundle_wrapper']['exposed_filters_wrapper']['exposed_filters'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Exposed filters'),
        '#default_value' => $configuration,
        '#options' => $available_filters,
        '#required' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name="exposed_filters_wrapper[exposed_filters_override]"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    $form[$key]['items_per_page'] = [
      '#type' => 'select',
      '#title' => $this->t('The number of items to show per page'),
      '#options' => [
        10 => '10',
        20 => '20',
      ],
      '#default_value' => $entity_meta_wrapper->getConfiguration()['items_per_page'] ?? 10,
    ];

    // Set the entity meta so we use it in the submit handler.
    $form_state->set($entity_meta_bundle . '_entity_meta', $entity_meta);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, FormStateInterface $form_state): void {
    // Do not save new entity meta if we don't have required values.
    if (!$form_state->getValue('entity_type') || !$form_state->getValue('bundle')) {
      return;
    }
    /** @var \Drupal\Core\Entity\ContentEntityInterface $host_entity */
    $host_entity = $form_state->getFormObject()->getEntity();

    $entity_meta_bundle = $this->getPluginDefinition()['entity_meta_bundle'];

    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $form_state->get($entity_meta_bundle . '_entity_meta');
    /** @var \Drupal\oe_list_pages\ListPageWrapper $entity_meta_wrapper */
    $entity_meta_wrapper = $entity_meta->getWrapper();

    $entity_meta_wrapper->setSource($form_state->getValue('entity_type'), $form_state->getValue('bundle'));
    $selected_filters = array_filter($form_state->getValue([
      'exposed_filters_wrapper',
      'exposed_filters',
    ], []));

    $override = $form_state->getValue('exposed_filters_wrapper')['exposed_filters_override'];
    $configuration = $entity_meta_wrapper->getConfiguration();
    $configuration['override_exposed_filters'] = $override;
    $configuration['exposed_filters'] = $override ? $selected_filters : [];
    $configuration['items_per_page'] = (int) $form_state->getValue('items_per_page');
    $configuration['exposed_filters'] = $override ? $selected_filters : [];
    $entity_meta_wrapper->setConfiguration($configuration);
    $host_entity->get('emr_entity_metas')->attach($entity_meta);
  }

  /**
   * {@inheritdoc}
   */
  public function fillDefaultEntityMetaValues(EntityMetaInterface $entity_meta): void {
    // Set the default value to be the first node bundle.
    // We want to do this because we don't want any entity meta being created
    // without a value (via the API).
    $bundles = $this->entityTypeBundleInfo->getBundleInfo('node');
    /** @var \Drupal\oe_list_pages\ListPageWrapper $wrapper */
    $wrapper = $entity_meta->getWrapper();
    $wrapper->setSource('node', key($bundles));
  }

  /**
   * Get the related List Page entity meta.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param string $entity_meta_bundle
   *   The entity meta bundle.
   *
   * @return \Drupal\emr\Entity\EntityMetaInterface
   *   The entity meta.
   */
  protected function getListPageEntityMeta(ContentEntityInterface $entity, string $entity_meta_bundle): EntityMetaInterface {
    /** @var \Drupal\emr\Field\EntityMetaItemListInterface $entity_meta_list */
    $entity_meta_list = $entity->get('emr_entity_metas');
    /** @var \Drupal\emr\Entity\EntityMetaInterface $navigation_block_entity_meta */
    return $entity_meta_list->getEntityMeta($entity_meta_bundle);
  }

  /**
   * Get available entity types.
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
   * Get available bundles for a given entity type.
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
   * Get whether the exposed filters are overridden.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The selected list source.
   * @param \Drupal\emr\EntityMetaWrapper $entity_meta_wrapper
   *   The current entity meta wrapper.
   *
   * @return bool
   *   Whether the exposed filters are overridden.
   */
  protected function areExposedFiltersOverridden(ListSourceInterface $list_source, EntityMetaWrapper $entity_meta_wrapper): bool {
    $overridden = FALSE;
    if ($list_source && $entity_meta_wrapper->getSourceEntityType() === $list_source->getEntityType() && $entity_meta_wrapper->getSourceEntityBundle() === $list_source->getBundle()) {
      $overridden = !empty($entity_meta_wrapper->getConfiguration()['override_exposed_filters']) ? (bool) $entity_meta_wrapper->getConfiguration()['override_exposed_filters'] : FALSE;
    }
    return $overridden;
  }

  /**
   * Get the exposed filters configuration.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The selected list source.
   * @param \Drupal\emr\EntityMetaWrapper $entity_meta_wrapper
   *   The current entity meta wrapper.
   *
   * @return array
   *   The current unserialized configuration.
   */
  protected function getExposedFiltersConfiguration(ListSourceInterface $list_source, EntityMetaWrapper $entity_meta_wrapper): array {
    // Get currently saved configuration for exposed filters if applicable
    // (we have selected relevant entity type and bundle).
    $configuration = [];
    if ($list_source && $entity_meta_wrapper->getSourceEntityType() === $list_source->getEntityType() && $entity_meta_wrapper->getSourceEntityBundle() === $list_source->getBundle()) {
      $configuration = isset($entity_meta_wrapper->getConfiguration()['exposed_filters']) ? array_keys($entity_meta_wrapper->getConfiguration()['exposed_filters']) : [];
    }
    return $configuration;
  }

  /**
   * Get the default exposed filters configuration from the bundle.
   *
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The selected list source.
   * @param \Drupal\emr\EntityMetaWrapper $entity_meta_wrapper
   *   The current entity meta wrapper.
   *
   * @return array
   *   The exposed filters.
   */
  protected function getBundleDefaultExposedFilters(ListSourceInterface $list_source, EntityMetaWrapper $entity_meta_wrapper): array {
    $bundle_entity_type = $this->entityTypeManager->getDefinition($list_source->getEntityType())->getBundleEntityType();
    $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
    $bundle = $storage->load($list_source->getBundle());
    return $bundle->getThirdPartySetting('oe_list_pages', 'default_exposed_filters', []);
  }

}
