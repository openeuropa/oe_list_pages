<?php

namespace Drupal\oe_list_pages\Plugin\MultiselectFilterField;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\oe_list_pages\MultiSelectFilterFieldPluginBase;

/**
 * Defines the list field type multiselect filter plugin.
 *
 * @PageHeaderMetadata(
 *   id = "list",
 *   label = @Translation("List field"),
 *   weight = 100
 * )
 */
class ListField extends MultiSelectFilterFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function applies(): bool {
    $field_definition = $this->configuration['field_definition'];
    if (!$field_definition instanceof FieldDefinitionInterface) {
      return FALSE;
    }
    $supported_types = ['list_integer', 'list_float', 'list_string'];
    if (in_array($field_definition->getType(), $supported_types)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(): array {
    $field_definition = $this->configuration['field_definition'];
    if (!$field_definition instanceof FieldDefinitionInterface) {
      return [];
    }

    return [
      '#type' => 'select',
      '#options' => $field_definition->getSetting('allowed_values'),
      '#empty_option' => $this->t('Select'),
    ];
  }

}
