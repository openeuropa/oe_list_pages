<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\MultiselectFilterField;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\MultiSelectFilterFieldPluginBase;

/**
 * Defines the list field type multiselect filter plugin.
 *
 * @MultiselectFilterField(
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
  public function buildDefaultValueForm(array &$form = [], FormStateInterface $form_state = NULL, ListPresetFilter $preset_filter = NULL): array {
    $field_definition = $this->getFacetFieldDefinition($this->configuration['facet'], $this->configuration['list_source']);
    if (empty($field_definition)) {
      return [];
    }

    return [
      '#type' => 'select',
      '#required_error' => t('@facet field is required.', ['@facet' => $this->configuration['facet']->label()]),
      '#options' => $field_definition->getSetting('allowed_values'),
      '#empty_option' => $this->t('Select'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(): string {
    $field_definition = $this->getFacetFieldDefinition($this->configuration['facet'], $this->configuration['list_source']);
    if (empty($field_definition)) {
      return '';
    }

    $filter_value = array_filter(parent::getDefaultValues());
    return implode(', ', array_map(function ($value) use ($field_definition) {
      return $field_definition->getSetting('allowed_values')[$value];
    }, $filter_value));
  }

}
