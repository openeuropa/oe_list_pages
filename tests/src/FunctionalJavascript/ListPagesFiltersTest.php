<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;

/**
 * Tests the list page filters.
 *
 * @group oe_list_pages
 */
class ListPagesFiltersTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'options',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages',
    'oe_list_pages_filters_test',
    'node',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
  ];

  /**
   * Test fields in a list page content type.
   */
  public function testListPageFilters(): void {
    // Create list for content type one.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/node/add/content_type_list');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Source bundle', 'Content type one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->checkField('Override default exposed filters');
    $page->checkField('Select one');
    $page->checkField('Published');
    $page->fillField('Title', 'List page for ct1');
    $page->pressButton('Save');

    // Create list for content type two.
    $this->drupalGet('/node/add/content_type_list');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Source bundle', 'Content type two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->fillField('Title', 'List page for ct2');
    $page->pressButton('Save');

    // Create list for content type one without exposed filters.
    $this->drupalGet('/node/add/content_type_list');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Source bundle', 'Content type one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->checkField('Override default exposed filters');
    $page->fillField('Title', 'Another List page for ct1');
    $page->pressButton('Save');

    // Create some test nodes to index and search in.
    $date = new DrupalDateTime('20-10-2020');
    $values = [
      'title' => 'that yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a banana',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    $values = [
      'title' => 'that red fruit',
      'type' => 'content_type_two',
      'body' => 'this is a cherry',
      'field_select_two' => 'test2',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    // Index the nodes.
    $index->indexItems();

    // Check fields are visible in list nodes.
    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $this->drupalGet($node->toUrl());
    $this->assertSession()->fieldExists('Select one');
    $this->assertSession()->fieldExists('Published');
    $this->assertSession()->fieldNotExists('Created');
    $assert = $this->assertSession();
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $node = $this->drupalGetNodeByTitle('List page for ct2');
    $this->drupalGet($node->toUrl());
    $this->assertSession()->fieldExists('Select two');
    $this->assertSession()->fieldNotExists('Published');
    $this->assertSession()->fieldNotExists('Created');
    $assert->pageTextContains('that red fruit');
    $assert->pageTextNotContains('that yellow fruit');
    $node = $this->drupalGetNodeByTitle('Another List page for ct1');
    $this->drupalGet($node->toUrl());
    $this->assertSession()->fieldNotExists('Select one');
    $this->assertSession()->fieldNotExists('Published');
    $this->assertSession()->fieldNotExists('Created');
    $assert = $this->assertSession();
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');

  }

  /**
   * Tests the selected filters.
   */
  public function testSelectedListPageFilters(): void {
    // Create some test nodes to index and search in.
    $date = new DrupalDateTime('20-10-2020');
    $values = [
      'title' => 'one yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a banana',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
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
      'created' => $date->modify('+ 5 days')->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    $index->indexItems();

    // Create the list page that uses all the available facets.
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('/node/add/content_type_list');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Source bundle', 'Content type one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->checkField('Override default exposed filters');
    $page->checkField('Select one');
    $page->checkField('Published');
    $page->checkField('Created');
    $page->fillField('Title', 'List page for ct1');
    $page->pressButton('Save');

    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('one yellow fruit');
    $this->assertSession()->pageTextContains('another yellow fruit');
    $this->assertSession()->elementNotExists('css', '.field--name-extra-field-oe-list-page-selected-filtersnodecontent-type-list a');
    $this->assertSession()->linkNotExistsExact('Yes');
    $this->assertSession()->linkNotExistsExact('test1');
    $this->assertSession()->linkNotExistsExact('test2');

    $this->getSession()->getPage()->selectFieldOption('Published', 'Yes', TRUE);
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test1', TRUE);
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test2', TRUE);
    $this->getSession()->getPage()->pressButton('Search');

    $this->assertSession()->pageTextContains('one yellow fruit');
    $this->assertSession()->pageTextContains('another yellow fruit');
    $this->assertSession()->linkExistsExact('Yes');
    $this->assertSession()->linkExistsExact('test1');
    $this->assertSession()->linkExistsExact('test2');

    $this->assertSelectedFiltersLabels(['Published', 'Select one']);

    // Remove test2 from the selected filters.
    $this->getSession()->getPage()->clickLink('test2');
    $this->assertSession()->pageTextContains('one yellow fruit');
    // The filter was removed, so we should only see the first node.
    $this->assertSession()->pageTextNotContains('another yellow fruit');
    $this->assertSession()->linkExistsExact('Yes');
    $this->assertSession()->linkExistsExact('test1');
    $this->assertSession()->linkNotExistsExact('test2');

    $this->assertSelectedFiltersLabels(['Published', 'Select one']);

    // Remove test1 as well.
    $this->getSession()->getPage()->clickLink('test1');
    // Both nodes should now be shown as they are both published.
    $this->assertSession()->pageTextContains('one yellow fruit');
    $this->assertSession()->pageTextContains('another yellow fruit');
    $this->assertSession()->linkNotExistsExact('test1');
    $this->assertSession()->linkNotExistsExact('test2');
    $this->assertSelectedFiltersLabels(['Published']);

    // Filter by date.
    // Node 1 was created on 20 October 2020.
    // Node 2 was created on 25 October 2020.
    $this->getSession()->getPage()->selectFieldOption('Created', 'After');
    $this->getSession()->getPage()->fillField('created_first_date[date]', '10/19/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertSession()->pageTextContains('one yellow fruit');
    $this->assertSession()->pageTextContains('another yellow fruit');
    $this->assertSession()->linkExistsExact('After 19 October 2020');

    $this->getSession()->getPage()->fillField('created_first_date[date]', '10/22/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertSession()->pageTextNotContains('one yellow fruit');
    $this->assertSession()->pageTextContains('another yellow fruit');
    $this->assertSession()->linkExistsExact('After 22 October 2020');
    $this->getSession()->getPage()->selectFieldOption('Created', 'Before');
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertSession()->pageTextContains('one yellow fruit');
    $this->assertSession()->pageTextNotContains('another yellow fruit');
    $this->assertSession()->linkExistsExact('Before 22 October 2020');
    $this->getSession()->getPage()->selectFieldOption('Created', 'In between');
    $this->getSession()->getPage()->fillField('created_first_date[date]', '10/19/2020');
    $this->getSession()->getPage()->fillField('created_second_date[date]', '10/26/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertSession()->pageTextContains('one yellow fruit');
    $this->assertSession()->pageTextContains('another yellow fruit');
    $this->assertSession()->linkExistsExact('Between 19 October 2020 and 26 October 2020');

    // @todo add test for the default status by adding a new date filter.
  }

  /**
   * Asserts the selected filters labels from the page.
   *
   * @param array $expected_labels
   *   The expected labels.
   */
  protected function assertSelectedFiltersLabels(array $expected_labels): void {
    $spans = $this->getSession()->getPage()->findAll('css', '.field--name-extra-field-oe-list-page-selected-filtersnodecontent-type-list span');
    $this->assertCount(count($expected_labels), $spans);
    $labels = [];
    foreach ($spans as $span) {
      $labels[] = $span->getText();
    }

    $this->assertEquals($expected_labels, $labels);
  }

}
