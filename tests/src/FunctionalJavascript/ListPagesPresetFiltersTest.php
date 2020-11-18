<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\oe_list_pages\ListPresetFiltersBuilder;
use Drupal\search_api\Entity\Index;

/**
 * Tests the list page preset filters.
 *
 * @group oe_list_pages
 */
class ListPagesPresetFiltersTest extends WebDriverTestBase {

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
    'node',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
    'multivalue_form_element',
  ];

  /**
   * Test presence of preset filters configuration in the plugin.
   */
  public function testListPageFiltersPresenceInContentForm(): void {
    // Default filters configuration is present in oe_list_page content type.
    $this->drupalLogin($this->rootUser);
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
   * Test list page preset filters configuration.
   */
  public function testListPagePresetFilters(): void {
    // Create some test nodes of content type Two.
    $date = new DrupalDateTime('30-10-2020');
    $values = [
      'title' => 'that red animal',
      'type' => 'content_type_two',
      'body' => 'this is a fish',
      'field_select_one' => 'test2',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    $values = [
      'title' => 'that yellow animal',
      'type' => 'content_type_two',
      'body' => 'this is a giraffe',
      'field_select_one' => 'test2',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    // Create some test nodes of content type One.
    $date = new DrupalDateTime('20-10-2020');
    $values = [
      'title' => 'that yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a banana',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'field_reference' => $node->id(),
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    $date = new DrupalDateTime('30-10-2020');
    $values = [
      'title' => 'that red fruit',
      'type' => 'content_type_one',
      'body' => 'this is a cherry',
      'field_select_one' => 'test2',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    // Index the nodes.
    $index->indexItems();

    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Do not fill in the title and assert the validation limiting works.
    $page->selectFieldOption('Add default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Body field is required.');
    $assert->pageTextNotContains('Title field is required.');

    // Cancel and start over.
    $page->pressButton('Cancel');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextNotContains('Title field is required.');
    $assert->pageTextNotContains('Body field is required.');
    $page->fillField('Title', 'List page for ct1');

    // Set preset filter for Body and cancel.
    $page->selectFieldOption('Add default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $assert->pageTextContains('Set default value for Body');
    $body_filter_id = ListPresetFiltersBuilder::generateFilterId('body');
    $page = $this->getSession()->getPage();
    $filter_selector = 'emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $body_filter_id . '][body]';
    $page->fillField($filter_selector, 'cherry');
    $page->pressButton('Cancel');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextNotContains('Title field is required.');
    $assert->pageTextNotContains('Body field is required.');
    $this->assertDefaultValueForFilters(['' => t('No default values set')]);

    // Set preset filter for Body and save.
    $page->selectFieldOption('Add default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Body');
    $body_filter_id = ListPresetFiltersBuilder::generateFilterId('body');
    $page = $this->getSession()->getPage();
    $filter_selector = 'emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $body_filter_id . '][body]';
    $page->fillField($filter_selector, 'cherry');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      'Body' => 'cherry',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Set preset filter for Created.
    $page->selectFieldOption('Add default value for', 'Created');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Created');
    $created_filter_id = ListPresetFiltersBuilder::generateFilterId('created');
    $filter_selector = 'emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $created_filter_id . ']';
    // Assert validations.
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Created field is required.');
    $assert->pageTextContains('The Date date is required. Please enter a date in the format');
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[created_op]', 'In between');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('The Date date is required. Please enter a date in the format');
    $assert->pageTextContains('The second date is required.');
    $this->getSession()->getPage()->fillField($filter_selector . '[created_first_date_wrapper][created_first_date][date]', '10/19/2019');
    $this->getSession()->getPage()->fillField($filter_selector . '[created_second_date_wrapper][created_second_date][date]', '10/17/2019');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('The second date cannot be before the first date.');

    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[created_op]', 'After');
    $this->getSession()->getPage()->fillField($filter_selector . '[created_first_date_wrapper][created_first_date][date]', '10/19/2019');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Created'] = 'After 19 October 2019';
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Set preset filter for Published.
    $page->selectFieldOption('Add default value for', 'Published');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Published');
    $published_filter_id = ListPresetFiltersBuilder::generateFilterId('list_facet_source_node_content_type_onestatus');
    $filter_selector = 'emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $published_filter_id . ']';

    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[list_facet_source_node_content_type_onestatus][0][boolean]', '1', TRUE);
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Published'] = 'Yes';
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Set preset filter for Reference.
    $page->selectFieldOption('Add default value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $reference_filter_id = ListPresetFiltersBuilder::generateFilterId('reference');
    $filter_selector = 'emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $reference_filter_id . ']';
    $this->getSession()->getPage()->fillField($filter_selector . '[reference][0][entity]', 'that red animal (1)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Reference'] = 'that red animal';
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Set additional value for reference.
    $page->pressButton('edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $filter_selector = 'emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $reference_filter_id . ']';
    $this->assertSession()->fieldValueEquals($filter_selector . '[reference][0][entity]', 'that red animal (1)');
    $this->getSession()->getPage()->fillField($filter_selector . '[reference][1][entity]', 'that yellow animal (2)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Reference'] = 'that red animal, that yellow animal';
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Remove the yellow animal.
    $page->pressButton('edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $this->assertSession()->fieldValueEquals($filter_selector . '[reference][0][entity]', 'that red animal (1)');
    $this->assertSession()->fieldValueEquals($filter_selector . '[reference][1][entity]', 'that yellow animal (2)');
    $this->getSession()->getPage()->fillField($filter_selector . '[reference][1][entity]', '');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Reference'] = 'that red animal';
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Set preset filter for Select one.
    $page->selectFieldOption('Add default value for', 'Select one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Select one');

    $select_one_filter_id = ListPresetFiltersBuilder::generateFilterId('select_one');
    $filter_selector = 'emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $select_one_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[select_one][0][list]', 'test2');
    $page->pressButton('Add another item');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[select_one][1][list]', 'test3');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Select one'] = 'Test2, Test3';
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Remove preset filter for Published.
    $this->assertSession()->elementTextContains('css', 'table.default-filter-values-table', 'Published');
    $page->pressButton('delete-' . $published_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    unset($expected_set_filters['Published']);
    $this->assertDefaultValueForFilters($expected_set_filters);
    $this->assertSession()->elementTextNotContains('css', 'table.default-filter-values-table', 'Published');

    // Remove preset filter for Reference.
    $this->assertSession()->elementTextContains('css', 'table.default-filter-values-table', 'Reference');
    $page->pressButton('delete-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    unset($expected_set_filters['Reference']);
    $this->assertDefaultValueForFilters($expected_set_filters);
    $this->assertSession()->elementTextNotContains('css', 'table.default-filter-values-table', 'Reference');

    // Remove preset filter for select one.
    $this->assertSession()->elementTextContains('css', 'table.default-filter-values-table', 'Select one');
    $page->pressButton('delete-' . $select_one_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    unset($expected_set_filters['Select one']);
    $this->assertDefaultValueForFilters($expected_set_filters);
    $this->assertSession()->elementTextNotContains('css', 'table.default-filter-values-table', 'Select one');

    // Edit preset filter for Body and cancel.
    $page->pressButton('edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $body_filter_id . '][body]', 'cherry');
    $page->fillField('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $body_filter_id . '][body]', 'banana');
    $page->pressButton('Cancel');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Nothing was changed, we just pressed the cancel button.
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Edit preset filter for Body.
    $page->pressButton('edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $body_filter_id . '][body]', 'cherry');
    $page->fillField('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $body_filter_id . '][body]', 'banana');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Body'] = 'banana';
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Edit preset filter for Created.
    $page->pressButton('edit-' . $created_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Created');
    $this->assertSession()->fieldValueEquals('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $created_filter_id . '][created_op]', 'gt');
    $this->assertSession()->fieldValueEquals('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $created_filter_id . '][created_first_date_wrapper][created_first_date][date]', '2019-10-19');
    $this->getSession()->getPage()->selectFieldOption('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $created_filter_id . '][created_op]', 'Before');
    $this->getSession()->getPage()->fillField('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $created_filter_id . '][created_first_date_wrapper][created_first_date][date]', '10/31/2020');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Created'] = 'Before 31 October 2020';
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Save.
    $page->pressButton('Save');
    // Check results.
    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $this->drupalGet($node->toUrl());
    $assert->pageTextNotContains('that red fruit');
    $assert->pageTextContains('that yellow fruit');

    // Edit again, change preset filter, expose filter and save.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->pressButton('edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains(' Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $body_filter_id . '][body]', 'banana');
    $page->fillField('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $body_filter_id . '][body]', 'cherry');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Body'] = 'cherry';
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->checkField('Override default exposed filters');
    $page->checkField('Body');
    $page->checkField('Created');
    $page->pressButton('Save');
    $this->drupalGet($node->toUrl());
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
    $assert->fieldValueEquals('Body', 'cherry');
    $assert->fieldValueEquals('Created', 'lt');

    // Change directly the value in exposed form.
    $page->fillField('Body', 'banana');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $assert->fieldValueEquals('Body', 'banana');

    // Change also the created date.
    $this->getSession()->getPage()->selectFieldOption('Created', 'After');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '10/30/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->fieldValueEquals('Body', 'banana');
    $assert->fieldValueEquals('Created', 'gt');
    $assert->fieldValueEquals('created_first_date_wrapper[created_first_date][date]', '2020-10-30');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');

    // Edit again to remove filters and save.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->pressButton('delete-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    unset($expected_set_filters['Body']);
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');

    $this->drupalGet($node->toUrl());
    $assert->fieldValueEquals('Body', '');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');

    // Change preset filter for Created.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->pressButton('edit-' . $created_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Created');
    $page = $this->getSession()->getPage();
    $this->getSession()->getPage()->selectFieldOption('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $created_filter_id . '][created_op]', 'Before');
    $this->getSession()->getPage()->fillField('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $created_filter_id . '][created_first_date_wrapper][created_first_date][date]', '10/30/2020');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Created'] = 'Before 30 October 2020';
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');

    $assert->fieldValueEquals('Body', '');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');

    // Set preset for Reference for yellow animal.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->selectFieldOption('Add default value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();

    $assert->pageTextContains('Set default value for Reference');
    $this->getSession()->getPage()->fillField('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $reference_filter_id . '][reference][0][entity]', 'that yellow animal (2)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Reference'] = 'that yellow animal';
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');
    $assert->fieldValueEquals('Body', '');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');

    // Change the preset of Reference to the red animal.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->pressButton('edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $this->getSession()->getPage()->fillField('emr_plugins_oe_list_page[wrapper][default_filter_values][wrapper][edit][' . $reference_filter_id . '][reference][0][entity]', 'that red animal (1)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['Reference'] = 'that red animal';
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');
    $assert->fieldValueEquals('Body', '');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
  }

  /**
   * Asserts the default value is set for the filter.
   *
   * @param array $default_filters
   *   The default filters.
   */
  protected function assertDefaultValueForFilters(array $default_filters = []): void {
    $assert = $this->assertSession();
    $assert->elementsCount('css', 'table.default-filter-values-table tr', count($default_filters) + 1);
    foreach ($default_filters as $filter => $default_value) {
      $assert->elementTextContains('css', 'table.default-filter-values-table', $filter);
      $assert->elementTextContains('css', 'table.default-filter-values-table', $default_value);
    }
  }

}
