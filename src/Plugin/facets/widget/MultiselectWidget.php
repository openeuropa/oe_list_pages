<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\ResultInterface;
use Drupal\oe_list_pages\ListSourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The multiselect list widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_multiselect",
 *   label = @Translation("List pages multiselect"),
 *   description = @Translation("A multiselect search widget."),
 * )
 */
class MultiselectWidget extends ListPagesWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(FacetInterface $facet, ListSourceInterface $list_source = NULL, array $filter_value = []): string {
    $field_definition = $this->getFieldDefinition($facet, $list_source);
    $field_type = !empty($field_definition) ? $field_definition->getType() : NULL;
    $entity_storage = $this->entityTypeManager->getStorage($list_source->getEntityType());
    if ($field_type === 'entity_reference') {
      $referenced_entities = $entity_storage->loadMultiple($filter_value);
      return implode(', ', array_map(function ($referenced_entity) {
        return $referenced_entity->label();
      }, $referenced_entities));
    }
    elseif (in_array($field_type, ['list_integer', 'list_float', 'list_string'])) {
      return implode(', ', array_map(function ($value) use ($field_definition) {
        return $field_definition->getSetting('allowed_values')[$value];
      }, $filter_value));
    }
    else {
      return parent::getDefaultValuesLabel($facet, $list_source, $filter_value);
    }
  }

  /**
   * Gets field definition for the field used in the facet.
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet.
   * @param \Drupal\oe_list_pages\ListSourceInterface $list_source
   *   The list source.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   *   The field definition.
   */
  protected function getFieldDefinition(FacetInterface $facet, ListSourceInterface $list_source): FieldDefinitionInterface {
    $field_id = $list_source->getIndex()->getField($facet->getFieldIdentifier())->getOriginalFieldIdentifier();
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($list_source->getEntityType(), $list_source->getBundle());
    return $field_definitions[$field_id];
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValuesWidget(FacetInterface $facet, ListSourceInterface $list_source = NULL, array $parents = []): ?array {
    $field_definition = $this->getFieldDefinition($facet, $list_source);
    $field_type = !empty($field_definition) ? $field_definition->getType() : NULL;
    $entity_storage = $this->entityTypeManager->getStorage($list_source->getEntityType());
    if ($field_type === 'entity_reference') {
      for ($i = 0; $i < count($facet->getActiveItems()) + 1; $i++) {
        $form[$facet->id() . '_' . $i] = [
          '#type' => 'entity_autocomplete',
          '#target_type' => $field_definition->getTargetEntityTypeId(),
          '#default_value' => !empty($facet->getActiveItems() && $facet->getActiveItems()[$i]) ? $entity_storage->load($facet->getActiveItems()[$i]) : NULL,
          '#selection_settings' => $field_definition->getSettings()['handler_settings'],
        ];
      }

      $form['input_count'] = [
        '#type' => 'value',
        '#value' => $i,
      ];

      return $form;
    }
    elseif (in_array($field_type, ['list_integer', 'list_float', 'list_string'])) {
      $form[$facet->id()] = [
        '#type' => 'select',
        '#default_value' => $facet->getActiveItems()[0],
        '#multiple' => TRUE,
        '#options' => $field_definition->getSetting('allowed_values'),
      ];

      return $form;
    }
    else {
      return $this->build($facet);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet) {
    $results = $facet->getResults();

    $options = [];
    array_walk($results, function (ResultInterface &$result) use (&$options) {
      $options[$result->getRawValue()] = $result->getDisplayValue();
    });

    if ($options) {
      $build[$facet->id()] = [
        '#type' => 'select',
        '#title' => $facet->getName(),
        '#options' => $options,
        '#multiple' => TRUE,
        '#default_value' => $facet->getActiveItems(),
      ];
    }

    $build['#cache']['contexts'] = [
      'url.query_args',
      'url.path',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDefaultValueFilter(FacetInterface $facet, array &$form, FormStateInterface $form_state): array {
    $count = $form_state->getValue('input_count', 0);
    // Used for multi inputs.
    if ($count) {
      for ($i = 0; $i < $count; $i++) {
        $value = $form_state->getValue($facet->id() . '_' . $i);
        if (!empty($value)) {
          $values[] = $value;
        }
      }
      return $values;
    }
    else {
      return $this->prepareValueForUrl($facet, $form, $form_state);
    }
  }

}
