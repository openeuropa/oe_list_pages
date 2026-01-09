<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages_link_list_source\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Tests\oe_list_pages\FunctionalJavascript\ListPagePluginFormTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;

/**
 * Tests the list pages with link list displays.
 */
class ListPageLinkDisplayPluginTest extends ListPagePluginFormTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'oe_link_lists_test',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages_filters_test',
    'oe_list_pages_link_list_displays',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
    'block',
    'oe_list_pages_event_subscriber_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * Tests the link list displays on list pages.
   */
  public function testListPageLinkListDisplayForm(): void {
    // Create some test nodes to index.
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

    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();

    $page = $this->getSession()->getPage();
    $page->fillField('Title', 'List page for ct1');
    $assert_session = $this->assertSession();
    $this->assertTrue($assert_session->optionExists('Source bundle', 'Content type one')->isSelected());
    $display = $assert_session->selectExists('Display');
    $this->assertEquals('required', $display->getAttribute('required'));
    $this->assertFieldSelectOptions('Display', [
      'same_configuration_display_one',
      'same_configuration_display_two',
      'test_configurable_title',
      'test_link_tag',
      'test_markup',
      'test_translatable_form',
      'test_no_bundle_restriction_display',
      'title',
    ]);

    // Pick a display with no configuration.
    $page->selectFieldOption('Display', 'Title');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('This plugin does not have any configuration options.');
    $page->pressButton('Save');

    // Assert we see the results as just titles.
    $expected = [
      'that red fruit',
      'that yellow fruit',
    ];
    $actual = [];
    $links = $page->findAll('css', '.field--name-extra-field-oe-list-page-resultsnodeoe-list-page ul li a');
    foreach ($links as $link) {
      $actual[] = $link->getHtml();
    }
    $this->assertEquals($expected, $actual);

    // Edit the node and change the display.
    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $this->assertEquals('title', $page->findField('Display')->find('css', 'option[selected="selected"]')->getValue());
    $page->selectFieldOption('Display', 'Titles with optional link');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextNotContains('This plugin does not have any configuration options.');
    // By default, the Link checkbox is checked.
    $checkbox = $page->find('css', '.form-item-emr-plugins-oe-list-page-wrapper-display-plugin-configuration-wrapper-test-configurable-title-link input');
    $this->assertTrue($checkbox->isChecked());
    // Switch again to another plugin for testing.
    $page->selectFieldOption('Display', 'Same configuration display one.');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldExists('The value');
    // Switch back and save.
    $page->selectFieldOption('Display', 'Titles with optional link');
    $assert_session->assertWaitOnAjaxRequest();
    // Fill in the configuration field to trigger the validation.
    $page->fillField('No validate', 'Test validation');
    $page->pressButton('Save');
    $this->assertSession()->elementTextContains('css', '.messages--error', 'The no_validate value cannot be filled in.');
    $page->fillField('No validate', '');
    $page->pressButton('Save');
    $this->assertSession()->elementNotExists('css', '.messages--error');

    // We should see the titles in the same way: linked.
    $links = $page->findAll('css', '.field--name-extra-field-oe-list-page-resultsnodeoe-list-page ul li a');
    $actual = [];
    foreach ($links as $link) {
      $actual[] = $link->getHtml();
    }
    $this->assertEquals($expected, $actual);
    // Edit again and configure to not link the titles.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $checkbox->uncheck();
    $page->pressButton('Save');
    // Now the titles are without links.
    $links = $page->findAll('css', '.field--name-extra-field-oe-list-page-resultsnodeoe-list-page ul li');
    $actual = [];
    foreach ($links as $link) {
      $this->assertNull($link->find('css', 'a'));
      $actual[] = $link->getHtml();
    }
    $this->assertEquals($expected, $actual);
  }

  /**
   * Tests selecting the sort when creating a list page using a display.
   *
   * We retest here because we take over the ListBuilder which applies the
   * sort.
   */
  public function testBackendSortWithDisplayPlugins(): void {
    // Create some nodes to test the sorting.
    $date = new DrupalDateTime('20-10-2020');
    $date->modify('- 1 hour');
    $values = [
      'title' => 'Second by created',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $this->drupalCreateNode($values);

    $date->modify('+ 2 hours');
    $values = [
      'title' => 'First by created',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $this->drupalCreateNode($values);

    $date->modify('- 3 hours');
    $values = [
      'title' => 'Third by created',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $this->drupalCreateNode($values);

    $date->modify('- 1 hour');
    $values = [
      'title' => 'Fourth by created',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $this->drupalCreateNode($values);

    $date = new DrupalDateTime('20-10-2019');
    $values = [
      'title' => 'First by boolean field',
      'type' => 'content_type_one',
      'status' => NodeInterface::PUBLISHED,
      'field_test_boolean' => 1,
      'created' => $date->getTimestamp(),
    ];
    $this->drupalCreateNode($values);

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    // Index the nodes.
    $index->indexItems();

    // Subscribe to the event and provide another sort option for the content
    // type.
    \Drupal::state()->set('oe_list_pages_test.alter_sort_options', TRUE);

    // Log in and create a list page.
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();

    // Select node.
    $assert_session = $this->assertSession();
    $this->assertTrue($assert_session->optionExists('Source entity type', 'node')->isSelected());
    $this->assertTrue($assert_session->optionExists('Source bundle', 'content_type_one')->isSelected());
    $assert_session->selectExists('Sort');
    $this->assertTrue($assert_session->optionExists('Sort', 'Default')->isSelected());
    $this->getSession()->getPage()->selectFieldOption('Display', 'Title');
    $assert_session->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('Title', 'Node title');
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('List page Node title has been created.');
    // The sorting is by the default sort.
    $this->assertResultsAreInCorrectOrder([
      'First by created',
      'Second by created',
      'Third by created',
      'Fourth by created',
      'First by boolean field',
    ]);

    // Edit again the node and save a different sort.
    $node = $this->drupalGetNodeByTitle('Node title');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $this->getSession()->getPage()->selectFieldOption('Sort', 'field_test_boolean__DESC');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertResultsAreInCorrectOrder([
      'First by boolean field',
      'First by created',
      'Second by created',
      'Third by created',
      'Fourth by created',
    ]);
  }

  /**
   * Test the pager is rendered when using display plugins.
   */
  public function testPagerOnListPageWithDisplays(): void {
    $date = new DrupalDateTime('20-10-2020');
    $expected_order = [];
    for ($x = 1; $x <= 12; $x++) {
      $values = [
        'title' => 'Node title ' . $x,
        'type' => 'content_type_one',
        'status' => NodeInterface::PUBLISHED,
        'created' => $date->getTimestamp(),
      ];
      $this->drupalCreateNode($values);
      $date->modify('-1 day');
      $expected_order[] = 'Node title ' . $x;
    }

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    // Index the nodes.
    $index->indexItems();

    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();

    // Select node.
    $assert_session = $this->assertSession();
    $this->assertTrue($assert_session->optionExists('Source entity type', 'node')->isSelected());
    $this->assertTrue($assert_session->optionExists('Source bundle', 'content_type_one')->isSelected());
    $this->getSession()->getPage()->selectFieldOption('Display', 'Title');
    $assert_session->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField('Title', 'Node title');
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('List page Node title has been created.');

    // The sorting is by the default sort.
    $first_ten = array_splice($expected_order, 0, 10);
    $this->assertResultsAreInCorrectOrder($first_ten);
    $this->clickLink('Next');
    $this->assertResultsAreInCorrectOrder([
      'Node title 11',
      'Node title 12',
    ]);
  }

  /**
   * Checks if a select element contains the specified options.
   *
   * @param string $name
   *   The field name.
   * @param array $expected_options
   *   An array of expected options.
   */
  protected function assertFieldSelectOptions(string $name, array $expected_options): void {
    $select = $this->getSession()->getPage()->find('named', [
      'select',
      $name,
    ]);

    if (!$select) {
      $this->fail('Unable to find select ' . $name);
    }

    $options = $select->findAll('css', 'option');
    array_walk($options, function (NodeElement &$option) {
      $option = $option->getValue();
    });
    $options = array_filter($options);
    sort($options);
    sort($expected_options);
    $this->assertSame($expected_options, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function goToListPageConfiguration(): void {
    $this->drupalGet('node/add/oe_list_page');
    $this->clickLink('List Page');
  }

  /**
   * Asserts that the list page result titles are in the correct order.
   *
   * @param array $expected_title_order
   *   The expected order of the titles.
   */
  protected function assertResultsAreInCorrectOrder(array $expected_title_order): void {
    $actual_title_order = [];
    foreach ($this->getSession()->getPage()->findAll('css', '.field--name-extra-field-oe-list-page-resultsnodeoe-list-page .item-list ul li a') as $element) {
      $actual_title_order[] = $element->getText();
    }

    $this->assertEquals($expected_title_order, $actual_title_order);
  }

}
