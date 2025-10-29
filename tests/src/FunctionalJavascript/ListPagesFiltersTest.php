<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\facets\Entity\Facet;
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
    'oe_list_page_content_type',
    'oe_list_pages_address',
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
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Rebuild all containers to ensure services are properly initialized after
    // enabling all required modules.
    $this->rebuildAll();
  }

  /**
   * Test fields in a list page content type.
   */
  public function testListPageFilters(): void {
    $user = $this->createUser([], NULL, TRUE);
    // Create list for content type one.
    $this->drupalLogin($user);
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();
    $this->assertTrue($assert->optionExists('Source bundle', 'Content type one')->isSelected());
    $page->checkField('Override default exposed filters');
    $page->checkField('Select one');
    $page->checkField('Published');
    $page->fillField('Title', 'List page for ct1');
    $page->pressButton('Save');
    $assert->pageTextContains('List page for ct1');

    // Create list for content type two.
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Source bundle', 'Content type two');
    $assert->assertWaitOnAjaxRequest();
    $page->fillField('Title', 'List page for ct2');
    $page->pressButton('Save');

    // Create list for content type one without exposed filters.
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $this->assertTrue($assert->optionExists('Source bundle', 'Content type one')->isSelected());
    $page->checkField('Override default exposed filters');
    $page->uncheckField('Body');
    $page->uncheckField('Published');
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
    $assert->fieldExists('Select one');
    $assert->fieldExists('Published');
    $assert->fieldNotExists('Created');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $node = $this->drupalGetNodeByTitle('List page for ct2');
    $this->drupalGet($node->toUrl());
    $assert->fieldExists('Select two');
    $assert->fieldNotExists('Published');
    $assert->fieldNotExists('Created');
    $assert->pageTextContains('that red fruit');
    $assert->pageTextNotContains('that yellow fruit');
    $node = $this->drupalGetNodeByTitle('Another List page for ct1');
    $this->drupalGet($node->toUrl());
    $assert->fieldNotExists('Select one');
    $assert->fieldNotExists('Published');
    $assert->fieldNotExists('Created');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
  }

  /**
   * Tests the selected filters.
   */
  public function testSelectedListPageFilters(): void {
    // Create a default status facet for the date field.
    $facet = Facet::create([
      'id' => 'period',
      'name' => 'Period',
    ]);

    $facet->setUrlAlias('period');
    $facet->setFieldIdentifier('created');
    $facet->setEmptyBehavior(['behavior' => 'none']);
    $facet->setFacetSourceId('list_facet_source:node:content_type_one');
    $facet->setWidget('oe_list_pages_multiselect', []);
    $facet->addProcessor([
      'processor_id' => 'url_processor_handler',
      'weights' => ['pre_query' => -10, 'build' => -10],
      'settings' => [],
    ]);
    $facet->addProcessor([
      'processor_id' => 'oe_list_pages_date_status_processor',
      'weights' => ['pre_query' => 60, 'build' => 35],
      'settings' => [
        'default_status' => 'past',
        'upcoming_label' => 'Future',
        'past_label' => 'Past',
      ],
    ]);
    $facet->save();

    // Create a facet for the country code, using a build processor that
    // transforms the values.
    $facet = Facet::create([
      'id' => 'country',
      'name' => 'Country',
    ]);

    $facet->setUrlAlias('country');
    $facet->setFieldIdentifier('field_country_code');
    $facet->setEmptyBehavior(['behavior' => 'none']);
    $facet->setFacetSourceId('list_facet_source:node:content_type_one');
    $facet->setWidget('oe_list_pages_multiselect', []);
    $facet->addProcessor([
      'processor_id' => 'url_processor_handler',
      'weights' => ['pre_query' => -10, 'build' => -10],
      'settings' => [],
    ]);
    $facet->addProcessor([
      'processor_id' => 'oe_list_pages_address_format_country_code',
      'weights' => ['pre_query' => 60, 'build' => 35],
      'settings' => [],
    ]);
    $facet->save();

    // Create some test nodes to index and search in.
    $date = new DrupalDateTime('20-10-2019');
    $values = [
      'title' => 'one yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a banana',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'field_country_code' => 'BE',
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
      'field_country_code' => 'FR',
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
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->assertTrue($assert_session->optionExists('Source bundle', 'Content type one')->isSelected());
    $page->checkField('Override default exposed filters');
    $page->checkField('Select one');
    $page->checkField('Published');
    $page->checkField('Period');
    $page->checkField('Body');
    $page->checkField('Country');
    $page->checkField('Created');
    $page->fillField('Title', 'List page for ct1');
    $page->pressButton('Save');

    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $this->drupalGet($node->toUrl());
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextContains('another yellow fruit');
    $assert_session->linkNotExistsExact('Yes');
    $assert_session->linkNotExistsExact('test1');
    $assert_session->linkNotExistsExact('test2');
    $assert_session->linkNotExistsExact('Future');
    // Past is showing up but it's not a link.
    $assert_session->linkNotExistsExact('Past');
    // We have a default status facet, configured to show Past items.
    $spans = $this->getSession()->getPage()->findAll('css', '.field--name-extra-field-oe-list-page-selected-filtersnodeoe-list-page span');
    $this->assertCount(2, $spans);
    $actual_values = [];
    foreach ($spans as $span) {
      $this->assertFalse($span->has('css', 'a'));
      $actual_values[] = $span->getText();
    }
    $this->assertEquals(['Period', 'Past'], $actual_values);

    $this->getSession()->getPage()->selectFieldOption('Published', 'Yes', TRUE);
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test1', TRUE);
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test2', TRUE);
    $this->getSession()->getPage()->pressButton('Search');
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextContains('another yellow fruit');
    $assert_session->elementExists('css', '.field--name-extra-field-oe-list-page-selected-filtersnodeoe-list-page');
    $this->assertTrue($assert_session->optionExists('Published', 'Yes')->isSelected());
    $this->assertTrue($assert_session->optionExists('Select one', 'test1')->isSelected());
    $this->assertTrue($assert_session->optionExists('Select one', 'test2')->isSelected());
    $assert_session->linkExistsExact('Yes');
    $assert_session->linkExistsExact('test1');
    $assert_session->linkExistsExact('test2');
    $assert_session->linkNotExistsExact('Future');
    // Since we have other filters now, the Past status becomes a link as it
    // can be removed.
    $assert_session->linkExistsExact('Past');

    $this->assertSelectedFiltersLabels(['Period', 'Select one', 'Published']);

    // Remove test2 from the selected filters.
    $this->getSession()->getPage()->clickLink('test2');
    $assert_session->pageTextContains('one yellow fruit');
    // The filter was removed, so we should only see the first node.
    $assert_session->pageTextNotContains('another yellow fruit');
    $assert_session->linkExistsExact('Yes');
    $assert_session->linkExistsExact('test1');
    $assert_session->linkNotExistsExact('test2');
    $assert_session->linkNotExistsExact('Future');
    $assert_session->linkExistsExact('Past');

    $this->assertSelectedFiltersLabels(['Period', 'Select one', 'Published']);

    // Remove test1 as well.
    $this->getSession()->getPage()->clickLink('test1');
    // Both nodes should now be shown as they are both published.
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextContains('another yellow fruit');
    $assert_session->linkNotExistsExact('test1');
    $assert_session->linkNotExistsExact('test2');
    $assert_session->linkNotExistsExact('Future');
    $assert_session->linkExistsExact('Past');
    $this->assertSelectedFiltersLabels(['Period', 'Published']);

    // Filter by date.
    // Node 1 was created on 20 October 2019.
    // Node 2 was created on 25 October 2019.
    $this->getSession()->getPage()->selectFieldOption('Created', 'After');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '10/19/2019');
    $this->getSession()->getPage()->pressButton('Search');
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextContains('another yellow fruit');
    $assert_session->linkExistsExact('After 19 October 2019');
    $assert_session->linkExistsExact('Past');

    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '10/22/2019');
    $this->getSession()->getPage()->pressButton('Search');
    $assert_session->pageTextNotContains('one yellow fruit');
    $assert_session->pageTextContains('another yellow fruit');
    $assert_session->linkExistsExact('After 22 October 2019');
    $assert_session->linkExistsExact('Past');
    $this->getSession()->getPage()->selectFieldOption('Created', 'Before');
    $this->getSession()->getPage()->pressButton('Search');
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextNotContains('another yellow fruit');
    $assert_session->linkExistsExact('Before 22 October 2019');
    $assert_session->linkExistsExact('Past');
    $this->getSession()->getPage()->selectFieldOption('Created', 'In between');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '10/19/2019');
    $this->getSession()->getPage()->fillField('created_second_date_wrapper[created_second_date][date]', '10/26/2019');
    $this->getSession()->getPage()->pressButton('Search');
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextContains('another yellow fruit');
    $assert_session->linkExistsExact('Between 19 October 2019 and 26 October 2019');

    // Test that the full text widget-based filter shows also the selected
    // value.
    $this->getSession()->getPage()->pressButton('Clear filters');
    $this->getSession()->getPage()->fillField('Body', 'banana');
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test1', TRUE);
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertSelectedFiltersLabels(['Period', 'Body', 'Select one']);
    $assert_session->linkExistsExact('banana');
    $assert_session->linkExistsExact('test1');
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextNotContains('another yellow fruit');
    // Remove the select field and assert we still show the link with the
    // text filter.
    $this->getSession()->getPage()->clickLink('test1');
    $this->assertSelectedFiltersLabels(['Period', 'Body']);
    $assert_session->linkExistsExact('banana');
    $assert_session->linkNotExistsExact('test1');
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextNotContains('another yellow fruit');
    // Add back the select and remove the text filter.
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test1', TRUE);
    $this->getSession()->getPage()->pressButton('Search');
    $this->assertSelectedFiltersLabels(['Period', 'Body', 'Select one']);
    $assert_session->linkExistsExact('banana');
    $assert_session->linkExistsExact('test1');
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextNotContains('another yellow fruit');
    $this->getSession()->getPage()->clickLink('banana');
    $this->assertSelectedFiltersLabels(['Period', 'Select one']);
    $assert_session->linkNotExistsExact('banana');
    $assert_session->linkExistsExact('test1');
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextNotContains('another yellow fruit');

    // Test the period filter with a default status.
    $this->getSession()->getPage()->pressButton('Clear filters');
    $this->getSession()->getPage()->selectFieldOption('Published', 'Yes', TRUE);
    $this->getSession()->getPage()->pressButton('Search');
    $assert_session->linkNotExistsExact('Future');
    $assert_session->linkExistsExact('Past');
    $this->getSession()->getPage()->selectFieldOption('Period', 'Future', TRUE);
    $this->getSession()->getPage()->selectFieldOption('Period', 'Past', TRUE);
    $this->getSession()->getPage()->pressButton('Search');
    // Now both default status values are links.
    $assert_session->linkExistsExact('Future');
    $assert_session->linkExistsExact('Past');
    // Remove Past and assert that Future remains a link because it is not
    // configured as the default status.
    $this->clickLink('Past');
    $assert_session->linkExistsExact('Future');
    // The Past link is gone, and also as a simple string.
    $assert_session->linkNotExistsExact('Past');
    $spans = $this->getSession()->getPage()->findAll('css', '.field--name-extra-field-oe-list-page-selected-filtersnodeoe-list-page span');
    $this->assertCount(2, $spans);
    $this->assertEquals('Period', $spans[0]->getText());
    $this->assertEquals('Published', $spans[1]->getText());

    // Test that even if we have no results, all the selected filters show up.
    $this->drupalGet($node->toUrl());
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test1', TRUE);
    $this->getSession()->getPage()->fillField('Body', 'no results');
    $this->getSession()->getPage()->pressButton('Search');
    $assert_session->pageTextNotContains('one yellow fruit');
    $assert_session->pageTextNotContains('another yellow fruit');
    $this->assertSelectedFiltersLabels(['Period', 'Body', 'Select one']);

    // Test that processors are used in building the selected filters.
    $this->drupalGet($node->toUrl());
    $this->getSession()->getPage()->selectFieldOption('Country', 'Belgium');
    $this->getSession()->getPage()->pressButton('Search');
    $assert_session->pageTextContains('one yellow fruit');
    $assert_session->pageTextNotContains('another yellow fruit');
    $assert_session->linkNotExistsExact('BE');
    $assert_session->linkExistsExact('Belgium');
    $this->assertSelectedFiltersLabels(['Period', 'Country']);

    // Test that even without results, the processors are used for the selected
    // filters.
    $this->getSession()->getPage()->fillField('Body', 'no results');
    $this->getSession()->getPage()->pressButton('Search');
    $assert_session->pageTextNotContains('one yellow fruit');
    $assert_session->pageTextNotContains('another yellow fruit');
    $assert_session->linkNotExistsExact('BE');
    $assert_session->linkExistsExact('Belgium');
    $this->assertSelectedFiltersLabels(['Period', 'Body', 'Country']);
  }

  /**
   * Testing the pager information regarding the current query.
   */
  public function testListPagePagerInfo(): void {
    // Create some test nodes to index and search in.
    $date = new DrupalDateTime('20-10-2019');
    for ($i = 1; $i <= 23; $i++) {
      $date->modify('+1 day');
      $values = [
        'title' => 'Node title ' . $i,
        'type' => 'content_type_one',
        'body' => 'Node body ' . $i,
        'status' => NodeInterface::PUBLISHED,
        'created' => $date->getTimestamp(),
      ];
      $node = Node::create($values);
      $node->save();
    }

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    $index->indexItems();

    // Create the list page.
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $this->assertTrue($assert_session->optionExists('Source bundle', 'Content type one')->isSelected());
    $page->fillField('Title', 'List page for ct1');
    $page->pressButton('Save');

    // By default we show only 10 results.
    $this->assertCount(10, $this->getSession()->getPage()->findAll('css', '.node--type-content-type-one'));

    $assert_session->pageTextContains('Showing results 1 to 10');
    $this->getSession()->getPage()->clickLink('Next');
    $assert_session->pageTextContains('Showing results 10 to 20');
    $this->getSession()->getPage()->clickLink('Next');
    $assert_session->pageTextContains('Showing results 20 to 23');

    // Update the node to show 20 results.
    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $this->getSession()->getPage()->selectFieldOption('The number of items to show per page', '20');
    $page->pressButton('Save');
    $this->assertCount(20, $this->getSession()->getPage()->findAll('css', '.node--type-content-type-one'));
    $assert_session->pageTextContains('Showing results 1 to 20');
    $this->getSession()->getPage()->clickLink('Next');
    $assert_session->pageTextContains('Showing results 20 to 23');

    // Search to trigger no results.
    $this->getSession()->getPage()->fillField('Body', 'not going to find anything');
    $this->getSession()->getPage()->pressButton('Search');
    $assert_session->pageTextContains('No results have been found');
    $assert_session->pageTextNotContains('Showing results');
  }

  /**
   * Asserts the selected filters labels from the page.
   *
   * @param array $expected_labels
   *   The expected labels.
   */
  protected function assertSelectedFiltersLabels(array $expected_labels): void {
    $spans = $this->getSession()->getPage()->findAll('css', '.field--name-extra-field-oe-list-page-selected-filtersnodeoe-list-page span');
    $this->assertCount(count($expected_labels), $spans);
    $labels = [];
    foreach ($spans as $span) {
      $labels[] = $span->getText();
    }

    $this->assertEquals($expected_labels, $labels);
  }

}
