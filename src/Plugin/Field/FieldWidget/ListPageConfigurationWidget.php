<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\oe_list_pages\Form\ListPageConfigurationSubformFactory;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListPageWrapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Widget for the list page configuration.
 *
 * @FieldWidget(
 *   id = "oe_list_pages_configuration",
 *   label = @Translation("List page configuration"),
 *   field_types = {
 *     "string_long",
 *   },
 * )
 */
class ListPageConfigurationWidget extends WidgetBase {

  /**
   * The list page configuration subform factory.
   *
   * @var \Drupal\oe_list_pages\Form\ListPageConfigurationSubformFactory
   */
  protected ListPageConfigurationSubformFactory $subformFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, ListPageConfigurationSubformFactory $subform_factory, ModuleHandlerInterface $module_handler) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->subformFactory = $subform_factory;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('oe_list_pages.list_page_configuration_subform_factory'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $host_entity */
    $host_entity = $items->getEntity();
    $configuration = $this->getConfiguration($host_entity, $form_state);

    $bundle_entity_type_id = $host_entity->getEntityType()->getBundleEntityType();
    if ($bundle_entity_type_id) {
      $bundle = \Drupal::entityTypeManager()->getStorage($bundle_entity_type_id)->load($host_entity->bundle());
      if ($bundle) {
        $configuration->setDefaultFilterValuesAllowed((bool) $bundle->getThirdPartySetting('oe_list_pages', 'default_filter_values_allowed', FALSE));
      }
    }

    $element['oe_list_page'] = [
      '#type' => 'details',
      '#tree' => TRUE,
      '#title' => t('List Page'),
      '#open' => TRUE,
      '#parents' => array_merge($form['#parents'], ['oe_list_page']),
    ];
    $subform_state = SubformState::createForSubform($element['oe_list_page'], $form, $form_state);
    $subform = $this->subformFactory->getForm($configuration);
    $element['oe_list_page'] = $subform->buildForm($element['oe_list_page'], $subform_state);

    $wrapper = $this->getWrapperConfiguration($host_entity);
    $element['oe_list_page']['limit'] = [
      '#type' => 'select',
      '#title' => $this->t('The number of items to show per page'),
      '#options' => [
        10 => '10',
        20 => '20',
        50 => '50',
        100 => '100',
      ],
      '#default_value' => $wrapper['limit'] ?? 10,
    ];

    $form_state->set('entity_meta_bundle', $host_entity->bundle());
    $this->moduleHandler->alter('list_page_configuration_form', $element['oe_list_page']['wrapper'], $form_state);

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $host_entity */
    $host_entity = $items->getEntity();

    $field_name = $this->fieldDefinition->getName();
    $element_parents = array_merge($form['#parents'] ?? [], [$field_name, 'widget', 0]);
    $element = &$form;
    foreach ($element_parents as $key) {
      if (!isset($element[$key])) {
        return;
      }
      $element = &$element[$key];
    }

    $configuration = $this->getConfiguration($host_entity, $form_state);
    $subform_state = SubformState::createForSubform($element['oe_list_page'], $form, $form_state);
    $subform = $this->subformFactory->getForm($configuration);
    $this->moduleHandler->invokeAll('list_page_configuration_form_validate', [
      $element['oe_list_page']['wrapper'] ?? $element['oe_list_page'],
      $form_state,
    ]);
    $subform->submitForm($element['oe_list_page'], $subform_state);
    /** @var \Drupal\oe_list_pages\ListPageConfiguration $configuration */
    $configuration = $subform->getConfiguration();

    if (!$configuration->getEntityType() || !$configuration->getBundle()) {
      return;
    }

    $list_page_configuration_values = [
      'override_exposed_filters' => $configuration->isExposedFiltersOverridden(),
      'exposed_filters' => $configuration->getExposedFilters(),
      'preset_filters' => $configuration->getDefaultFiltersValues(),
      'limit' => (int) $form_state->getValue(['oe_list_page', 'limit']),
      'sort' => $configuration->getSort(),
      'exposed_sort' => $configuration->isExposedSort(),
    ];

    $alter_element = $element['oe_list_page']['wrapper'] ?? $element['oe_list_page'];
    $this->moduleHandler->alter(
      'list_page_configuration_form_submit',
      $alter_element,
      $form_state,
      $list_page_configuration_values,
    );

    $wrapper = new ListPageWrapper($host_entity);
    $wrapper->setSource($configuration->getEntityType(), $configuration->getBundle());
    $wrapper->setConfiguration($list_page_configuration_values);
  }

  /**
   * Builds the configuration for the host entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\oe_list_pages\ListPageConfiguration
   *   The configuration.
   */
  protected function getConfiguration(ContentEntityInterface $entity, FormStateInterface $form_state): ListPageConfiguration {
    if ($entity->hasField('oe_list_page_source') && !$entity->get('oe_list_page_source')->isEmpty()) {
      return ListPageConfiguration::fromEntity($entity);
    }

    // No source yet on this entity — start with a default configuration.
    $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
    return new ListPageConfiguration([
      'entity_type' => 'node',
      'bundle' => key($bundles),
      'extra' => [
        'context_entity' => [
          'entity_type' => $entity->getEntityTypeId(),
          'entity_id' => $entity->id(),
        ],
      ],
    ]);
  }

  /**
   * Returns the raw wrapper-style configuration array for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host entity.
   *
   * @return array
   *   The configuration array.
   */
  protected function getWrapperConfiguration(ContentEntityInterface $entity): array {
    return (new ListPageWrapper($entity))->getConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return $field_definition->getName() === 'oe_list_page_config' && $field_definition->getType() === 'string_long';
  }

}
