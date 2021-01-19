<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;

/**
 * Base implementation of a multiselect filter field plugin.
 */
abstract class MultiSelectFilterFieldPluginBase extends PluginBase implements ConfigurableInterface, MultiselectFilterFieldPluginInterface {

  use FacetManipulationTrait;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($this->configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'facet' => NULL,
      'active_items' => [],
      'field_definition' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // Merge in defaults.
    $this->configuration = NestedArray::mergeDeep(
      $this->defaultConfiguration(),
      $configuration
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValues(): array {
    return $this->configuration['active_items'];
  }

}
