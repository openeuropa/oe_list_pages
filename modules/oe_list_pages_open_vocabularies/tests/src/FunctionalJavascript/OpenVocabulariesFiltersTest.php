<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages_open_vocabularies\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\oe_list_pages\FilterConfigurationFormBuilderBase;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;
use Drupal\Tests\oe_list_pages\FunctionalJavascript\ListPagePluginFormTestBase;
use Drupal\Tests\oe_list_pages_open_vocabularies\Traits\OpenVocabularyTestTrait;

/**
 * Tests the List pages open vocabularies filters.
 *
 * @group oe_list_pages
 */
class OpenVocabulariesFiltersTest extends ListPagePluginFormTestBase {

  use LinkListTestTrait;
  use OpenVocabularyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'options',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages',
    'oe_list_page_content_type',
    'oe_list_pages_filters_test',
    'oe_list_pages_open_vocabularies_test',
    'oe_list_pages_link_list_source',
    'open_vocabularies',
    'node',
    'emr',
    'emr_node',
    'rdf_skos',
    'search_api',
    'search_api_db',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The first association to test.
   *
   * @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface
   */
  protected $firstAssociation;

  /**
   * The second association to test.
   *
   * @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface
   */
  protected $secondAssociation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $terms = $this->createTestTaxonomyVocabularyAndTerms([
      'yellow color',
      'green color',
    ]);

    // Create association for content types.
    $fields = [
      'node.content_type_one.field_open_vocabularies',
      'node.content_type_two.field_open_vocabularies',
    ];
    $this->firstAssociation = $this->createTagsVocabularyAssociation($fields);
    $this->secondAssociation = $this->createTagsVocabularyAssociation($fields, 'oe_list_pages_ov_tags', '_two');

