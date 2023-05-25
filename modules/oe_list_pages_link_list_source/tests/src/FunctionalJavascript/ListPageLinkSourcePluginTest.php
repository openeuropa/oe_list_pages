<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages_link_list_source\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\oe_link_lists\Entity\LinkList;
use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages_link_list_source\ContextualFiltersConfigurationBuilder;
use Drupal\oe_list_pages_link_list_source\ContextualPresetFilter;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Item\Field;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;
use Drupal\Tests\oe_list_pages\FunctionalJavascript\ListPagePluginFormTestBase;
use Drupal\user\Entity\Role;

/**
 * Tests the list page link source plugin.
 */
class ListPageLinkSourcePluginTest extends ListPagePluginFormTestBase {

  use LinkListTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'oe_list_pages_link_list_source',
    'oe_link_lists_test',
    'oe_list_pages_event_subscriber_test',
    'oe_list_pages_link_list_source_test',
    'oe_list_pages_address',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages_filters_test',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * Tests the plugin configuration form.
   */
  public function testPluginConfigurationForm(): void {
    $web_user = $this->drupalCreateUser([
      'create dynamic link list',
      'edit dynamic link list',
    ]);
    $this->drupalLogin($web_user);

    $this->assertListPageEntityTypeSelection();

    // In link lists, we disable the exposed filters and exposed sort.
    $this->goToListPageConfiguration();
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'vocabulary_one');
    $this->assertSession()->pageTextNotContains('An illegal choice has been detected.');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldNotExists('Override default exposed filters');
    $this->assertSession()->fieldNotExists('Exposed filters');
    $this->assertSession()->fieldNotExists('Expose sort');

    $this->getSession()->getPage()->selectFieldOption('Link display', 'Title');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Save the link list.
    $this->getSession()->getPage()->fillField('Administrative title', 'List page plugin test');
    $this->getSession()->getPage()->fillField('Title', 'List page list');
    $this->getSession()->getPage()->pressButton('Save');

    $link_list = $this->getLinkListByTitle('List page list', TRUE);
    $configuration = new ListPageConfiguration($link_list->getConfiguration()['source']['plugin_configuration']);
    $this->assertEquals('taxonomy_term', $configuration->getEntityType());
    $this->assertEquals('vocabulary_one', $configuration->getBundle());
    $this->assertEmpty($configuration->getExposedFilters());
    $this->assertFalse($configuration->isExposedFiltersOverridden());

    // Edit the link list and check the values are correctly pre-populated.
    $this->drupalGet($link_list->toUrl('edit-form'));

    $this->assertTrue($this->assertSession()->optionExists('Source entity type', 'Taxonomy term')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('Source bundle', 'Vocabulary one')->isSelected());

    // Change the source to a Node type.
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextNotContains('An illegal choice has been detected.');
    $this->getSession()->getPage()->pressButton('Save');

