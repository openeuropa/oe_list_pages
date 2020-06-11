<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\EntityMetaRelation;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\emr\Entity\EntityMetaInterface;
use Drupal\emr\Plugin\EntityMetaRelationContentFormPluginBase;
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
  private $entityTypeBundleInfo;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager, $entity_type_manager);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
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
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Ajax request handler.
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
    return $form[$key]['bundle_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $form, FormStateInterface $form_state, ContentEntityInterface $entity): array {
    $key = $this->getFormKey();
    $this->buildFormContainer($form, $form_state, $key);
    $entity_meta_bundle = $this->getPluginDefinition()['entity_meta_bundle'];

    // Get the related List Page entity meta.
    /** @var \Drupal\emr\Field\EntityMetaItemListInterface $entity_meta_list */
    $entity_meta_list = $entity->get('emr_entity_metas');
    /** @var \Drupal\emr\Entity\EntityMetaInterface $navigation_block_entity_meta */
    $entity_meta = $entity_meta_list->getEntityMeta($entity_meta_bundle);
    /** @var \Drupal\oe_list_pages\ListPageWrapper $entity_meta_wrapper */
    $entity_meta_wrapper = $entity_meta->getWrapper();

    $entity_type_options = [];
    $entity_types = $this->entityTypeManager->getDefinitions();
    foreach ($entity_types as $entity_key => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }
      $entity_type_options[$entity_key] = $entity_type->getLabel();
    }

    // @todo throw event to limit the allowed entity types.

    $entity_type_id = $entity_meta_wrapper->getSourceEntityType();

    $form[$key]['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Source entity type'),
      '#description' => $this->t('Select the entity type that will be used as the source for this list page.'),
      '#options' => $entity_type_options,
      '#default_value' => $form_state->getValue('entity_type') ?? $entity_type_id,
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'updateEntityBundles'],
        'disable-refocus' => FALSE,
        'event' => 'change',
        'wrapper' => 'list-page-entity-bundles',
      ],
    ];

    $bundle_options = [];
    if ($form[$key]['entity_type']['#default_value']) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($form[$key]['entity_type']['#default_value']);
      foreach ($bundles as $bundle_key => $bundle) {
        $bundle_options[$bundle_key] = $bundle['label'];
      }

      // @todo throw event to limit the allowed bundles.
    }

    $entity_bundle_id = $entity_meta_wrapper->getSourceEntityBundle();

    $form[$key]['bundle_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'list-page-entity-bundles',
      ],
    ];

    if (!empty($form_state->getValue('entity_type')) || $entity_bundle_id) {
      $form[$key]['bundle_wrapper']['bundle'] = [
        '#type' => 'select',
        '#title' => $this->t('Source bundle'),
        '#default_value' => $form_state->getValue('bundle') ?? $entity_bundle_id,
        '#options' => $bundle_options,
        '#required' => TRUE,
      ];
    }

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

}
