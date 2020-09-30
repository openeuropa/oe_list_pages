<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(FacetInterface $facet, ListSourceInterface $list_source = NULL, array $filter_value = []): string {
    $field_id = $facet->getFieldIdentifier();
    $field_config = $this->entityTypeManager->getStorage('field_config')->load($list_source->getEntityType() . '.' . $list_source->getBundle() . '.' . $field_id);
    $field_type = !empty($field_config) ? $field_config->getType() : NULL;
    $entity_storage = $this->entityTypeManager->getStorage($list_source->getEntityType());
    if ($field_type === 'entity_reference') {
      $referenced_entities = $entity_storage->loadMultiple($filter_value);
      return implode(', ', array_map(function ($referenced_entity) {
        return $referenced_entity->label();
      }, $referenced_entities));
    }
    elseif (in_array($field_type, ['list_integer', 'list_float', 'list_string'])) {
      return implode(', ', array_map(function ($value) use ($field_config) {
        return $field_config->getSetting('allowed_values')[$value];
      }, $filter_value));
    }
    else {
      return parent::getDefaultValuesLabel($facet, $list_source, $filter_value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValuesWidget(FacetInterface $facet, ListSourceInterface $list_source = NULL, array $parents = []): ?array {
    $field_id = $facet->getFieldIdentifier();
    $field_config = $this->entityTypeManager->getStorage('field_config')->load($list_source->getEntityType() . '.' . $list_source->getBundle() . '.' . $field_id);
    $field_type = !empty($field_config) ? $field_config->getType() : NULL;
    $entity_storage = $this->entityTypeManager->getStorage($list_source->getEntityType());
    if ($field_type === 'entity_reference') {
      $form[$facet->id()] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => $field_config->getTargetEntityTypeId(),
        '#default_value' => !empty($facet->getActiveItems()) ? $entity_storage->load($facet->getActiveItems()[0]) : NULL,
        '#selection_settings' => [
          'target_bundles' => $field_config->getSettings()['handler_settings']['target_bundles'],
        ],
      ];

      return $form;
    }
    elseif (in_array($field_type, ['list_integer', 'list_float', 'list_string'])) {
      $form[$facet->id()] = [
        '#type' => 'select',
        '#default_value' => $facet->getActiveItems()[0],
        '#multiple' => TRUE,
        '#options' => $field_config->getSetting('allowed_values'),
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

}
