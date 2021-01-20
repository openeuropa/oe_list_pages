<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\MultiselectFilterField;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_list_pages\MultiSelectFilterFieldPluginBase;

/**
 * Defines the list field type multiselect filter plugin.
 *
 * @MultiselectFieldFilter(
 *   id = "list",
 *   label = @Translation("List field"),
 *   field_types = {
 *     "list_integer",
 *     "list_float",
 *     "list_string",
 *   },
 *   weight = 100
 * )
 */
class ListField extends MultiSelectFilterFieldPluginBase {

  use StringTranslationTrait;

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
