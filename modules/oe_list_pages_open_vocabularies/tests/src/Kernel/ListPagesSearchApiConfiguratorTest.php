<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages_open_vocabularies\Kernel;

use Drupal\facets\Entity\Facet;
use Drupal\oe_list_pages_open_vocabularies\SearchApiConfigurator;
use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;
use Drupal\search_api\Entity\Index;

/**
 * Tests the list page open vocabularies configurator.
 */
class ListPagesSearchApiConfiguratorTest extends ListPagesSearchApiConfiguratorTestBase {

  /**
   * Tests SearchApiConfigurator reaction to vocabulary associations changes.
   */
  public function testVocabularyAssociationChanges(): void {
    $id_1 = 'open_vocabularies_custom_vocabulary_open_vocabulary_node_content_type_one_field_open_vocabularies';
    $id_2 = 'open_vocabularies_custom_vocabulary_open_vocabulary_node_content_type_two_field_open_vocabularies';
    $field_id = 'open_vocabulary_bf49aa6d04';
    /** @var \Drupal\search_api\Entity\Index $node_index */
    $node_index = Index::load('node');
    // Check facet does not exist.
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
    $this->assertEquals($field_id, $facet_1->getFieldIdentifier());
    $this->assertEquals($facet_1->getWeight(), SearchApiConfigurator::MAX_WEIGHT);
    // Assert overriding of empty_behavior value by
    // \Drupal\oe_list_pages_open_vocabularies_test\EventSubscriber\SearchApiFacetTestSubscriber.
    $this->assertEquals([
      'behavior' => 'text',
      'text' => 'No results found for this block!',
      'text_format' => 'plain_text',
    ], $facet_1->getEmptyBehavior());
    $facet_2 = Facet::load($id_2);
    $this->assertNull($facet_2);
    // Check fields exists.
    $node_index = Index::load('node');
    $field_1 = $node_index->getField($field_id);
    $this->assertEquals($field_id, $field_1->getFieldIdentifier());
    $this->assertEquals($field_id, $field_1->getPropertyPath());
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
    /** @var \Drupal\facets\FacetInterface $facet_1 */
    $facet_1 = Facet::load($id_1);
    $this->assertEquals('New label', $facet_1->label());
    $this->assertEquals($field_id, $facet_1->getFieldIdentifier());
    $this->assertEquals($facet_1->getWeight(), SearchApiConfigurator::MAX_WEIGHT);
    // Assert overriding of empty_behavior value by
    // \Drupal\oe_list_pages_open_vocabularies_test\EventSubscriber\SearchApiFacetTestSubscriber.
    $this->assertEquals([
      'behavior' => 'text',
      'text' => 'No results found for this block!',
      'text_format' => 'plain_text',
    ], $facet_1->getEmptyBehavior());
    $this->assertArrayHasKey('url_processor_handler', $facet_1->getProcessors());
    $this->assertArrayHasKey('display_value_widget_order', $facet_1->getProcessors());
    $this->assertArrayHasKey('translate_entity', $facet_1->getProcessors());
    $facet_2 = Facet::load($id_2);
    $this->assertEquals('New label', $facet_2->label());
    $this->assertEquals($field_id, $facet_2->getFieldIdentifier());
    $this->assertEquals($facet_2->getWeight(), SearchApiConfigurator::MAX_WEIGHT);
    // Assert overriding of empty_behavior value by
    // \Drupal\oe_list_pages_open_vocabularies_test\EventSubscriber\SearchApiFacetTestSubscriber.
    $this->assertEquals([
      'behavior' => 'text',
      'text' => 'No results found for this block!',
      'text_format' => 'plain_text',
    ], $facet_2->getEmptyBehavior());
    // Check field exists.
    $node_index = Index::load('node');
    $field_1 = $node_index->getField($field_id);
    $this->assertEquals($field_id, $field_1->getFieldIdentifier());

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
    $this->assertEquals($facet_2->getWeight(), SearchApiConfigurator::MAX_WEIGHT);
    $this->assertEquals($field_id, $facet_2->getFieldIdentifier());

    // Check field exists.
    $node_index = Index::load('node');
    $field_1 = $node_index->getField($field_id);
    $this->assertEquals($field_id, $field_1->getFieldIdentifier());
    $this->assertEquals($field_id, $field_1->getPropertyPath());

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
    $field_1 = $node_index->getField($field_id);
    $this->assertNull($field_1);
  }

}
