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

}
