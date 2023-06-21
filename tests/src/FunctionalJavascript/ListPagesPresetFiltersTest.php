<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\facets\Entity\Facet;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\query_type\DateStatus;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\oe_list_pages\Traits\FacetsTestTrait;

/**
 * Tests the list page preset filters.
 *
 * @group oe_list_pages
 */
class ListPagesPresetFiltersTest extends ListPagePluginFormTestBase {

  use FacetsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'options',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages',
    'oe_list_pages_filters_test',
    'oe_list_page_content_type',
    'oe_list_pages_address',
    'node',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
    'multivalue_form_element',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * Test presence of preset filters configuration in the plugin.
   */
  public function testListPageFiltersPresenceInContentForm(): void {
    // Default filters configuration is present in oe_list_page content type.
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $this->assertSession()->fieldExists('Add default value for');

    // Create a new content type without preset filters functionality.
    $this->drupalCreateContentType([
      'type' => 'list_without_preset_filters',
      'name' => 'List without preset filters',
    ]);

    /** @var \Drupal\emr\EntityMetaRelationInstaller $installer */
    $installer = \Drupal::service('emr.installer');
    $installer->installEntityMetaTypeOnContentEntityType('oe_list_page', 'node', [
      'list_without_preset_filters',
    ]);

    $this->drupalGet('/node/add/list_without_preset_filters');
    $this->clickLink('List Page');
    $this->assertSession()->fieldNotExists('Add default value for');
  }

  /**
   * Test list page preset filters form level validations.
   */
  public function testListPagePresetFilterValidations(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);