    $link_list = $this->getLinkListByTitle('List page list', TRUE);
    $configuration = new ListPageConfiguration($link_list->getConfiguration()['source']['plugin_configuration']);
    $this->assertEquals('node', $configuration->getEntityType());
    $this->assertEquals('content_type_one', $configuration->getBundle());
    $this->assertEmpty($configuration->getExposedFilters());
    $this->assertFalse($configuration->isExposedFiltersOverridden());
  }

  /**
   * Test list page preset filters form level validations.
   */
  public function testListPagePresetFilterValidations(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);

    $this->goToListPageConfiguration();
    $this->assertListPagePresetFilterValidations('configuration[0][link_source][plugin_configuration_wrapper][list_pages][list_page_configuration][wrapper][default_filter_values]');
  }

  /**
   * Test list page preset filters configuration.
   */
  public function testListPagePresetFilters(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();
    $this->getSession()->getPage()->fillField('Administrative title', 'List page plugin test');
    $this->getSession()->getPage()->fillField('Title', 'List page list');
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Title');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertListPagePresetFilters('configuration[0][link_source][plugin_configuration_wrapper][list_pages][list_page_configuration][wrapper][default_filter_values]');
  }

  /**
   * Test list page contextual filters configuration form.
   */
  public function testListPageContextualFiltersForm(): void {
    $contextual_filter_name_prefix = 'configuration[0][link_source][plugin_configuration_wrapper][list_pages][list_page_configuration][wrapper][contextual_filters]';
    $default_value_name_prefix = 'configuration[0][link_source][plugin_configuration_wrapper][list_pages][list_page_configuration][wrapper][default_filter_values]';

    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();
    $this->getSession()->getPage()->fillField('Administrative title', 'List page plugin test');
    $this->getSession()->getPage()->fillField('Title', 'List page list');
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Title');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Set tabs.
    $this->drupalPlaceBlock('local_tasks_block', ['primary' => TRUE]);

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $page->selectFieldOption('Source entity type', 'Content');
    $assert->assertWaitOnAjaxRequest();
    $page->selectFieldOption('Source bundle', 'Content type one');
    $assert->assertWaitOnAjaxRequest();

    // Assert that only certain filters can be set as contextual.
    $options = $this->getSelectOptions('Add contextual value for');
    $this->assertEquals([
      '' => '- None -',
      'link' => 'Link',
      'list_facet_source_node_content_type_onestatus' => 'Published',
      'reference' => 'Reference',
      'select_one' => 'Select one',
      'test_boolean' => 'Test Boolean',
      'oe_list_pages_filters_test_test_field' => 'Foo',
    ], $options);

    $reference_filter_id = ContextualFiltersConfigurationBuilder::generateFilterId('reference');
    $link_filter_id = ContextualFiltersConfigurationBuilder::generateFilterId('link');
    $body_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('body');

    $expected_contextual_filters = [];
    $expected_default_filters = [];

    // Set a contextual filter for Reference.
    $page->selectFieldOption('Add contextual value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set contextual options for Reference');
    $options = $this->getSelectOptions('Filter source');
    $this->assertEquals([
      'field_values' => 'Field values',
      'entity_id' => 'Entity ID',
    ], $options);
    $this->assertTrue($this->assertSession()->optionExists('Filter source', 'Field values')->hasAttribute('selected'));

    $filter_selector = $contextual_filter_name_prefix . '[wrapper][edit][' . $reference_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[operator]', 'and');
    $page->pressButton('Set options');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_contextual_filters['reference'] = [
      'key' => 'Reference',
      'value' => 'All of',
    ];
    $this->assertContextualValueForFilters($expected_contextual_filters);

    // Set a contextual filter for Link.
    $page->selectFieldOption('Add contextual value for', 'Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set contextual options for Link');
    $filter_selector = $contextual_filter_name_prefix . '[wrapper][edit][' . $link_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[operator]', 'not');
    $page->pressButton('Set options');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_contextual_filters['link'] = [
      'key' => 'Link',
      'value' => 'None of',
    ];
    $this->assertContextualValueForFilters($expected_contextual_filters);

    // Edit the Reference.
    $page->pressButton('contextual-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set contextual options for Reference');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'All of')->hasAttribute('selected'));

    // While the Reference contextual filter is open, add a Default filter
    // value.
    $page->selectFieldOption('Add default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Body');
    $page = $this->getSession()->getPage();
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $page->fillField($filter_selector, 'cherry');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_default_filters['body'] = ['key' => 'Body', 'value' => 'cherry'];
    $this->assertDefaultValueForFilters($expected_default_filters);

    // Continue editing the Reference contextual filter.
    $filter_selector = $contextual_filter_name_prefix . '[wrapper][edit][' . $reference_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[operator]', 'not');
    $page->pressButton('Set options');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_contextual_filters['reference'] = [
      'key' => 'Reference',
      'value' => 'None of',
    ];
    $this->assertContextualValueForFilters($expected_contextual_filters);

    // Start editing one contextual filter and one default value at the same
    // time.
    $page->pressButton('contextual-edit-' . $link_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set contextual options for Link');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'None of')->hasAttribute('selected'));

    $page->pressButton('default-edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Body');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $this->assertSession()->fieldValueEquals($filter_selector, 'cherry');

    // Cancel out of both forms, one at the time.
    $page->pressButton('contextual-cancel-' . $link_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextNotContains('Set contextual options for Link');
    $this->assertContextualValueForFilters($expected_contextual_filters);
    $assert->pageTextContains('Set default value for Body');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $this->assertSession()->fieldValueEquals($filter_selector, 'cherry');

    $page->pressButton('default-cancel-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextNotContains('Set default value for Body');
    $this->assertContextualValueForFilters($expected_contextual_filters);
    $this->assertDefaultValueForFilters($expected_default_filters);

    // Edit again one contextual filter and one default value at the same
    // time.
    $page->pressButton('contextual-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set contextual options for Reference');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'None of')->hasAttribute('selected'));

    $page->pressButton('default-edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Body');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $this->assertSession()->fieldValueEquals($filter_selector, 'cherry');

    // Submit them one at the time.
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $page->fillField($filter_selector, 'updated cherry');
    $expected_default_filters['body'] = [
      'key' => 'Body',
      'value' => 'updated cherry',
    ];
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters($expected_default_filters);
    $assert->pageTextContains('Set contextual options for Reference');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'None of')->hasAttribute('selected'));

    $filter_selector = $contextual_filter_name_prefix . '[wrapper][edit][' . $reference_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[operator]', 'or');
    // Change to an Entity ID filter source.
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[filter_source]', ContextualPresetFilter::FILTER_SOURCE_ENTITY_ID);
    $page->pressButton('Set options');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters($expected_default_filters);
    $expected_contextual_filters['reference'] = [
      'key' => 'Reference',
      'value' => 'Any of',
      'filter_source' => 'Entity ID',
    ];
    $this->assertContextualValueForFilters($expected_contextual_filters);

    // Save the link list and assert the values have been correctly saved.
    $page->pressButton('Save');
    $link_list = $this->getLinkListByTitle('List page list', TRUE);
    $configuration = new ListPageConfiguration($link_list->getConfiguration()['source']['plugin_configuration']);
    $this->assertEquals('node', $configuration->getEntityType());
    $this->assertEquals('content_type_one', $configuration->getBundle());
    $this->assertEmpty($configuration->getExposedFilters());
    $this->assertFalse($configuration->isExposedFiltersOverridden());
    $default_filter_values = $configuration->getDefaultFiltersValues();
    $contextual_filter_values = $link_list->getConfiguration()['source']['plugin_configuration']['contextual_filters'];

    $this->assertCount(1, $default_filter_values);
    $this->assertCount(2, $contextual_filter_values);
    /** @var \Drupal\oe_list_pages\ListPresetFilter $body */
    $body = $default_filter_values[$body_filter_id];
    $this->assertEquals('body', $body->getFacetId());
    $this->assertEquals('or', $body->getOperator());
    $this->assertEquals(['updated cherry'], $body->getValues());

    /** @var \Drupal\oe_list_pages_link_list_source\ContextualPresetFilter $reference */
    $reference = $contextual_filter_values[$reference_filter_id];
    $this->assertInstanceOf(ContextualPresetFilter::class, $reference);
    $this->assertEquals('reference', $reference->getFacetId());
    $this->assertEquals('or', $reference->getOperator());
    $this->assertEquals('entity_id', $reference->getFilterSource());
    $this->assertEmpty($reference->getValues());
    /** @var \Drupal\oe_list_pages_link_list_source\ContextualPresetFilter $link */
    $link = $contextual_filter_values[$link_filter_id];
    $this->assertInstanceOf(ContextualPresetFilter::class, $link);
    $this->assertEquals('link', $link->getFacetId());
    $this->assertEquals('not', $link->getOperator());
    $this->assertEquals('field_values', $link->getFilterSource());
    $this->assertEmpty($link->getValues());

    // Edit the link list and assert the values are pre-populated correctly.
    $this->clickLink('Edit');
    $this->assertContextualValueForFilters($expected_contextual_filters);
    $this->assertDefaultValueForFilters($expected_default_filters);
    $page->pressButton('contextual-edit-' . $link_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set contextual options for Link');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'None of')->hasAttribute('selected'));
    $this->assertTrue($this->assertSession()->optionExists('Filter source', 'Field values')->hasAttribute('selected'));
    $page->pressButton('contextual-cancel-' . $link_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertContextualValueForFilters($expected_contextual_filters);
    $page->pressButton('contextual-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set contextual options for Reference');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'Any of')->hasAttribute('selected'));
    $this->assertTrue($this->assertSession()->optionExists('Filter source', 'Entity ID')->hasAttribute('selected'));

    $page->pressButton('default-edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Body');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $this->assertSession()->fieldValueEquals($filter_selector, 'updated cherry');
  }

  /**
   * Test list page contextual filtering with field values as the source.
   */
  public function testListPageContextualFieldValuesFiltering(): void {
    // Create a link list.
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = LinkList::create([
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'My link list admin',
      'status' => 1,
    ]);

    $configuration = [
      'source' => [
        'plugin' => 'list_pages',
        'plugin_configuration' => [
          'entity_type' => 'node',
          'bundle' => 'content_type_one',
          'exposed_filters' => [],
          'exposed_filters_overridden' => FALSE,
          'default_filter_values' => [],
          'contextual_filters' => [],
        ],
      ],
      'display' => [
        'plugin' => 'title',
      ],
      'no_results_behaviour' => [
        'plugin' => 'hide_list',
        'plugin_configuration' => [],
      ],
    ];

    $link_list->setConfiguration($configuration);
    $link_list->save();

    // Place the bock for this link list.
    $this->drupalPlaceBlock('oe_link_list_block:' . $link_list->uuid(), ['region' => 'content']);

    // Grant permission to anonymous users to view the link list.
    $role = Role::load('anonymous');
    $role->grantPermission('view link list');
    $role->save();

    // Create two nodes that can be referenced.
    $refs = [];
    foreach (['ref1', 'ref2'] as $title) {
      $ref = Node::create([
        'type' => 'content_type_two',
        'title' => $title,
      ]);
      $ref->save();
      $refs[$title] = $ref;
    }

    // Create a Page node that will show the link list (will be the contextual
    // entity).
    $node = Node::create([
      'type' => 'page',
      'title' => 'My contextual page',
      'field_test_boolean' => 1,
      'field_reference' => $refs['ref1']->id(),
      'field_link' => 'http://example.com',
    ]);
    $node->save();
    $this->drupalGet($node->toUrl());

    // Create some nodes to be filtered in the link list.
    $map = [
      'field_test_boolean' => [
        'visible' => [
          'title' => 'visible boolean',
          'value' => 1,
          'facet' => 'test_boolean',
        ],
        'not visible' => [
          'title' => 'not visible boolean',
          'value' => 0,
          'facet' => 'test_boolean',
        ],
      ],
      'field_select_one' => [
        'visible' => [
          'title' => 'visible select',
          'value' => 'test1',
          'facet' => 'select_one',
        ],
        'not visible' => [
          'title' => 'not visible select',
          'value' => 'test2',
          'facet' => 'select_one',
        ],
      ],
      'field_reference' => [
        'visible' => [
          'title' => 'visible reference',
          'value' => $refs['ref1']->id(),
          'facet' => 'reference',
        ],
        'not visible' => [
          'title' => 'not visible reference',
          'value' => $refs['ref2']->id(),
          'facet' => 'reference',
        ],
      ],
      'field_link' => [
        'visible' => [
          'title' => 'visible link',
          'value' => 'http://example.com',
          'facet' => 'link',
        ],
        'not visible' => [
          'title' => 'not visible link',
          'value' => 'http://europa.eu',
          'facet' => 'link',
        ],
      ],
    ];

    // Create the nodes.
    foreach ($map as $field_name => $field_info) {
      foreach ($field_info as $expectation => $info) {
        Node::create([
          'type' => 'content_type_one',
          $field_name => $info['value'],
          'title' => $info['title'],
        ])->save();
      }
    }

    // Index the nodes.
    $index = Index::load('node');
    $index->indexItems();

    // Loop through all the created nodes and assert that they are now all
    // visible.
    $this->drupalGet($node->toUrl());
    foreach ($map as $field_name => $field_info) {
      foreach ($field_info as $expectation => $info) {
        $this->assertSession()->linkExistsExact($info['title']);
      }
    }

    // Set a contextual filter for a field which is empty in the contextual
    // page and assert we have no more results.
    $configuration = $link_list->getConfiguration();
    $configuration['source']['plugin_configuration']['contextual_filters'] = [
      ContextualFiltersConfigurationBuilder::generateFilterId('select_one') => new ContextualPresetFilter('select_one', [], 'or'),
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();
    $this->drupalGet($node->toUrl());
    $this->assertSession()->elementExists('css', '.block-oe-link-lists');
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 0);

    $node->set('field_select_one', 'test1');
    $node->save();

    // Re-loop the text content and assert only the correct ones are visible
    // once we configure the link list to add contextual filters.
    foreach ($map as $field_name => $field_info) {
      $visible = $field_info['visible'];
      $configuration = $link_list->getConfiguration();
      $configuration['source']['plugin_configuration']['contextual_filters'] = [
        ContextualFiltersConfigurationBuilder::generateFilterId($visible['facet']) => new ContextualPresetFilter($visible['facet'], [], 'or'),
      ];
      $link_list->setConfiguration($configuration);
      $link_list->save();

      $this->drupalGet($node->toUrl());

      foreach ($field_info as $expectation => $info) {
        if ($expectation === 'visible') {
          $this->assertSession()->linkExistsExact($info['title']);
          continue;
        }

        $this->assertSession()->linkNotExistsExact($info['title']);
      }
    }

    // Test that we can have multiple contextual filters.
    $configuration = $link_list->getConfiguration();
    $configuration['source']['plugin_configuration']['contextual_filters'] = [
      ContextualFiltersConfigurationBuilder::generateFilterId('select_one') => new ContextualPresetFilter('select_one', [], 'or'),
      ContextualFiltersConfigurationBuilder::generateFilterId('reference') => new ContextualPresetFilter('reference', [], 'or'),
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();

    Node::create([
      'type' => 'content_type_one',
      'title' => 'select one and reference',
      'field_select_one' => 'test1',
      'field_reference' => $refs['ref1']->id(),
    ])->save();

    $index = Index::load('node');
    $index->indexItems();

    $this->drupalGet($node->toUrl());
    $this->assertSession()->linkExistsExact('select one and reference');
    // We only have 1 resulting node.
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 1);

    // Test that other operators also work.
    $configuration = $link_list->getConfiguration();
    $configuration['source']['plugin_configuration']['contextual_filters'] = [
      ContextualFiltersConfigurationBuilder::generateFilterId('select_one') => new ContextualPresetFilter('select_one', [], 'not'),
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();

    $this->drupalGet($node->toUrl());

    // We got 7 results: all the previous nodes we created, minus the one with
    // the test1 select_one value. Also the last node we created is not visible.
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 7);
    $this->assertSession()->linkNotExistsExact('select one and reference');
    $this->assertSession()->linkNotExistsExact('visible select');
    $this->assertSession()->linkExistsExact('not visible select');

    // Test that contextual filters work together with default filter values.
    Node::create([
      'type' => 'content_type_one',
      'title' => 'node with both selects',
      'field_select_one' => ['test1', 'test2'],
    ])->save();

    Node::create([
      'type' => 'content_type_one',
      'title' => 'node with test2',
      'field_select_one' => ['test2'],
    ])->save();

    $index = Index::load('node');
    $index->indexItems();

    $configuration = $link_list->getConfiguration();
    $configuration['source']['plugin_configuration']['contextual_filters'] = [
      ContextualFiltersConfigurationBuilder::generateFilterId('select_one') => new ContextualPresetFilter('select_one', [], 'or'),
    ];
    $configuration['source']['plugin_configuration']['default_filter_values'] = [
      ContextualFiltersConfigurationBuilder::generateFilterId('select_one', array_keys($configuration['source']['plugin_configuration']['contextual_filters'])) => new ContextualPresetFilter('select_one', ['test2'], 'or'),
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();

    $this->drupalGet($node->toUrl());
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 1);
    // Only the node with both test1 and test2 selects will show up because we
    // have a default filter value set to test2 and a contextual filter on
    // the select and the current entity has test1. So only the one with both
    // show up.
    $this->assertSession()->linkExistsExact('node with both selects');

    // Test that if we view this link list on an entity which doesn't have
    // a field configured as contextual, we see no results.
    $user = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($user);
    $this->drupalGet($user->toUrl());
    // We are on the user page which is not going to have the fields.
    $this->assertSession()->elementExists('css', '.block-oe-link-lists');
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 0);

    // Test that if we view the link list on a page where there is no entity
    // in the context, we see no results.
    $this->drupalGet('/admin');
    $this->assertSession()->elementExists('css', '.block-oe-link-lists');
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 0);

    // Test that we can filter also using a field that is mapped and not with
    // the same name.
    $node = Node::create([
      'type' => 'article',
      'title' => 'My contextual article',
      'field_another_reference' => $refs['ref1']->id(),
    ]);
    $node->save();
    $configuration = $link_list->getConfiguration();
    $configuration['source']['plugin_configuration']['default_filter_values'] = [];
    $configuration['source']['plugin_configuration']['contextual_filters'] = [
      ContextualFiltersConfigurationBuilder::generateFilterId('reference') => new ContextualPresetFilter('reference', [], 'or'),
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();
    $this->drupalGet($node->toUrl());
    // By default we don't have any results.
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 0);
    // Configure the mapping of the reference field.
    $map = [
      'field_another_reference' => 'field_reference',
      'field_reference' => 'field_another_reference',
    ];
    $config = \Drupal::configFactory()->getEditable('oe_list_pages_link_list_source.contextual_field_map');
    $config->set('maps', [$map])->save();
    $this->drupalGet($node->toUrl());

    // Now we have two results which have the same reference as the Article.
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 2);
    $this->assertSession()->linkExistsExact('visible reference');
    $this->assertSession()->linkExistsExact('select one and reference');

    $node->isDefaultRevision(FALSE);
    $node->setNewRevision(TRUE);
    $node->setPublished(FALSE);
    $node->save();
    // Check it also appears on revision page.
    $this->drupalGet('/node/' . $node->id() . '/revisions/' . $node->getRevisionId() . '/view');
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 2);
    $this->assertSession()->linkExistsExact('visible reference');
    $this->assertSession()->linkExistsExact('select one and reference');

    // Test that we can also use custom search API fields as contextual filters.
    $configuration['source']['plugin_configuration']['contextual_filters'] = [
      ContextualFiltersConfigurationBuilder::generateFilterId('oe_list_pages_filters_test_test_field') => new ContextualPresetFilter('oe_list_pages_filters_test_test_field', [], 'or'),
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();
    $this->drupalGet($node->toUrl());
    // By default, we don't have any results.
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 0);
    // Add the test contextual filter to the Page node type.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_test_contextual_filter',
      'type' => 'string',
      'translatable' => '0',
      'cardinality' => 2,
    ])->save();
    FieldConfig::create([
      'label' => 'Test contextual filter',
      'description' => '',
      'field_name' => 'field_test_contextual_filter',
      'entity_type' => 'node',
      'bundle' => 'article',
      'required' => 0,
    ])->save();

    // We added the field but with no values, so we should still see no results.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 0);

    // Add some IDs as the field values so that our test processor can return
    // them.
    $list_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'title' => [
        'visible boolean',
        'not visible boolean',
      ],
    ]);
    $ids = array_keys($list_nodes);
    $node = Node::load($node->id());
    $node->set('field_test_contextual_filter', reset($ids));
    $node->save();
    $this->drupalGet($node->toUrl());
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 1);
    $this->assertSession()->linkExistsExact('not visible boolean');
    $node->set('field_test_contextual_filter', $ids);
    $node->save();
    $this->drupalGet($node->toUrl());
    $this->assertSession()->elementsCount('css', '.block-oe-link-lists ul li', 2);
    $this->assertSession()->linkExistsExact('not visible boolean');
    $this->assertSession()->linkExistsExact('visible boolean');
  }

  /**
   * Test list page contextual filtering with the entity ID as the source.
   */
  public function testListPageContextualEntityIdFiltering(): void {
    // Create a link list.
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = LinkList::create([
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'My link list admin',
      'status' => 1,
    ]);

    $configuration = [
      'source' => [
        'plugin' => 'list_pages',
        'plugin_configuration' => [
          'entity_type' => 'node',
          'bundle' => 'content_type_one',
          'exposed_filters' => [],
          'exposed_filters_overridden' => FALSE,
          'default_filter_values' => [],
          'contextual_filters' => [],
        ],
      ],
      'display' => [
        'plugin' => 'title',
      ],
    ];

    $link_list->setConfiguration($configuration);
    $link_list->save();

    // Place the bock for this link list.
    $this->drupalPlaceBlock('oe_link_list_block:' . $link_list->uuid(), ['region' => 'content']);

    // Grant permission to anonymous users to view the link list.
    $role = Role::load('anonymous');
    $role->grantPermission('view link list');
    $role->save();

    // Create two nodes that can be referenced.
    $ref_one = Node::create([
      'type' => 'content_type_two',
      'title' => 'Referenced node 1',
    ]);
    $ref_one->save();
    $ref_two = Node::create([
      'type' => 'content_type_two',
      'title' => 'Referenced node 2',
    ]);
    $ref_two->save();

    // Create a node that can reference nodes and which will be listed by the
    // link list.
    $node = Node::create([
      'type' => 'content_type_one',
      'field_reference' => $ref_one->id(),
      'title' => 'Listed node',
    ]);
    $node->save();

    // Index the nodes.
    $index = Index::load('node');
    $index->indexItems();

    // Without any contextual filters, the listed node should be seen on both
    // the referenced nodes.
    $this->drupalGet($ref_one->toUrl());
    $this->assertSession()->linkExistsExact('Listed node');
    $this->drupalGet($ref_two->toUrl());
    $this->assertSession()->linkExistsExact('Listed node');

    // Set a contextual filter on the link list for the Reference field and
    // assert that now we won't see a result on the link list in any of the
    // cases. This because content_type_two doesn't even have that field to
    // get values from which is the default contextual filter behaviour.
    $configuration = $link_list->getConfiguration();
    $configuration['source']['plugin_configuration']['contextual_filters'] = [
      ContextualFiltersConfigurationBuilder::generateFilterId('reference') => new ContextualPresetFilter('reference', [], 'or'),
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();

    $this->drupalGet($ref_one->toUrl());
    $this->assertSession()->linkNotExistsExact('Listed node');
    $this->drupalGet($ref_two->toUrl());
    $this->assertSession()->linkNotExistsExact('Listed node');

    // Update the contextual filter to set the filter source to be the entity
    // ID instead of the field values so as to check the current entity ID
    // when filtering.
    $filter = new ContextualPresetFilter('reference', [], 'or');
    $filter->setFilterSource(ContextualPresetFilter::FILTER_SOURCE_ENTITY_ID);
    $configuration['source']['plugin_configuration']['contextual_filters'] = [
      ContextualFiltersConfigurationBuilder::generateFilterId('reference') => $filter,
    ];
    $link_list->setConfiguration($configuration);
    $link_list->save();

    // Now we should see the listed node only on the ref_one node because
    // that is what the listed node references.
    $this->drupalGet($ref_one->toUrl());
    $this->assertSession()->linkExistsExact('Listed node');
    $this->drupalGet($ref_two->toUrl());
    $this->assertSession()->linkNotExistsExact('Listed node');

    // Switch out the node value and now it should be the other way around.
    $node->set('field_reference', $ref_two);
    $node->save();
    $index = Index::load('node');
    $index->indexItems();

    $this->drupalGet($ref_one->toUrl());
    $this->assertSession()->linkNotExistsExact('Listed node');
    $this->drupalGet($ref_two->toUrl());
    $this->assertSession()->linkExistsExact('Listed node');
  }

  /**
   * Test list page contextual filtering current entity exclusion.
   *
   * Tests that a link list source can be configured so that if placed on an
   * entity page, it can filter out the current entity from the results.
   */
  public function testListPageContextualSelfExclusion(): void {
    // Create two nodes to render.
    $node_one = Node::create([
      'type' => 'content_type_one',
      'title' => 'Listed node one',
      'status' => 1,
    ]);
    $node_one->save();
    $node_two = Node::create([
      'type' => 'content_type_one',
      'title' => 'Listed node two',
      'status' => 1,
    ]);
    $node_two->save();
    $index = Index::load('node');
    $index->indexItems();

    // Create a normal link list that renders a node of content_type_one and
    // place it on the node page of this content type.
    /** @var \Drupal\oe_link_lists\Entity\LinkListInterface $link_list */
    $link_list = LinkList::create([
      'bundle' => 'dynamic',
      'title' => 'My link list',
      'administrative_title' => 'My link list admin',
      'status' => 1,
    ]);

    $configuration = [
      'source' => [
        'plugin' => 'list_pages',
        'plugin_configuration' => [
          'entity_type' => 'node',
          'bundle' => 'content_type_one',
          'exposed_filters' => [],
          'exposed_filters_overridden' => FALSE,
          'default_filter_values' => [],
          'contextual_filters' => [],
        ],
      ],
      'display' => [
        'plugin' => 'title',
      ],
      'no_results_behaviour' => [
        'plugin' => 'hide_list',
        'plugin_configuration' => [],
      ],
    ];

    $link_list->setConfiguration($configuration);
    $link_list->save();
    $this->drupalPlaceBlock('page_title_block');
    $this->drupalPlaceBlock('oe_link_list_block:' . $link_list->uuid(), ['region' => 'content']);

    // Grant permission to anonymous users to view the link list.
    $role = Role::load('anonymous');
    $role->grantPermission('view link list');
    $role->save();

    // Go to each of the node and assert we see the link list.
    $this->drupalGet($node_one->toUrl());
    $this->assertSession()->elementContains('css', '.page-title', 'Listed node one');
    $this->assertSession()->linkExistsExact('Listed node one');
    $this->assertSession()->linkExistsExact('Listed node two');
    $this->drupalGet($node_two->toUrl());
    $this->assertSession()->elementContains('css', '.page-title', 'Listed node two');
    $this->assertSession()->linkExistsExact('Listed node one');
    $this->assertSession()->linkExistsExact('Listed node two');

    $web_user = $this->drupalCreateUser([
      'create dynamic link list',
      'edit dynamic link list',
      'view link list',
    ]);
    $this->drupalLogin($web_user);

    // Edit the link list and assert we don't yet see the checkbox to exclude
    // the current entity because we don't have an ID field indexed.
    $this->drupalGet($link_list->toUrl('edit-form'));
    $this->assertSession()->selectExists('Link source');
    $this->assertSession()->fieldNotExists('Exclude the current entity');

    // Add the ID field to the index.
    $field = new Field($index, 'list_page_link_source_id');
    $field->setType('integer');
    $field->setPropertyPath('nid');
    $field->setDatasourceId('entity:node');
    $field->setLabel('ID');
    $field->setDependencies([
      'modules' => [
        'node',
      ],
    ]);
    $index->addField($field);
    $index->save();
    $index->reindex();
    $index->indexItems();

    // Edit the link list and exclude the current entity from being shown if
    // found in the results.
    $this->drupalGet($link_list->toUrl('edit-form'));
    $this->assertSession()->fieldExists('Exclude the current entity');
    $this->getSession()->getPage()->checkField('Exclude the current entity');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the My link list admin Link list.');

    // Go back to the respective nodes and assert we don't see them anymore
    // in the results.
    $this->drupalGet($node_one->toUrl());
    $this->assertSession()->elementContains('css', '.page-title', 'Listed node one');
    $this->assertSession()->linkNotExistsExact('Listed node one');
    $this->assertSession()->linkExistsExact('Listed node two');
    $this->drupalGet($node_two->toUrl());
    $this->assertSession()->elementContains('css', '.page-title', 'Listed node two');
    $this->assertSession()->linkExistsExact('Listed node one');
    $this->assertSession()->linkNotExistsExact('Listed node two');

    // Add the ID field also to the taxonomy index.
    $index = Index::load('taxonomy');
    $field = new Field($index, 'list_page_link_source_id');
    $field->setType('integer');
    $field->setPropertyPath('tid');
    $field->setDatasourceId('entity:taxonomy_term');
    $field->setLabel('ID');
    $field->setDependencies([
      'modules' => [
        'taxonomy',
      ],
    ]);
    $index->addField($field);
    $index->save();

    // Create terms until we get a term that has the same ID as the first node
    // we created.
    $nid = $node_one->id();
    $tid = 0;
    while ($nid !== $tid) {
      $term = Term::create([
        'vid' => 'vocabulary_one',
        'name' => 'Term name',
      ]);
      $term->save();
      $term->set('name', 'Term ' . $term->id());
      $term->save();
      $tid = $term->id();
    }
    $this->assertEquals($nid, $term->id());
    $index->reindex();
    $index->indexItems();

    // Change the link list to show taxonomy terms instead of nodes.
    $this->drupalGet($link_list->toUrl('edit-form'));
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'vocabulary_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Saved the My link list admin Link list.');

    // Go back to the first node and assert that even if the current entity ID
    // (the node) has the same ID as a term in the link list result, we won't
    // hide the term from the results because it's a different list source than
    // the current entity (node).
    $this->drupalGet($node_one->toUrl());
    $this->assertSession()->elementContains('css', '.page-title', 'Listed node one');
    $this->assertSession()->linkExistsExact($term->label());
  }

  /**
   * {@inheritdoc}
   */
  protected function goToListPageConfiguration(): void {
    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->selectFieldOption('Link source', 'List page');
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

}