    // Create some test nodes for content type one.
    $date = new DrupalDateTime('09-02-2021');
    $values = [
      'title' => 'one yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a banana',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'field_open_vocabularies' => [[
        'target_id' => $terms[0]->id(),
        'target_association_id' => $this->firstAssociation->id(),
      ], [
        'target_id' => $terms[1]->id(),
        'target_association_id' => $this->secondAssociation->id(),
      ],
      ],
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    $values = [
      'title' => 'a green fruit',
      'type' => 'content_type_one',
      'body' => 'this is an avocado',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test2',
      'field_open_vocabularies' => [[
        'target_id' => $terms[1]->id(),
        'target_association_id' => $this->firstAssociation->id(),
      ], [
        'target_id' => $terms[1]->id(),
        'target_association_id' => $this->secondAssociation->id(),
      ],
      ],
      'created' => $date->modify('+ 5 days')->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'Leaf',
      'type' => 'content_type_one',
      'body' => 'This is leaf',
      'status' => NodeInterface::PUBLISHED,
      'field_open_vocabularies' => [[
        'target_id' => $terms[1]->id(),
        'target_association_id' => $this->firstAssociation->id(),
      ], [
        'target_id' => $terms[0]->id(),
        'target_association_id' => $this->secondAssociation->id(),
      ],
      ],
      'created' => $date->modify('+ 5 days')->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    // Create some test nodes for content type two.
    $date = new DrupalDateTime('09-02-2021');
    $values = [
      'title' => 'Sun',
      'type' => 'content_type_two',
      'body' => 'This is the sun',
      'status' => NodeInterface::PUBLISHED,
      'field_open_vocabularies' => [[
        'target_id' => $terms[0]->id(),
        'target_association_id' => $this->firstAssociation->id(),
      ], [
        'target_id' => $terms[0]->id(),
        'target_association_id' => $this->secondAssociation->id(),
      ],
      ],
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'Grass',
      'type' => 'content_type_two',
      'body' => 'This is grass',
      'status' => NodeInterface::PUBLISHED,
      'field_open_vocabularies' => [[
        'target_id' => $terms[1]->id(),
        'target_association_id' => $this->firstAssociation->id(),
      ], [
        'target_id' => $terms[0]->id(),
        'target_association_id' => $this->secondAssociation->id(),
      ],
      ],
      'created' => $date->modify('+ 5 days')->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    $index->indexItems();
  }

  /**
   * Test exposed filters and default filters configuration.
   */
  public function testListPagePluginFiltersFormConfiguration(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();
    $this->assertTrue($assert->optionExists('Source bundle', 'Content type one')->isSelected());
    $page->checkField('Override default exposed filters');

    // By default, the CT exposed filters are Body and Status.
    $assert->checkboxChecked('Published');
    $assert->checkboxChecked('Body');
    $assert->checkboxNotChecked('Select one');
    $assert->checkboxNotChecked('Created');
    $assert->checkboxNotChecked($this->firstAssociation->label());
    $assert->checkboxNotChecked($this->secondAssociation->label());

    // Expose the associations fields.
    $page->checkField($this->firstAssociation->label());
    $page->checkField($this->secondAssociation->label());
    $page->fillField('Title', 'Node title');
    $page->pressButton('Save');

    $assert->pageTextContains('one yellow fruit');
    $assert->pageTextContains('a green fruit');
    $actual_bundles = $this->getSelectOptions($this->firstAssociation->label());
    $expected_bundles = [
      '1' => 'yellow color',
      '2' => 'green color',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    $actual_bundles = $this->getSelectOptions($this->secondAssociation->label());
    $expected_bundles = [
      '1' => 'yellow color',
      '2' => 'green color',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    // Search on the first association field.
    $this->getSession()->getPage()->selectFieldOption($this->firstAssociation->label(), 'yellow color');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('one yellow fruit');
    $assert->pageTextNotContains('a green fruit');
    $assert->pageTextNotContains('Leaf');

    // Reset search.
    $this->getSession()->getPage()->pressButton('Clear filters');
    $assert->pageTextContains('one yellow fruit');
    $assert->pageTextContains('a green fruit');
    $assert->pageTextContains('Leaf');

    // Search on the other value of the first association.
    $this->getSession()->getPage()->selectFieldOption($this->firstAssociation->label(), 'green color');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('one yellow fruit');
    $assert->pageTextContains('a green fruit');
    $assert->pageTextContains('Leaf');

    // Search on the second association.
    $this->getSession()->getPage()->selectFieldOption($this->secondAssociation->label(), 'yellow color');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('one yellow fruit');
    $assert->pageTextNotContains('a green fruit');
    $assert->pageTextContains('Leaf');

    // Add default values for the first association.
    $node = $this->drupalGetNodeByTitle('Node title');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');

    $page->selectFieldOption('Add default value for', $this->firstAssociation->label());
    $assert->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for ' . $this->firstAssociation->label());
    // Test required fields.
    $page->pressButton('Set default value');
    $assert->assertWaitOnAjaxRequest();
    $assert->pageTextContains($this->firstAssociation->label() . ' field is required.');

    $field_id = 'open_vocabularies_tags_vocabulary_tags_vocabulary_node_content_type_one_field_open_vocabularies';
    $association_filter_id = FilterConfigurationFormBuilderBase::generateFilterId($field_id);
    $default_value_name_prefix = 'emr_plugins_oe_list_page[wrapper][default_filter_values]';
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $association_filter_id . ']';
    $this->getSession()->getPage()->fillField($filter_selector . '[' . $field_id . '][0][entity_reference]', 'green color (2)');
    $page->pressButton('Set default value');
    $assert->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      [
        'key' => $this->firstAssociation->label(),
        'value' => 'Any of: Green color',
      ],
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');

    // Results are correct and facet correctly filled.
    $assert->pageTextNotContains('one yellow fruit');
    $assert->pageTextContains('a green fruit');
    $assert->pageTextContains('Leaf');
    $actual_bundles = $this->getSelectOptions($this->firstAssociation->label());
    $expected_bundles = [
      '2' => 'green color',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    $node = $this->drupalGetNodeByTitle('Node title');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');

    // Add another default value, for the second association.
    $page->selectFieldOption('Add default value for', $this->secondAssociation->label());
    $assert->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for ' . $this->secondAssociation->label());
    // Test required fields.
    $page->pressButton('Set default value');
    $assert->assertWaitOnAjaxRequest();
    $assert->pageTextContains($this->secondAssociation->label() . ' field is required.');

    $field_id = 'open_vocabularies_tags_vocabulary_two_tags_vocabulary_two_node_content_type_one_field_open_vocabularies';
    $association_filter_id = FilterConfigurationFormBuilderBase::generateFilterId($field_id);
    $default_value_name_prefix = 'emr_plugins_oe_list_page[wrapper][default_filter_values]';
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $association_filter_id . ']';
    $this->getSession()->getPage()->fillField($filter_selector . '[' . $field_id . '][0][entity_reference]', 'yellow color (1)');
    $page->pressButton('Set default value');
    $assert->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      [
        'key' => $this->firstAssociation->label(),
        'value' => 'Any of: Green color',
      ],
      [
        'key' => $this->secondAssociation->label(),
        'value' => 'Any of: Yellow color',
      ],
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');

    // Results are correct and facet correctly filled.
    $assert->pageTextNotContains('one yellow fruit');
    $assert->pageTextNotContains('a green fruit');
    $assert->pageTextContains('Leaf');
    $actual_bundles = $this->getSelectOptions($this->firstAssociation->label());
    $expected_bundles = [
      '2' => 'green color',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    $actual_bundles = $this->getSelectOptions($this->secondAssociation->label());
    $expected_bundles = [
      '1' => 'yellow color',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    // Delete associations and assert everything keeps working.
    $this->firstAssociation->delete();
    $this->secondAssociation->delete();
    $node = $this->drupalGetNodeByTitle('Node title');
    $this->drupalGet($node->toUrl());
    $assert->pageTextContains('one yellow fruit');
    $assert->pageTextContains('a green fruit');
    $assert->pageTextContains('Leaf');

    // Can be edited again.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $assert->fieldNotExists('Tags association');
    $assert->fieldNotExists('Tags association two');
    $page->pressButton('Save');
    $assert->pageTextContains('one yellow fruit');
    $assert->pageTextContains('a green fruit');
    $assert->pageTextContains('Leaf');
  }

  /**
   * Test contextual filters in link lists.
   */
  public function testListPageContextualFilters(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('link_list/add/dynamic');
    $assert_session = $this->assertSession();
    $this->assertTrue($assert_session->optionExists('Link source', 'List pages')->isSelected());
    $contextual_filter_name_prefix = 'configuration[0][link_source][plugin_configuration_wrapper][list_pages][list_page_configuration][wrapper][contextual_filters]';
    $this->getSession()->getPage()->fillField('Administrative title', 'List page plugin test');
    $this->getSession()->getPage()->fillField('Title', 'List page list OpenVocabularies');
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Title');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Source entity type', 'Content');
    $assert_session->assertWaitOnAjaxRequest();
    $page->selectFieldOption('Source bundle', 'Content type one');
    $assert_session->assertWaitOnAjaxRequest();

    // Set a contextual filter for the association.
    $field_id = 'open_vocabularies_tags_vocabulary_tags_vocabulary_node_content_type_one_field_open_vocabularies';
    $association_filter_id = FilterConfigurationFormBuilderBase::generateFilterId($field_id);
    $expected_contextual_filters = [];
    $page->selectFieldOption('Add contextual value for', $this->firstAssociation->label());
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('Set contextual options for ' . $this->firstAssociation->label());
    $filter_selector = $contextual_filter_name_prefix . '[wrapper][edit][' . $association_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[operator]', 'and');
    $page->pressButton('Set options');
    $assert_session->assertWaitOnAjaxRequest();
    $expected_contextual_filters['reference'] = [
      'key' => $this->firstAssociation->label(),
      'value' => 'All of',
    ];
    $this->assertContextualValueForFilters($expected_contextual_filters);
    $page->pressButton('Save');

    // Place the block for this link list.
    $link_list = $this->getLinkListByTitle('List page list OpenVocabularies', TRUE);
    $this->drupalPlaceBlock('oe_link_list_block:' . $link_list->uuid(), ['region' => 'content']);

    // Nodes from content type one with same terms appear.
    $node = $this->drupalGetNodeByTitle('Sun');
    $this->drupalGet($node->toUrl());
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextNotContains('a green fruit');
    $assert_session->pageTextNotContains('Leaf');
    $node = $this->drupalGetNodeByTitle('Grass');
    $this->drupalGet($node->toUrl());
    $assert_session->pageTextContains('a green fruit');
    $assert_session->pageTextContains('Leaf');
    $assert_session->pageTextNotContains('one yellow fruit');
  }

  /**
   * {@inheritdoc}
   */
  protected function goToListPageConfiguration(): void {
    $this->drupalGet('node/add/oe_list_page');
    $this->clickLink('List Page');
  }

}
