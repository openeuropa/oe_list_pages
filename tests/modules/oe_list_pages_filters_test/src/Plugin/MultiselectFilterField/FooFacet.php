<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_filters_test\Plugin\MultiselectFilterField;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_list_pages\MultiSelectFilterFieldPluginBase;

/**
 * Test plugin for the oe_list_pages_filters_test_test_field facet.
 *
 * @MultiselectFilterField(
 *   id = "foo",
 *   label = @Translation("A filter field plugin for the oe_list_pages_filters_test_test_field facet"),
 *   facet_ids = {
 *     "oe_list_pages_filters_test_test_field",
 *   },
 *   weight = 100
 * )
 */
class FooFacet extends MultiSelectFilterFieldPluginBase {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(): array {
    return [
      '#type' => 'select',
      '#options' => [
        1 => $this->t('One'),
        2 => $this->t('Two'),
        3 => $this->t('There'),
      ],
      '#empty_option' => $this->t('Select'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(): string {
    $preset_filter = $this->configuration['preset_filter'];
    $map = [
      1 => $this->t('One'),
      2 => $this->t('Two'),
      3 => $this->t('There'),
    ];

    $labels = [];
    foreach ($preset_filter->getValues() as $value) {
      $labels[] = $map[$value] ?? $value;
    }

    return implode(', ', $labels);
  }

}
