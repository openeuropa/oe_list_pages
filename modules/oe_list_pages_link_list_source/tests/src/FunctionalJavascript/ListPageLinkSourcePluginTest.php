<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages_link_list_source\FunctionalJavascript;

use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages_link_list_source\ContextualFiltersConfigurationBuilder;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;
use Drupal\Tests\oe_list_pages\FunctionalJavascript\ListPagePluginFormTestBase;

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
   * Tests the plugin configuration form.
   */
  public function testPluginConfigurationForm(): void {
    $web_user = $this->drupalCreateUser([
      'create dynamic link list',
      'edit dynamic link list',
    ]);
    $this->drupalLogin($web_user);

    $this->assertListPageEntityTypeSelection();

    // In link lists, we disable the exposed filters.
    $this->goToListPageConfiguration();
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'vocabulary_one');
    $this->assertSession()->pageTextNotContains('An illegal choice has been detected.');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldNotExists('Override default exposed filters');
    $this->assertSession()->fieldNotExists('Exposed filters');

    $this->getSession()->getPage()->selectFieldOption('Link display', 'Foo');
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

    $this->assertOptionSelected('Source entity type', 'Taxonomy term');
    $this->assertOptionSelected('Source bundle', 'Vocabulary one');

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
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Foo');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertListPagePresetFilters('configuration[0][link_source][plugin_configuration_wrapper][list_pages][list_page_configuration][wrapper][default_filter_values]');
  }

  /**
   * Test list page contextual filters configuration.
   */
  public function testListPageContextualFilters(): void {
    $contextual_filter_name_prefix = 'configuration[0][link_source][plugin_configuration_wrapper][list_pages][list_page_configuration][wrapper][contextual_filters]';
    $default_value_name_prefix = 'configuration[0][link_source][plugin_configuration_wrapper][list_pages][list_page_configuration][wrapper][default_filter_values]';

    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();
    $this->getSession()->getPage()->fillField('Administrative title', 'List page plugin test');
    $this->getSession()->getPage()->fillField('Title', 'List page list');
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Foo');
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
    ], $options);

    $reference_filter_id = ContextualFiltersConfigurationBuilder::generateFilterId('reference');
    $link_filter_id = ContextualFiltersConfigurationBuilder::generateFilterId('link');
    $body_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('body');

    $expected_contextual_filters = [];
    $expected_default_filters = [];

    // Set a contextual filter for Reference.
    $page->selectFieldOption('Add contextual value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set operator for Reference');
    $filter_selector = $contextual_filter_name_prefix . '[wrapper][edit][' . $reference_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[operator]', 'and');
    $page->pressButton('Set operator');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_contextual_filters['reference'] = ['key' => 'Reference', 'value' => 'All of'];
    $this->assertContextualValueForFilters($expected_contextual_filters);

    // Set a contextual filter for Link.
    $page->selectFieldOption('Add contextual value for', 'Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set operator for Link');
    $filter_selector = $contextual_filter_name_prefix . '[wrapper][edit][' . $link_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[operator]', 'not');
    $page->pressButton('Set operator');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_contextual_filters['link'] = ['key' => 'Link', 'value' => 'None of'];
    $this->assertContextualValueForFilters($expected_contextual_filters);

    // Edit the Reference.
    $page->pressButton('contextual-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set operator for Reference');
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
    $page->pressButton('Set operator');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_contextual_filters['reference'] = ['key' => 'Reference', 'value' => 'None of'];
    $this->assertContextualValueForFilters($expected_contextual_filters);

    // Start editing one contextual filter and one default value at the same
    // time.
    $page->pressButton('contextual-edit-' . $link_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set operator for Link');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'None of')->hasAttribute('selected'));

    $page->pressButton('default-edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Body');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $this->assertSession()->fieldValueEquals($filter_selector, 'cherry');

    // Cancel out of both forms, one at the time.
    $page->pressButton('contextual-cancel-' . $link_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextNotContains('Set operator for Link');
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
    $assert->pageTextContains('Set operator for Reference');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'None of')->hasAttribute('selected'));

    $page->pressButton('default-edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Body');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $this->assertSession()->fieldValueEquals($filter_selector, 'cherry');

    // Submit them one at the time.
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $page->fillField($filter_selector, 'updated cherry');
    $expected_default_filters['body'] = ['key' => 'Body', 'value' => 'updated cherry'];
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters($expected_default_filters);
    $assert->pageTextContains('Set operator for Reference');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'None of')->hasAttribute('selected'));

    $filter_selector = $contextual_filter_name_prefix . '[wrapper][edit][' . $reference_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[operator]', 'or');
    $page->pressButton('Set operator');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters($expected_default_filters);
    $expected_contextual_filters['reference'] = ['key' => 'Reference', 'value' => 'Any of'];
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

    /** @var \Drupal\oe_list_pages\ListPresetFilter $body */
    $reference = $contextual_filter_values[$reference_filter_id];
    $this->assertEquals('reference', $reference->getFacetId());
    $this->assertEquals('or', $reference->getOperator());
    $this->assertEmpty($reference->getValues());
    /** @var \Drupal\oe_list_pages\ListPresetFilter $body */
    $link = $contextual_filter_values[$link_filter_id];
    $this->assertEquals('link', $link->getFacetId());
    $this->assertEquals('not', $link->getOperator());
    $this->assertEmpty($link->getValues());

    // Edit the link list and assert the values are pre-populated correctly.
    $this->clickLink('Edit');
    $this->assertContextualValueForFilters($expected_contextual_filters);
    $this->assertDefaultValueForFilters($expected_default_filters);
    $page->pressButton('contextual-edit-' . $link_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set operator for Link');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'None of')->hasAttribute('selected'));
    $page->pressButton('contextual-cancel-' . $link_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertContextualValueForFilters($expected_contextual_filters);
    $page->pressButton('contextual-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set operator for Reference');
    $this->assertTrue($this->assertSession()->optionExists('Operator', 'Any of')->hasAttribute('selected'));

    $page->pressButton('default-edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Body');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $this->assertSession()->fieldValueEquals($filter_selector, 'updated cherry');
  }

  /**
   * Asserts the contextual value is set for the filter.
   *
   * @param array $contextual_values
   *   The default filters.
   */
  protected function assertContextualValueForFilters(array $contextual_values = []): void {
    $assert = $this->assertSession();
    $assert->elementsCount('css', 'table.contextual-filters-table tr', count($contextual_values) + 1);
    foreach ($contextual_values as $filter) {
      $key = $filter['key'];
      $default_value = $filter['value'];

      $assert->elementTextContains('css', 'table.contextual-filters-table', $key);
      $assert->elementTextContains('css', 'table.contextual-filters-table', $default_value);
    }
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
