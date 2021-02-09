<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages_open_vocabularies\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\oe_list_pages\ListPresetFiltersBuilder;
use Drupal\open_vocabularies\Entity\OpenVocabulary;
use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;
use Drupal\search_api\Entity\Index;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\oe_list_pages\FunctionalJavascript\ListPagePluginFormTestBase;

/**
 * Tests the List pages open vocabularies filters.
 *
 * @group oe_list_pages
 */
class OpenVocabulariesFiltersTest extends ListPagePluginFormTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'options',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages',
    'oe_list_page_content_type',
    'oe_list_pages_filters_test',
    'oe_list_pages_open_vocabularies_test',
    'open_vocabularies',
    'node',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
  ];
  /**
   * The association to test.
   *
   * @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface
   */
  protected $association;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    /** @var \Drupal\emr\EntityMetaRelationInstaller $installer */
    $installer = \Drupal::service('emr.installer');
    $installer->installEntityMetaTypeOnContentEntityType('oe_list_page', 'node', [
      'oe_list_page',
    ]);

    // Create OpenVocabularies vocabularies.
    $values = [
      'id' => 'custom_vocabulary',
      'label' => 'My amazing vocabulary',
      'description' => $this->randomString(128),
      'handler' => 'taxonomy',
      'handler_settings' => [
        'target_bundles' => [
          'entity_test' => 'vocabulary_one',
        ],
      ],
    ];

    /** @var \Drupal\open_vocabularies\OpenVocabularyInterface $vocabulary */
    $vocabulary = OpenVocabulary::create($values);
    $vocabulary->save();

    // Create association for content types.
    $fields = [
      'node.content_type_one.field_open_vocabularies',
    ];
    $values = [
      'label' => 'My amazing association',
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
    $association->save();
    $association_id = $association->id();
    $this->association = $association;

    // Create some taxonomy terms.
    $values = [
      'name' => 'yellow color',
      'vid' => 'vocabulary_one',
    ];
    $term_1 = Term::create($values);
    $term_1->save();

    $values = [
      'name' => 'green color',
      'vid' => 'vocabulary_one',
    ];
    $term_2 = Term::create($values);
    $term_2->save();

    // Create some test nodes to index and search in.
    $date = new DrupalDateTime('09-02-2021');
    $values = [
      'title' => 'one yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a banana',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'field_open_vocabularies' => [
        'target_id' => $term_1->id(),
        'target_association_id' => $association_id,
      ],
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    $values = [
      'title' => 'another yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a lemon',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test2',
      'field_open_vocabularies' => [
        'target_id' => $term_2->id(),
        'target_association_id' => $association_id,
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
    $actual_entity_types = $this->getSelectOptions('Source entity type');
    $expected_entity_types = [
      '' => '- Select -',
      'node' => 'Content',
      'taxonomy_term' => 'Taxonomy term',
    ];
    $this->assertEquals($expected_entity_types, $actual_entity_types);
    // By default, Node is selected if there are no stored values.
    $this->assertOptionSelected('Source entity type', 'Content');

    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'content_type_one' => 'Content type one',
      'content_type_two' => 'Content type two',
      '' => '- Select -',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Source bundle', 'Content type one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->checkField('Override default exposed filters');

    // By default, the CT exposed filters are Body and Status.
    $this->assertSession()->checkboxChecked('Published');
    $this->assertSession()->checkboxChecked('Body');
    $this->assertSession()->checkboxNotChecked('Select one');
    $this->assertSession()->checkboxNotChecked('Created');
    $this->assertSession()->checkboxNotChecked('My amazing association');
    $page->uncheckField('Body');

    // Expose association field.
    $page->checkField('My amazing association');
    $page->fillField('Title', 'Node title');
    $page->pressButton('Save');

    $this->assertSession()->pageTextContains('one yellow fruit');
    $this->assertSession()->pageTextContains('another yellow fruit');

    $actual_bundles = $this->getSelectOptions('My amazing association');
    $expected_bundles = [
      '1' => 'yellow color',
      '2' => 'green color',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    // Search on association field.
    $this->getSession()->getPage()->selectFieldOption('My amazing association', 'yellow color');
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertSession()->pageTextContains('one yellow fruit');
    $this->assertSession()->pageTextNotContains('another yellow fruit');

    // Reset search.
    $this->getSession()->getPage()->pressButton('Clear filters');
    $this->assertSession()->pageTextContains('one yellow fruit');
    $this->assertSession()->pageTextContains('another yellow fruit');

    // Search on the other value.
    $this->getSession()->getPage()->selectFieldOption('My amazing association', 'green color');
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertSession()->pageTextNotContains('one yellow fruit');
    $this->assertSession()->pageTextContains('another yellow fruit');

    // Add default values.
    $node = $this->drupalGetNodeByTitle('Node title');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');

    $page->selectFieldOption('Add default value for', 'My amazing association');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for My amazing association');
    // Test required fields.
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('My amazing association field is required.');

    // Check setting open vocabulary fields work.
    $field_id = 'open_vocabularies_custom_vocabulary_open_vocabulary_node_content_type_one_field_open_vocabularies';
    $association_filter_id = ListPresetFiltersBuilder::generateFilterId($field_id);
    $default_value_name_prefix = 'emr_plugins_oe_list_page[wrapper][default_filter_values]';
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $association_filter_id . ']';
    $this->getSession()->getPage()->fillField($filter_selector . '[' . $field_id . '][0][entity]', 'green color (2)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      ['key' => 'My amazing association', 'value' => 'Any of: Green color'],
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');

    // Results are correct and facet correctly filled.
    $this->assertSession()->pageTextNotContains('one yellow fruit');
    $this->assertSession()->pageTextContains('another yellow fruit');
    $actual_bundles = $this->getSelectOptions('My amazing association');
    $expected_bundles = [
      '2' => 'green color',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);
  }

  /**
   * Get select box available options.
   *
   * @param string $field
   *   The label, id or name of select box.
   *
   * @return array
   *   Select box options.
   */
  protected function getSelectOptions(string $field): array {
    $page = $this->getSession()->getPage();
    $options = $page->findField($field)->findAll('css', 'option');
    $actual_options = [];
    foreach ($options as $option) {
      $actual_options[$option->getValue()] = $option->getText();
    }
    return $actual_options;
  }

  /**
   * {@inheritdoc}
   */
  protected function goToListPageConfiguration(): void {
    $this->drupalGet('node/add/oe_list_page');
    $this->clickLink('List Page');
  }

}
