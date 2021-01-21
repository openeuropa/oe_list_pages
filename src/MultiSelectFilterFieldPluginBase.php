<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use DeepCopy\Exception\PropertyException;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base implementation of a multiselect filter field plugin.
 */
abstract class MultiSelectFilterFieldPluginBase extends PluginBase implements ConfigurableInterface, MultiselectFilterFieldPluginInterface, ContainerFactoryPluginInterface {

  use FacetManipulationTrait;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager) {
    if (!isset($configuration['facet']) || !$configuration['facet'] instanceof FacetInterface) {
      throw new PropertyException('The plugin requires a facet object.');
    }
    if (!isset($configuration['list_source']) || !$configuration['list_source'] instanceof ListSourceInterface) {
      throw new PropertyException('The plugin requires a list source object.');
    }
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'facet' => NULL,
      'list_source' => NULL,
      'preset_filter' => NULL,
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
    if (!isset($this->configuration['preset_filter']) || !$this->configuration['preset_filter'] instanceof ListPresetFilter) {
      return [];
    }
    return $this->configuration['preset_filter']->getValues();
  }

}
