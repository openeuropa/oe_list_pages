<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Plugin\MultiselectFilterField;

use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\MultiSelectFilterFieldPluginBase;

/**
 * Defines the string field type multiselect filter plugin.
 *
 * This is used as a default for string fields which don't have anything
 * more specific.
 *
 * @MultiselectFilterField(
 *   id = "string",
 *   label = @Translation("Default string field"),
 *   field_types = {
 *     "string",
 *   },
 *   data_types = {
 *     "string",
 *   },
 *   weight = 100
 * )
 */
class StringField extends MultiSelectFilterFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(array &$form = [], FormStateInterface $form_state = NULL, ListPresetFilter $preset_filter = NULL): array {
    return [
      '#type' => 'textfield',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(): string {
    $facet = $this->configuration['facet'];
    $preset_filter = $this->configuration['preset_filter'];
    return $this->getDefaultFilterValuesLabel($facet, $preset_filter);
  }

}
