<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\facets\FacetInterface;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\search_api\Item\Field;

/**
 * Tests for MultiSelectFilterField plugins.
 */
class MultiSelectFilterFieldPluginTest extends ListsSourceTestBase {

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
   * The test facet using the multiselect widget.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected $facet;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'options',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->pluginManager = \Drupal::service('plugin.manager.multiselect_filter_field');
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
  }

  /**
   * Tests the list fields plugin.
   */
  public function testListFieldPlugin(): void {
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'entity_type' => 'entity_test_mulrev_changed',
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
      'entity_type' => 'entity_test_mulrev_changed',
      'bundle' => 'item',
    ]);
    $field_config->save();

    $this->addFieldToIndex('list_field', 'List field', 'string');
    $facet = $this->createFieldFacet('list_field');

    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $plugin_id = $this->pluginManager->getPluginIdByFieldType($field_config->getType());
    $this->assertEquals($this->pluginManager->getPluginIdForFacet($facet, $item_list), $plugin_id);
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->pluginManager->createInstance($plugin_id, [
      'facet' => $facet,
      'preset_filter' => new ListPresetFilter($facet->id(), [1]),
      'list_source' => $item_list,
    ]);

    $expected_form = [
      '#type' => 'select',
      '#options' => [
        0 => 'Zero',
        1 => 'One',
      ],
      '#empty_option' => t('Select'),
      '#required_error' => t('Facet for list_field field is required.'),
    ];

    $this->assertEquals([1], $plugin->getDefaultValues());
    $this->assertEquals($expected_form, $plugin->buildDefaultValueForm());

    $this->assertEquals('One', $plugin->getDefaultValuesLabel());
    $plugin->setConfiguration([
      'facet' => $facet,
      'preset_filter' => new ListPresetFilter($facet->id(), [0, 1]),
      'list_source' => $item_list,
    ]);
    $this->assertEquals('Zero, One', $plugin->getDefaultValuesLabel());
  }

  /**
   * Tests the entity reference fields plugin.
   */
  public function testEntityReferenceFieldPlugin(): void {
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'entity_type' => 'entity_test_mulrev_changed',
      'field_name' => 'entity_reference_field',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'entity_test_mulrev_changed',
      ],
    ])->save();

    $field_config = $this->entityTypeManager->getStorage('field_config')->create([
      'label' => 'Entity reference field',
      'required' => FALSE,
      'field_name' => 'entity_reference_field',
      'entity_type' => 'entity_test_mulrev_changed',
      'bundle' => 'item',
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            'item' => 'item',
          ],
        ],
      ],
    ]);
    $field_config->save();
    $entity = $this->entityTypeManager->getStorage('entity_test_mulrev_changed')->create([
      'name' => 'test_entity',
      'type' => 'item',
    ]);
    $entity->save();
    // We need to load the entity so we can fully compare the objects below.
    $entity = $this->entityTypeManager->getStorage('entity_test_mulrev_changed')->load($entity->id());

    $this->addFieldToIndex('entity_reference_field', 'Entity reference field', 'string');
    $facet = $this->createFieldFacet('entity_reference_field');

    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $plugin_id = $this->pluginManager->getPluginIdByFieldType($field_config->getType());
    $this->assertEquals($this->pluginManager->getPluginIdForFacet($facet, $item_list), $plugin_id);
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->pluginManager->createInstance($plugin_id, [
      'facet' => $facet,
      'preset_filter' => new ListPresetFilter($facet->id(), [$entity->id()]),
      'list_source' => $item_list,
    ]);

    $expected_form = [
      '#type' => 'entity_autocomplete',
      '#maxlength' => 1024,
      '#target_type' => 'entity_test_mulrev_changed',
      '#selection_handler' => 'default:entity_test_mulrev_changed',
      '#selection_settings' => [
        'match_operator' => 'CONTAINS',
        'match_limit' => 10,
        'target_bundles' => [
          'item' => 'item',
        ],
      ],
      '#required_error' => t('Facet for entity_reference_field field is required.'),
    ];
    $this->assertEquals([$entity], $plugin->getDefaultValues());
    $this->assertEquals($expected_form, $plugin->buildDefaultValueForm());
    $this->assertEquals('test_entity', $plugin->getDefaultValuesLabel());
  }

  /**
   * Tests the link fields plugin.
   */
  public function testLinkFieldPlugin(): void {
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'field_name' => 'link_field',
      'entity_type' => 'entity_test_mulrev_changed',
      'type' => 'link',
    ])->save();

    /** @var \Drupal\Core\Field\FieldConfigInterface $field_config */
    $field_config = $this->entityTypeManager->getStorage('field_config')->create([
      'bundle' => 'item',
      'field_name' => 'link_field',
      'entity_type' => 'entity_test_mulrev_changed',
      'label' => 'Read more about this entity',
      'settings' => [
        'title' => DRUPAL_OPTIONAL,
        'link_type' => LinkItemInterface::LINK_EXTERNAL,
      ],
    ]);
    $field_config->save();

    $this->addFieldToIndex('link_field', 'Link field', 'string');
    $facet = $this->createFieldFacet('link_field');

    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $plugin_id = $this->pluginManager->getPluginIdByFieldType($field_config->getType());
    $this->assertEquals($this->pluginManager->getPluginIdForFacet($facet, $item_list), $plugin_id);
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->pluginManager->createInstance($plugin_id, [
      'facet' => $facet,
      'preset_filter' => new ListPresetFilter($facet->id(), ['route:custom-route']),
      'list_source' => $item_list,
    ]);

    $expected_form = [
      '#type' => 'url',
      '#element_validate' => [[LinkWidget::class, 'validateUriElement']],
      '#maxlength' => 2048,
      '#link_type' => LinkItemInterface::LINK_EXTERNAL,
      '#required_error' => t('Facet for link_field field is required.'),
    ];
    $this->assertEquals(['custom-route'], $plugin->getDefaultValues());
    $this->assertEquals($expected_form, $plugin->buildDefaultValueForm());
    $this->assertEquals('custom-route', $plugin->getDefaultValuesLabel());
    $plugin->setConfiguration([
      'facet' => $facet,
      'preset_filter' => new ListPresetFilter($facet->id(), ['internal:/path']),
      'list_source' => $item_list,
    ]);
    $this->assertEquals('/path', $plugin->getDefaultValuesLabel());
    $this->entityTypeManager->getStorage('node_type')->create([
      'type' => 'test_bundle',
      'name' => 'Test bundle',
    ])->save();
    /** @var \Drupal\emr\EntityMetaRelationInstaller $installer */
    $installer = \Drupal::service('emr.installer');
    $installer->installEntityMetaTypeOnContentEntityType('oe_list_page', 'node', [
      'test_bundle',
    ]);
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'test_bundle',
      'title' => 'Test node',
      'published' => 1,
    ]);
    $node->save();
    $user = $this->drupalCreateUser(['access content']);
    $this->drupalSetCurrentUser($user);
    $plugin->setConfiguration([
      'facet' => $facet,
      'preset_filter' => new ListPresetFilter($facet->id(), ['entity:node/1']),
      'list_source' => $item_list,
    ]);
    $this->assertEquals('Test node (1)', $plugin->getDefaultValuesLabel());

    // Change the link field type to be generic or internal and assert the
    // form element changes.
    $field_config->setSetting('link_type', LinkItemInterface::LINK_INTERNAL);
    $field_config->save();
    $expected_form['#type'] = 'entity_autocomplete';
    $expected_form['#target_type'] = 'node';
    $expected_form['#process_default_value'] = FALSE;
    $expected_form['#link_type'] = LinkItemInterface::LINK_INTERNAL;
    $this->assertEquals($expected_form, $plugin->buildDefaultValueForm());
    $field_config->setSetting('link_type', LinkItemInterface::LINK_GENERIC);
    $field_config->save();
    $expected_form['#link_type'] = LinkItemInterface::LINK_GENERIC;
    $this->assertEquals($expected_form, $plugin->buildDefaultValueForm());
  }

  /**
   * Tests the boolean fields plugin.
   */
  public function testBooleanFieldPlugin(): void {
    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'entity_type' => 'entity_test_mulrev_changed',
      'field_name' => 'boolean_field',
      'type' => 'boolean',
    ])->save();

    $field_config = $this->entityTypeManager->getStorage('field_config')->create([
      'label' => 'Boolean field',
      'required' => FALSE,
      'field_name' => 'boolean_field',
      'entity_type' => 'entity_test_mulrev_changed',
      'bundle' => 'item',
    ]);
    $field_config->save();

    $this->addFieldToIndex('boolean_field', 'Boolean field', 'boolean');
    $facet = $this->createFieldFacet('boolean_field');

    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $plugin_id = $this->pluginManager->getPluginIdByFieldType($field_config->getType());
    $this->assertEquals($this->pluginManager->getPluginIdForFacet($facet, $item_list), $plugin_id);
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->pluginManager->createInstance($plugin_id, [
      'facet' => $facet,
      'preset_filter' => new ListPresetFilter($facet->id(), ['0']),
      'list_source' => $item_list,
    ]);

    $expected_form = [
      '#type' => 'select',
      '#options' => [0 => 0, 1 => 1],
      '#empty_option' => 'Select',
    ];
    $this->assertEquals([0], $plugin->getDefaultValues());
    $this->assertEquals($expected_form, $plugin->buildDefaultValueForm());
    $this->assertEquals('0', $plugin->getDefaultValuesLabel());
    $plugin->setConfiguration([
      'facet' => $facet,
      'preset_filter' => new ListPresetFilter($facet->id(), ['1']),
      'list_source' => $item_list,
    ]);
    $this->assertEquals('1', $plugin->getDefaultValuesLabel());
  }

  /**
   * Adds a field to the entity test index.
   *
   * @param string $id
   *   The id for the field.
   * @param string $label
   *   The label for the field.
   * @param string $type
   *   The type for the field.
   */
  protected function addFieldToIndex(string $id, string $label, string $type): void {
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $index = $item_list->getIndex();
    $field = new Field($index, $id);
    $field->setType($type);
    $field->setPropertyPath($id);
    $field->setLabel($label);
    $field->setDatasourceId('entity:entity_test_mulrev_changed');
    $index->addField($field);
    $index->save();
  }

  /**
   * Creates a facet for the given entity test field.
   *
   * @param string $field_id
   *   The id of the field.
   *
   * @return \Drupal\facets\FacetInterface
   *   The created facet.
   */
  protected function createFieldFacet(string $field_id): FacetInterface {
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $facet = $this->createFacet($field_id, $default_list_id);
    $facet->setWidget('oe_list_pages_multiselect');
    $facet->save();
    return $facet;
  }

}
