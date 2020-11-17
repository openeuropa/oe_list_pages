<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Result\Result;
use Drupal\facets\Result\ResultInterface;
use Drupal\multivalue_form_element\Element\MultiValue;
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
  public function buildDefaultValueForm(array $form, FormStateInterface $form_state, FacetInterface $facet): array {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $field_definition = $this->getFieldDefinition($facet, $list_source);
    $field_type = !empty($field_definition) ? $field_definition->getType() : NULL;
    $active_items = $facet->getActiveItems();

    $form[$facet->id()] = [
      '#type' => 'multivalue',
      '#title' => $field_definition->getLabel(),
      '#required' => TRUE,
    ];

    // First, we cover entity references.
    if (in_array(EntityReferenceFieldItemListInterface::class, class_implements($field_definition->getClass()))) {
      $entity_storage = $this->entityTypeManager->getStorage($field_definition->getSetting('target_type'));
      $default_value = [];
      foreach ($active_items as $active_item) {
        $default_value[] = $entity_storage->load($active_item);
      }
      $selection_settings = [
        'match_operator' => 'CONTAINS',
        'match_limit' => 10,
      ] + $field_definition->getSettings()['handler_settings'];
      $form[$facet->id()]['#default_value'] = $default_value;
      $form[$facet->id()]['entity'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => $field_definition->getSetting('target_type'),
        '#selection_handler' => $field_definition->getSetting('handler'),
        '#selection_settings' => $selection_settings,
      ];
      $form_state->set('multivalue_child', 'entity');

      return $form;
    }

    if (in_array($field_type, ['list_integer', 'list_float', 'list_string'])) {
      $form[$facet->id()]['#default_value'] = $active_items;
      $form[$facet->id()]['list'] = [
        '#type' => 'select',
        '#options' => $field_definition->getSetting('allowed_values'),
        '#empty_option' => $this->t('Select'),
      ];
      $form_state->set('multivalue_child', 'list');

      return $form;
    }

    if ($field_type === 'boolean' && !$facet->getResults()) {
      // Create some dummy results for each boolean type (on/off) then process
      // the results to ensure we have display labels.
      $results = [
        new Result($facet, 1, 1, 1),
        new Result($facet, 0, 0, 1),
      ];

      $facet->setResults($results);
      $results = $this->processFacetResults($facet);
      $options = [];
      array_walk($results, function (ResultInterface &$result) use (&$options) {
        $options[$result->getRawValue()] = $result->getDisplayValue();
      });
      $form[$facet->id()]['#default_value'] = $active_items;
      $form[$facet->id()]['boolean'] = [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('Select'),
      ];
      $form_state->set('multivalue_child', 'boolean');

      return $form;
    }

    return $this->build($facet);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(FacetInterface $facet, ListSourceInterface $list_source = NULL, array $filter_value = []): string {
    $field_definition = $this->getFieldDefinition($facet, $list_source);
    $field_type = !empty($field_definition) ? $field_definition->getType() : NULL;

    if (in_array(EntityReferenceFieldItemListInterface::class, class_implements($field_definition->getClass()))) {
      $entity_storage = $this->entityTypeManager->getStorage($field_definition->getSetting('target_type'));
      $values = [];
      foreach ($filter_value as $value) {
        $entity = $entity_storage->load($value);
        if (!$entity) {
          continue;
        }

        $values[] = $entity->label();
      }

      return implode(', ', $values);
    }

    if (in_array($field_type, ['list_integer', 'list_float', 'list_string'])) {
      return implode(', ', array_map(function ($value) use ($field_definition) {
        return $field_definition->getSetting('allowed_values')[$value];
      }, $filter_value));
    }

    if ($field_type === 'boolean' && !$facet->getResults()) {
      $results = [
        new Result($facet, 1, 1, 1),
        new Result($facet, 0, 0, 1),
      ];

      $facet->setResults($results);
      return parent::getDefaultValuesLabel($facet, $list_source, $filter_value);
    }

    return parent::getDefaultValuesLabel($facet, $list_source, $filter_value);
  }

  /**
   * Gets field definition for the field used in the facet.
   *
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
  public function prepareDefaultFilterValue(FacetInterface $facet, array $form, FormStateInterface $form_state): array {
    $values = [];
    $child = $form_state->get('multivalue_child');

    foreach ($form_state->getValue($facet->id()) as $key => $value) {
      if (is_array($value) && isset($value[$child]) && $value[$child] !== "") {
        $values[] = $value[$child];
      }
    }

    // Reset the element count in the form element state so that if we reload
    // the form in the same form embed, we don't show a different number of
    // input elements.
    $state_parents = array_merge($form['#parents'], [$facet->id()]);
    $element_state = MultiValue::getElementState($state_parents, $facet->id(), $form_state);
    $element_state['items_count'] = count($values);
    MultiValue::setElementState($state_parents, $facet->id(), $form_state, $element_state);

    return $values;
  }

}
