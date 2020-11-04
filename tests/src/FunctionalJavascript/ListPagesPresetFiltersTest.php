<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
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

    // Create some test nodes to index and search in.
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

    // Field is present in oe_list_page.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $page->fillField('Title', 'List page for ct1');

    // Set preset filter for body.
    $page->selectFieldOption('Add default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Body');
    $body_filter_id = $this->getSession()->getPage()->find('css', 'input[name="preset_filters_wrapper[edit][filter_id]"]')->getValue();
    $page = $this->getSession()->getPage();
    $filter_id = 'preset_filters_wrapper[edit][' . $body_filter_id . '][body]';
    $page->fillField($filter_id, 'cherry');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'cherry']);

    // Set preset filter for created.
    $page->selectFieldOption('Add default value for', 'Created');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Created');
    $created_filter_id = $this->getSession()->getPage()->find('css', 'input[name="preset_filters_wrapper[edit][filter_id]"]')->getValue();
    $filter_id = 'preset_filters_wrapper[edit][' . $created_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_id . '[created_op]', 'After');
    $this->getSession()->getPage()->fillField($filter_id . '[created_first_date_wrapper][created_first_date][date]', '10/19/2019');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'cherry', 'Created' => 'After 19 October 2019']);

    // Set preset filter for published.
    $page->selectFieldOption('Add default value for', 'Published');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Published');
    $published_filter_id = $this->getSession()->getPage()->find('css', 'input[name="preset_filters_wrapper[edit][filter_id]"]')->getValue();
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Body' => 'cherry',
      'Created' => 'After 19 October 2019',
      'Published' => '',
    ]);

    // Set preset filter for reference.
    $page->selectFieldOption('Add default value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $reference_filter_id = $this->getSession()->getPage()->find('css', 'input[name="preset_filters_wrapper[edit][filter_id]"]')->getValue();
    $filter_id = 'preset_filters_wrapper[edit][' . $reference_filter_id . ']';
    $this->getSession()->getPage()->fillField($filter_id . '[reference_0]', 'that red animal (1)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Body' => 'cherry',
      'Created' => 'After 19 October 2019',
      'Published' => '',
      'Reference' => 'that red animal',
    ]);

    // Set additional value for reference.
    $page->selectFieldOption('Add default value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $filter_id = 'preset_filters_wrapper[edit][' . $reference_filter_id . ']';
    $this->assertSession()->fieldValueEquals($filter_id . '[reference_0]', 'that red animal (1)');
    $this->assertSession()->fieldValueEquals($filter_id . '[reference_1]', '');
    $this->getSession()->getPage()->fillField($filter_id . '[reference_1]', 'that yellow animal (2)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Body' => 'cherry',
      'Created' => 'After 19 October 2019',
      'Published' => '',
      'Reference' => 'that red animal, that yellow animal',
    ]);

    // Remove the yellow animal.
    $page->pressButton('edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $this->assertSession()->fieldValueEquals($filter_id . '[reference_0]', 'that red animal (1)');
    $this->assertSession()->fieldValueEquals($filter_id . '[reference_1]', 'that yellow animal (2)');
    $this->getSession()->getPage()->fillField($filter_id . '[reference_1]', '');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Body' => 'cherry',
      'Created' => 'After 19 October 2019',
      'Published' => '',
      'Reference' => 'that red animal',
    ]);

    // Set preset filter for select one.
    $page->selectFieldOption('Add default value for', 'Select one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Select one');

    $select_one_filter_id = $this->getSession()->getPage()->find('css', 'input[name="preset_filters_wrapper[edit][filter_id]"]')->getValue();
    $filter_id = 'preset_filters_wrapper[edit][' . $select_one_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_id . '[select_one][]', 'test2', TRUE);
    $this->getSession()->getPage()->selectFieldOption($filter_id . '[select_one][]', 'test3', TRUE);
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Body' => 'cherry',
      'Created' => 'After 19 October 2019',
      'Published' => '',
      'Reference' => 'that red animal',
      'Select one' => 'Test2, Test3',
    ]);

    // Remove preset filter for published.
    $page->pressButton('delete-' . $published_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Body' => 'cherry',
      'Created' => 'After 19 October 2019',
      'Reference' => 'that red animal',
      'Select one' => 'Test2, Test3',
    ]);
    $this->assertSession()->elementTextNotContains('css', '#list-page-default-filters table', 'Published');

    // Remove preset filter for reference.
    $page->pressButton('delete-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Body' => 'cherry',
      'Created' => 'After 19 October 2019',
      'Select one' => 'Test2, Test3',
    ]);
    $this->assertSession()->elementTextNotContains('css', '#list-page-default-filters table', 'Published');

    // Remove preset filter for select one.
    $page->pressButton('delete-' . $select_one_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Body' => 'cherry',
      'Created' => 'After 19 October 2019',
    ]);
    $this->assertSession()->elementTextNotContains('css', '#list-page-default-filters table', 'Published');

    // Edit preset filter for body.
    $page->pressButton('edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals('preset_filters_wrapper[edit][' . $body_filter_id . '][body]', 'cherry');
    $page->fillField('preset_filters_wrapper[edit][' . $body_filter_id . '][body]', 'banana');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'banana', 'Created' => 'After 19 October 2019']);

    // Edit preset filter for created.
    $page->pressButton('edit-' . $created_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Created');
    $this->assertSession()->fieldValueEquals('preset_filters_wrapper[edit][' . $created_filter_id . '][created_op]', 'gt');
    $this->assertSession()->fieldValueEquals('preset_filters_wrapper[edit][' . $created_filter_id . '][created_first_date_wrapper][created_first_date][date]', '2019-10-19');
    $this->getSession()->getPage()->selectFieldOption('preset_filters_wrapper[edit][' . $created_filter_id . '][created_op]', 'Before');
    $this->getSession()->getPage()->fillField('preset_filters_wrapper[edit][' . $created_filter_id . '][created_first_date_wrapper][created_first_date][date]', '10/31/2020');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'banana', 'Created' => 'Before 31 October 2020']);
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
    $page->selectFieldOption('Add default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains(' Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals('preset_filters_wrapper[edit][' . $body_filter_id . '][body]', 'banana');
    $page->fillField('preset_filters_wrapper[edit][' . $body_filter_id . '][body]', 'cherry');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Body' => 'cherry', 'Created' => 'Before 31 October 2020']);
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
    $this->assertDefaultValueForFilters(['Created' => 'Before 31 October 2020']);
    $page->pressButton('Save');

    $this->drupalGet($node->toUrl());
    $assert->fieldValueEquals('Body', '');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');

    // Change preset filter for date.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->selectFieldOption('Add default value for', 'Created');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains(' Set default value for Created');
    $page = $this->getSession()->getPage();
    $this->getSession()->getPage()->selectFieldOption('preset_filters_wrapper[edit][' . $created_filter_id . '][created_op]', 'Before');
    $this->getSession()->getPage()->fillField('preset_filters_wrapper[edit][' . $created_filter_id . '][created_first_date_wrapper][created_first_date][date]', '10/30/2020');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters(['Created' => 'Before 30 October 2020']);
    $page->pressButton('Save');

    $assert->fieldValueEquals('Body', '');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');

    // Set preset filter for reference for yellow animal.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->selectFieldOption('Add default value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $this->getSession()->getPage()->fillField('preset_filters_wrapper[edit][' . $reference_filter_id . '][reference_0]', 'that yellow animal (2)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Created' => 'Before 30 October 2020',
      'Reference' => 'that yellow animal',
    ]);
    $page->pressButton('Save');
    $assert->fieldValueEquals('Body', '');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');

    // Set again for red animal.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $page->pressButton('edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $this->getSession()->getPage()->fillField('preset_filters_wrapper[edit][' . $reference_filter_id . '][reference_0]', 'that red animal (1)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      'Created' => 'Before 30 October 2020',
      'Reference' => 'that red animal',
    ]);
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
    $assert->elementsCount('css', '#list-page-default-filters table tr', count($default_filters) + 1);
    foreach ($default_filters as $filter => $default_value) {
      $assert->elementTextContains('css', '#list-page-default-filters table', $filter);
      $assert->elementTextContains('css', '#list-page-default-filters table', $default_value);
    }
  }

}
