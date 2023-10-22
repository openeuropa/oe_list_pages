<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\EntityMetaRelation;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\emr\Entity\EntityMetaInterface;
use Drupal\emr\Plugin\EntityMetaRelationContentFormPluginBase;
use Drupal\oe_list_pages\Form\ListPageConfigurationSubformFactory;
use Drupal\oe_list_pages\ListPageConfiguration;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  protected $entityTypeBundleInfo;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The list page subform configuration factory.
   *
   * @var \Drupal\oe_list_pages\Form\ListPageConfigurationSubformFactory
   */
  protected $subformFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, ListPageConfigurationSubformFactory $subformFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager, $entity_type_manager);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->subformFactory = $subformFactory;
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
      $container->get('oe_list_pages.list_page_configuration_subform_factory')
    );
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

    $form[$key]['#parents'] = [$key];
    $form[$key]['#tree'] = TRUE;
    $subform_state = SubformState::createForSubform($form[$key], $form, $form_state);
    $configuration = $this->getListPageConfigurationFromEntity($entity);

    // Check if the current entity bundle supports default values for its
    // filters.
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $form_state->getFormObject()->getEntity();
    $bundle_entity_type = $entity->getEntityType()->getBundleEntityType();
    $storage = $this->entityTypeManager->getStorage($bundle_entity_type);
    $bundle = $storage->load($entity->bundle());
    $configuration->setDefaultFilterValuesAllowed($bundle->getThirdPartySetting('oe_list_pages', 'default_filter_values_allowed', FALSE));

    $subform = $this->subformFactory->getForm($configuration);
    $form[$key] = $subform->buildForm($form[$key], $subform_state);

    $form[$key]['limit'] = [
      '#type' => 'select',
      '#title' => $this->t('The number of items to show per page'),
      '#options' => [
        10 => '10',
        20 => '20',
      ],
      '#default_value' => $entity_meta_wrapper->getConfiguration()['limit'] ?? 10,
    ];

    // Set the entity meta so we use it in the submit handler.
    $form_state->set($entity_meta_bundle . '_entity_meta', $entity_meta);

    \Drupal::service('module_handler')->alter('list_page_entity_meta_form', $form[$key], $subform_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $host_entity */
    $host_entity = $form_state->getFormObject()->getEntity();

    $key = $this->getFormKey();
    $element = $form[$key];
    $subform_state = SubformState::createForSubform($element, $form, $form_state);
    $configuration = $this->getListPageConfigurationFromEntity($host_entity);
    $subform = $this->subformFactory->getForm($configuration);
    $subform->submitForm($element, $subform_state);
    /** @var \Drupal\oe_list_pages\ListPageConfiguration $configuration */
    $configuration = $subform->getConfiguration();

    // Do not save new entity meta if we don't have required values.
    if (!$configuration->getEntityType() || !$configuration->getBundle()) {
      return;
    }

    $entity_meta_bundle = $this->getPluginDefinition()['entity_meta_bundle'];

    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $form_state->get($entity_meta_bundle . '_entity_meta');
    /** @var \Drupal\oe_list_pages\ListPageWrapper $entity_meta_wrapper */
    $entity_meta_wrapper = $entity_meta->getWrapper();

    $entity_meta_wrapper->setSource($configuration->getEntityType(), $configuration->getBundle());

    $entity_meta_configuration = [];
    $entity_meta_configuration['override_exposed_filters'] = $configuration->isExposedFiltersOverridden();
    $entity_meta_configuration['exposed_filters'] = $configuration->getExposedFilters();
    $entity_meta_configuration['preset_filters'] = $configuration->getDefaultFiltersValues();
    $entity_meta_configuration['limit'] = (int) $form_state->getValue([
      $this->getFormKey(),
      'limit',
    ]);
    $entity_meta_configuration['sort'] = $configuration->getSort();
    $entity_meta_configuration['exposed_sort'] = $configuration->isExposedSort();

    \Drupal::service('module_handler')->alter('list_page_entity_meta_form_submit', $form[$key], $subform_state, $entity_meta_configuration);

    $entity_meta_wrapper->setConfiguration($entity_meta_configuration);

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
   * Instantiates a list page configuration object from an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return \Drupal\oe_list_pages\ListPageConfiguration
   *   The list page configuration.
   */
  public function getListPageConfigurationFromEntity(ContentEntityInterface $entity): ListPageConfiguration {
    return ListPageConfiguration::fromEntity($entity);
  }

}
