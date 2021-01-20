<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\facets\Entity\Facet;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\oe_list_pages\ListPresetFilter;

/**
 * Tests for MultiSelectFieldFilter plugins.
 */
class MultiSelectFieldFilterPluginTest extends EntityKernelTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The multiselect filter field plugin manager.
   *
   * @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginManager
   */
  protected $pluginManager;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'facets',
    'link',
    'options',
    'oe_list_pages',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setup() {
    parent::setUp();
    $this->pluginManager = \Drupal::service('plugin.manager.multiselect_filter_field');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
  }

  /**
   * Assert return values when plugin is not configured.
   */
  public function testEmptyPlugins(): void {
    foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
      /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
      $plugin = $this->pluginManager->createInstance($id, []);
      $this->assertEmpty($plugin->getDefaultValues(), 'Plugin returned default values even though no active items where configured');
      $this->assertEmpty($plugin->buildDefaultValueForm(), 'Plugin returned a form even though no field definition was configured.');
    }
  }

  /**
   * Tests the list fields plugin.
   */
  public function testListFieldPlugin(): void {
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'entity_type' => 'entity_test',
      'field_name' => 'list_field',
      'type' => 'list_integer',
      'settings' => [
        'allowed_values' => [
          0 => 'Zero',
          1 => 'One',
        ],
      ],
    ])->save();

    $field_config = $this->entityTypeManager->getStorage('field_config')->create([
      'label' => 'List field',
      'required' => FALSE,
      'field_name' => 'list_field',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ]);

    $plugin_id = $this->pluginManager->getPluginIdByFieldType($field_config->getType());
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->pluginManager->createInstance($plugin_id, [
      'field_definition' => $field_config,
      'active_items' => [1],
    ]);

    $expected_default_values = [1];
    $expected_form = [
      '#type' => 'select',
      '#options' => [
        0 => 'Zero',
        1 => 'One',
      ],
      '#empty_option' => 'Select',
    ];
    $this->assertEquals($expected_default_values, $plugin->getDefaultValues());
    $this->assertEquals($expected_form, $plugin->buildDefaultValueForm());

    $filter = new ListPresetFilter('facetid', [0]);
    $this->assertEquals('Zero', $plugin->getDefaultValuesLabel($filter));
    $filter = new ListPresetFilter('facetid', [0, 1]);
    $this->assertEquals('Zero, One', $plugin->getDefaultValuesLabel($filter));
  }

  /**
   * Tests the entity fields plugin.
   */
  public function testEntityFieldPlugin(): void {
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'entity_type' => 'entity_test',
      'field_name' => 'entity_field',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'entity_test',
      ],
    ])->save();

    $field_config = $this->entityTypeManager->getStorage('field_config')->create([
      'label' => 'Entity field',
      'required' => FALSE,
      'field_name' => 'entity_field',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'entity_test' => 'entity_test',
          ],
        ],
      ],
    ]);
    $entity = $this->entityTypeManager->getStorage('entity_test')->create([
      'name' => 'test_entity',
    ]);
    $entity->save();
    $entity = $this->entityTypeManager->getStorage('entity_test')->load($entity->id());

    $plugin_id = $this->pluginManager->getPluginIdByFieldType($field_config->getType());
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->pluginManager->createInstance($plugin_id, [
      'field_definition' => $field_config,
      'active_items' => [$entity->id()],
    ]);

    $expected_values = [$entity];
    $expected_form = [
      '#type' => 'entity_autocomplete',
      '#maxlength' => 1024,
      '#target_type' => 'entity_test',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'match_operator' => 'CONTAINS',
        'match_limit' => 10,
        'target_bundles' => [
          'entity_test' => 'entity_test',
        ],
      ],
    ];;
    $this->assertEquals($expected_values, $plugin->getDefaultValues());
    $this->assertEquals($expected_form, $plugin->buildDefaultValueForm());

    $filter = new ListPresetFilter('facetid', [$entity->id()]);
    $this->assertEquals('test_entity', $plugin->getDefaultValuesLabel($filter));
  }

  /**
   * Tests the link fields plugin.
   */
  public function testLinkFieldPlugin(): void {
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'field_name' => 'link_field',
      'entity_type' => 'entity_test',
      'type' => 'link',
    ])->save();

    $field_config = $this->entityTypeManager->getStorage('field_config')->create([
      'bundle' => 'entity_test',
      'field_name' => 'link_field',
      'entity_type' => 'entity_test',
      'label' => 'Read more about this entity',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_GENERIC,
      ],
    ]);
    $entity = $this->entityTypeManager->getStorage('entity_test')->create([
      'name' => 'test_entity label',
    ]);
    $entity->save();

    $plugin_id = $this->pluginManager->getPluginIdByFieldType($field_config->getType());
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->pluginManager->createInstance($plugin_id, [
      'field_definition' => $field_config,
      'active_items' => ['route:custom-route'],
    ]);

    $expected_values = ['custom-route'];
    $expected_form = [
      '#type' => 'url',
      '#element_validate' => [[LinkWidget::class, 'validateUriElement']],
      '#maxlength' => 2048,
      '#link_type' => LinkItemInterface::LINK_GENERIC,
    ];
    $this->assertEquals($expected_values, $plugin->getDefaultValues());
    $this->assertEquals($expected_form, $plugin->buildDefaultValueForm());

    $filter = new ListPresetFilter('facetid', ['route:custom-route']);
    $this->assertEquals('custom-route', $plugin->getDefaultValuesLabel($filter));
  }

  /**
   * Tests the link fields plugin.
   */
  public function testBooleanFieldPlugin(): void {
    $facet = new Facet([], 'facets_facet');
    $facet->setWidget('oe_list_pages_multiselect');

    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'entity_type' => 'entity_test',
      'field_name' => 'boolean_field',
      'type' => 'boolean',
    ])->save();

    $field_config = $this->entityTypeManager->getStorage('field_config')->create([
      'label' => 'Boolean field',
      'required' => FALSE,
      'field_name' => 'boolean_field',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ]);

    $plugin_id = $this->pluginManager->getPluginIdByFieldType($field_config->getType());
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->pluginManager->createInstance($plugin_id, [
      'field_definition' => $field_config,
      'active_items' => [1],
      'facet' => $facet,
    ]);

    $expected_values = [1];
    $expected_form = [
      '#type' => 'select',
      '#options' => [0 => 0, 1 => 1],
      '#empty_option' => 'Select',
    ];
    $this->assertEquals($expected_values, $plugin->getDefaultValues());
    $this->assertEquals($expected_form, $plugin->buildDefaultValueForm());

    $filter = new ListPresetFilter('facetid', [0]);
    $this->assertEquals('0', $plugin->getDefaultValuesLabel($filter));
    $filter = new ListPresetFilter('facetid', [1]);
    $this->assertEquals('1', $plugin->getDefaultValuesLabel($filter));
  }

}