    $this->goToListPageConfiguration();
    $this->assertListPagePresetFilterValidations('emr_plugins_oe_list_page[wrapper][default_filter_values]');
  }

  /**
   * Test list page preset filters configuration.
   */
  public function testListPagePresetFilters(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('/node/add/oe_list_page');
    $this->getSession()->getPage()->fillField('Title', 'List page for ct1');
    $this->clickLink('List Page');
    $this->assertListPagePresetFilters('emr_plugins_oe_list_page[wrapper][default_filter_values]');
  }

  /**
   * Test list page preset filters configuration with a default status facet.
   *
   * Default status facets have a processor that sets a default status if
   * a filter for that facet doesn't exist.
   */
  public function testListPageDefaultStatusPresetFilters(): void {
    // Create the configured facet.
    $list_id = ListSourceFactory::generateFacetSourcePluginId('node', 'content_type_one');

    $processor_options = [
      'default_status' => DateStatus::PAST,
      'upcoming_label' => 'Coming items',
      'past_label' => 'Past items',
    ];
    $facet = $this->createFacet('end_value', $list_id, 'options', 'oe_list_pages_multiselect', []);
    $facet->addProcessor([
      'processor_id' => 'oe_list_pages_date_status_processor',
      'weights' => ['pre_query' => 60, 'build' => 35],
      'settings' => $processor_options,
    ]);
    $facet->save();

    $past_date = new DrupalDateTime();
    $past_date->modify('-1 month');

    $future_date = new DrupalDateTime();
    $future_date->modify('+1 month');

    // Past nodes.
    $values = [
      'title' => 'Past node 1',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'field_date_range' => [
        'value' => $past_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $past_date->modify('+1 day')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'Past node 2',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'field_date_range' => [
        'value' => $past_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $past_date->modify('+1 day')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ];
    $node = Node::create($values);
    $node->save();

    // Future node.
    $values = [
      'title' => 'Future node',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'field_date_range' => [
        'value' => $future_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        'end_value' => $future_date->modify('+1 day')->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      ],
    ];
    $node = Node::create($values);
    $node->save();

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    $index->indexItems();

    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('/node/add/oe_list_page');
    $this->getSession()->getPage()->fillField('Title', 'List page for ct1');
    $this->clickLink('List Page');

    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');

    // We should only see the past nodes by default because we have the facet
    // configured to show the past events.
    $this->assertSession()->pageTextContains('Past node 1');
    $this->assertSession()->pageTextContains('Past node 2');
    $this->assertSession()->pageTextContains('Facet for end_value: Past items');
    $this->assertSession()->pageTextNotContains('Future node');

    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');

    // Set preset filter for default date facet.
    $this->getSession()->getPage()->selectFieldOption('Add default value for', 'Facet for end_value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $filter_id = DefaultFilterConfigurationBuilder::generateFilterId($facet->id());
    $filter_selector = 'emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[' . $facet->id() . '][0][list]', DateStatus::UPCOMING);
    $this->getSession()->getPage()->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    // The preset filter took over the default status.
    $this->assertSession()->pageTextNotContains('Past node 1');
    $this->assertSession()->pageTextNotContains('Past node 2');
    $this->assertSession()->pageTextNotContains('Facet for end_value');
    $this->assertSession()->pageTextContains('Future node');

    // Include both upcoming and past.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $this->getSession()->getPage()->pressButton('default-edit-' . $filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $filter_id . '][oe_list_pages_filter_operator]', 'Any of');
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[' . $facet->id() . '][1][list]', DateStatus::PAST);
    $this->getSession()->getPage()->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Past node 1');
    $this->assertSession()->pageTextContains('Past node 2');
    $this->assertSession()->pageTextContains('Future node');
    $this->assertSession()->pageTextNotContains('Facet for end_value');
  }

  /**
   * Tests that we can use custom Search API fields with default statuses.
   */
  public function testCustomFieldWithDefaultStatus(): void {
    // Configure the facet.
    $processor_options = [
      'default_status' => '1',
    ];
    $facet = Facet::load('oe_list_pages_filters_test_test_field');
    $facet->addProcessor([
      'processor_id' => 'oe_list_pages_filters_test_foo_processor',
      'weights' => ['pre_query' => 60, 'build' => 35],
      'settings' => $processor_options,
    ]);
    $facet->save();

    // Create 2 nodes.
    $values = [
      'title' => 'Node 1',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'Node 2',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
    ];
    $node = Node::create($values);
    $node->save();

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    $index->indexItems();

    // Create the list page.
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('/node/add/oe_list_page');
    $this->getSession()->getPage()->fillField('Title', 'List page for ct1');
    $this->clickLink('List Page');

    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');

    // We should only see Node 1 as the default value of Foo face is set to 1.
    $this->assertSession()->pageTextContains('Node 1');
    $this->assertSession()->pageTextNotContains('Node 2');

    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');

    // Set preset filter for the Foo facet.
    $this->getSession()->getPage()->selectFieldOption('Add default value for', 'Foo');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $filter_id = DefaultFilterConfigurationBuilder::generateFilterId($facet->id());
    $filter_selector = 'emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[' . $facet->id() . '][0][list]', 2);
    $this->getSession()->getPage()->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');

    // We should only see Node 2 as the Foo facet was set to 2.
    $this->assertSession()->pageTextNotContains('Node 1');
    $this->assertSession()->pageTextContains('Node 2');
  }

  /**
   * Tests that preset filter-based results can only be narrowed.
   */
  public function testPresetFilterResultsNarrowing(): void {
    $past_date = new DrupalDateTime('30-10-2010');
    $future_date = new DrupalDateTime('30-10-2030');
    $middle_date = new DrupalDateTime('30-10-2020');

    $values = [
      'title' => 'test 1',
      'type' => 'content_type_one',
      'body' => 'test 1',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => ['test1'],
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'test 2',
      'type' => 'content_type_one',
      'body' => 'test 2',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => ['test2'],
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'test 3',
      'type' => 'content_type_one',
      'body' => 'test 3',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => ['test3'],
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'test 1 and 2',
      'type' => 'content_type_one',
      'body' => 'test 1 and test 2',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => ['test1', 'test2'],
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'past node',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'created' => $past_date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'future node',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'created' => $future_date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'middle node',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'created' => $middle_date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    $index->indexItems();

    $all_nodes = Node::loadMultiple();

    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('/node/add/oe_list_page');
    $this->getSession()->getPage()->fillField('Title', 'List page for ct1');
    $this->clickLink('List Page');

    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->checkField('Override default exposed filters');
    $this->getSession()->getPage()->checkField('Select one');
    $this->getSession()->getPage()->checkField('Created');
    $this->getSession()->getPage()->pressButton('Save');

    foreach ($all_nodes as $node) {
      $this->assertSession()->pageTextContains($node->label());
    }

    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $filters = [
      DefaultFilterConfigurationBuilder::generateFilterId('select_one') => new ListPresetFilter('select_one', ['test1']),
    ];
    $this->setListPageFilters($node, $filters);
    $this->getSession()->reload();
    $this->assertResultCount(2);
    $this->assertSession()->pageTextContains('test 1');
    $this->assertSession()->pageTextContains('test 1 and 2');
    // Only the narrowing options should exist in the exposed form of this facet
    // which has default values.
    $this->assertSelectOptions('Select one', ['test1', 'test2']);
    $this->assertSession()->linkNotExistsExact('test1');
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test1');
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertResultCount(2);
    $this->assertSession()->pageTextContains('test 1');
    $this->assertSession()->pageTextContains('test 1 and 2');
    // Now we have an active filter so we show the selected filters.
    $this->assertSession()->linkExistsExact('test1');
    $this->getSession()->getPage()->clickLink('test1');
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test2');
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertResultCount(1);
    // Now the query is test1 AND test2.
    $this->assertSession()->pageTextContains('test 1 and 2');

    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $filters = [
      DefaultFilterConfigurationBuilder::generateFilterId('select_one') => new ListPresetFilter('select_one', [
        'test1',
        'test2',
      ]),
    ];
    $this->setListPageFilters($node, $filters);
    $this->getSession()->getPage()->pressButton('Clear filters');

    $this->assertResultCount(3);
    $this->assertSession()->pageTextContains('test 1');
    $this->assertSession()->pageTextContains('test 2');
    $this->assertSession()->pageTextContains('test 1 and 2');

    // Add a date default filter that includes all results.
    $filters = [
      DefaultFilterConfigurationBuilder::generateFilterId('created') => new ListPresetFilter('created', ['bt|2009-01-01T15:30:44+01:00|2031-01-01T15:30:44+01:00']),
    ];
    $this->setListPageFilters($node, $filters);
    $this->getSession()->reload();
    foreach ($all_nodes as $node) {
      $this->assertSession()->pageTextContains($node->label());
    }
    $this->assertSession()->linkNotExists('Between');

    $this->getSession()->getPage()->selectFieldOption('Select one', 'test3');
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertResultCount(1);
    $this->assertSession()->pageTextContains('test 3');
    $this->assertSession()->linkExistsExact('test3');

    $this->getSession()->getPage()->pressButton('Clear filters');
    // Narrow down the results by one old record.
    $this->getSession()->getPage()->selectFieldOption('Created', 'After');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '01/01/2011');
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertResultCount(count($all_nodes) - 1);
    $this->assertSession()->pageTextNotContains('past node');
  }

  /**
   * Sets the preset filters on a list page node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param array $filters
   *   The preset filters.
   */
  protected function setListPageFilters(NodeInterface $node, array $filters): void {
    $meta = $node->get('emr_entity_metas')->getEntityMeta('oe_list_page');
    $configuration = $meta->getWrapper()->getConfiguration();
    $configuration['preset_filters'] = $filters;
    $meta->getWrapper()->setConfiguration($configuration);
    $node->get('emr_entity_metas')->attach($meta);
    $node->save();
  }

  /**
   * Asserts the number of results on the list page.
   *
   * @param int $count
   *   The expected count.
   */
  protected function assertResultCount(int $count): void {
    $items = $this->getSession()->getPage()->findAll('css', '.node--view-mode-teaser');
    $this->assertCount($count, $items);
  }

  /**
   * Asserts the select has certain options.
   *
   * @param string $field
   *   The label, id or name of select box.
   * @param array $expected
   *   The expected options.
   */
  protected function assertSelectOptions(string $field, array $expected): void {
    $page = $this->getSession()->getPage();
    $options = $page->findField($field)->findAll('css', 'option');
    $actual_options = [];
    foreach ($options as $option) {
      $actual_options[] = $option->getValue();
    }

    sort($actual_options);
    sort($expected);
    $this->assertEquals($expected, $actual_options);
  }

  /**
   * {@inheritdoc}
   */
  protected function goToListPageConfiguration(): void {
    $this->drupalGet('node/add/oe_list_page');
    $this->clickLink('List Page');
  }

}
