<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\MultiselectFilterField;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oe_list_pages\MultiSelectFilterFieldPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the entity field type multiselect filter plugin.
 *
 * @MultiselectFieldFilter(
 *   id = "entity",
 *   label = @Translation("Entity field"),
 *   field_types = {
 *     "entity_reference",
 *     "entity_reference_revisions",
 *   },
 *   weight = 100
 * )
 */
class EntityField extends MultiSelectFilterFieldPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

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
  public function getDefaultValues(): array {
    $field_definition = $this->configuration['field_definition'];
    if (!$field_definition instanceof FieldDefinitionInterface) {
      return [];
    }
    $entity_storage = $this->entityTypeManager->getStorage($field_definition->getSetting('target_type'));
    $default_value = [];
    foreach ($this->configuration['active_items'] as $active_item) {
      $default_value[] = $entity_storage->load($active_item);
    }
    return $default_value;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(): array {
    $field_definition = $this->configuration['field_definition'];
    if (!$field_definition instanceof FieldDefinitionInterface) {
      return [];
    }
    $selection_settings = [
      'match_operator' => 'CONTAINS',
      'match_limit' => 10,
    ] + $field_definition->getSettings()['handler_settings'];
    return [
      '#type' => 'entity_autocomplete',
      '#maxlength' => 1024,
      '#target_type' => $field_definition->getSetting('target_type'),
      '#selection_handler' => $field_definition->getSetting('handler'),
      '#selection_settings' => $selection_settings,
    ];
  }

}
