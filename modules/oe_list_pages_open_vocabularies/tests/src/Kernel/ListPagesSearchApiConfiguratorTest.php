<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages_open_vocabularies\Kernel;

use Drupal\facets\Entity\Facet;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\open_vocabularies\Entity\OpenVocabulary;
use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;
use Drupal\search_api\Entity\Index;

/**
 * Tests the list page open vocabularies configurator.
 */
class ListPagesSearchApiConfiguratorTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'field',
    'user',
    'datetime',
    'datetime_range',
    'emr',
    'entity_reference_revisions',
    'emr_node',
    'link',
    'node',
    'oe_list_pages',
    'oe_list_pages_filters_test',
    'oe_list_page_content_type',
    'oe_list_pages_open_vocabularies',
    'oe_list_pages_open_vocabularies_test',
    'open_vocabularies',
    'open_vocabularies_test',
    'options',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'taxonomy',
    'text',
    'facets',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('search_api_task');
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('facets_facet');
    $this->installEntitySchema('node');
    $this->installEntitySchema('open_vocabulary');
    $this->installEntitySchema('open_vocabulary_association');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('entity_meta');
    $this->installEntitySchema('entity_meta_relation');
    $this->installConfig(['node',
      'open_vocabularies',
      'oe_list_pages',
      'oe_list_page_content_type',
      'oe_list_pages_filters_test',
      'oe_list_pages_open_vocabularies_test',
      'emr',
      'emr_node',
    ]);

    // Create OpenVocabularies vocabularies.
    $values = [
      'id' => 'custom_vocabulary',
      'label' => $this->randomString(),
      'description' => $this->randomString(128),
      'handler' => 'test_entity_plugin',
      'handler_settings' => [
        'target_bundles' => [
          'entity_test' => 'entity_test',
        ],
      ],
    ];

    /** @var \Drupal\open_vocabularies\OpenVocabularyInterface $vocabulary */
    $vocabulary = OpenVocabulary::create($values);
    $vocabulary->save();
  }

  /**
   * Tests SearchApiConfigurator reaction to vocabulary associations changes.
   */
  public function testVocabularyAssociationChanges(): void {
    $id_1 = 'open_vocabularies_custom_vocabulary_open_vocabulary_node_content_type_one_field_open_vocabularies';
    $id_2 = 'open_vocabularies_custom_vocabulary_open_vocabulary_node_content_type_two_field_open_vocabularies';

    /** @var \Drupal\search_api\Entity\Index $node_index */
    $node_index = Index::load('node');
    // Check facet do not exist.
    $facet_1 = Facet::load($id_1);
    $this->assertNull($facet_1);
    $facet_2 = Facet::load($id_2);
    $this->assertNull($facet_2);
    // Check field does not exist.
    $this->assertEmpty($node_index->getField($id_1));
    $this->assertEmpty($node_index->getField($id_2));

    // Create association for content types.
    $fields = [
      'node.content_type_one.field_open_vocabularies',
    ];
    $values = [
      'label' => $this->randomString(),
      'name' => 'open_vocabulary',
      'widget_type' => 'options_select',
      'required' => TRUE,
      'help_text' => 'Some text',
      'predicate' => 'http://example.com/#name',
      'cardinality' => 5,
      'vocabulary' => 'custom_vocabulary',
      'fields' => $fields,
    ];
    $this->container->get('kernel')->rebuildContainer();
    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = OpenVocabularyAssociation::create($values);
    $association_id = $association->id();
    $association->save();

    // Check facet exists.
    /** @var \Drupal\facets\FacetInterface $facet */
    $facet_1 = Facet::load($id_1);
    $this->assertEquals('list_facet_source:node:content_type_one', $facet_1->getFacetSourceId());
    // Check values were correctly saved.
    $this->assertEquals($values['label'], $facet_1->label());
    $this->assertEquals($id_1, $facet_1->getFieldIdentifier());
    $facet_2 = Facet::load($id_2);
    $this->assertNull($facet_2);
    // Check fields exists.
    $node_index = Index::load('node');
    $field_1 = $node_index->getField($id_1);
    $this->assertEquals($id_1, $field_1->getFieldIdentifier());
    $field_2 = $node_index->getField($id_2);
    $this->assertNull($field_2);

    // Alter the association, change label and add to a new field.
    $association = OpenVocabularyAssociation::load($association_id);
    $fields = [
      'node.content_type_one.field_open_vocabularies',
      'node.content_type_two.field_open_vocabularies',
    ];
    $association->set('fields', $fields);
    $association->set('label', 'New label');
    $association->save();

    // Check facet got changed.
    $this->container->get('kernel')->rebuildContainer();
    $facet_1 = Facet::load($id_1);
    $this->assertEquals('New label', $facet_1->label());
    $this->assertEquals($id_1, $facet_1->getFieldIdentifier());
    $facet_2 = Facet::load($id_2);
    $this->assertEquals('New label', $facet_2->label());
    $this->assertEquals($id_2, $facet_2->getFieldIdentifier());

    // Check field exists.
    $node_index = Index::load('node');
    $field_1 = $node_index->getField($id_1);
    $this->assertEquals($id_1, $field_1->getFieldIdentifier());
    $field_2 = $node_index->getField($id_2);
    $this->assertEquals($id_2, $field_2->getFieldIdentifier());

    // Alter the association to remove initial field.
    $association = OpenVocabularyAssociation::load($association_id);
    $fields = [
      'node.content_type_two.field_open_vocabularies',
    ];
    $association->set('fields', $fields);
    $association->save();

    // Check facet got changed.
    $this->container->get('kernel')->rebuildContainer();
    $facet_1 = Facet::load($id_1);
    $this->assertNull($facet_1);
    $facet_2 = Facet::load($id_2);
    $this->assertEquals('New label', $facet_2->label());
    $this->assertEquals($id_2, $facet_2->getFieldIdentifier());

    // Check field exists.
    $node_index = Index::load('node');
    $field_1 = $node_index->getField($id_1);
    $this->assertNull($field_1);
    $field_2 = $node_index->getField($id_2);
    $this->assertEquals($id_2, $field_2->getFieldIdentifier());

    // Delete association.
    $association->delete();
    $this->container->get('kernel')->rebuildContainer();
    // No more facets.
    $facet_1 = Facet::load($id_1);
    $this->assertNull($facet_1);
    $facet_2 = Facet::load($id_2);
    $this->assertNull($facet_2);
    // No more fields.
    $node_index = Index::load('node');
    $field_1 = $node_index->getField($id_1);
    $this->assertNull($field_1);
    $field_2 = $node_index->getField($id_2);
    $this->assertNull($field_2);
  }

}
