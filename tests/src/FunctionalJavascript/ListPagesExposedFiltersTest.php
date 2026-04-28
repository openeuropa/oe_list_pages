<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\oe_list_pages\ListPageWrapper;
use Drupal\Tests\oe_list_pages\Traits\ListPageTestTrait;

/**
 * Tests the List pages exposed filters.
 *
 * @group oe_list_pages
 */
class ListPagesExposedFiltersTest extends WebDriverTestBase {

  use ListPageTestTrait;

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
    'rdf_skos',
    'search_api',
    'search_api_db',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installListPageFields('content_type_one');
  }

  /**
   * Test exposed filters configuration.
   */
  public function testListPagePluginFiltersFormConfiguration(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('/node/add/oe_list_page');
    $actual_entity_types = $this->getSelectOptions('Source entity type');
    $expected_entity_types = [
      '' => '- Select -',
      'node' => 'Content',
      'taxonomy_term' => 'Taxonomy term',
    ];
    $this->assertEquals($expected_entity_types, $actual_entity_types);
    // By default, Node is selected if there are no stored values.
    $assert_session = $this->assertSession();
    $this->assertTrue($assert_session->optionExists('Source entity type', 'Content')->isSelected());

    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'content_type_one' => 'Content type one',
      'content_type_two' => 'Content type two',
      '' => '- Select -',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);
    $page = $this->getSession()->getPage();
    $this->assertTrue($assert_session->optionExists('Source bundle', 'Content type one')->isSelected());
    $page->checkField('Override default exposed filters');
    // By default, the CT exposed filters are Body and Status.
    $assert_session->checkboxChecked('Published');
    $assert_session->checkboxChecked('Body');
    $assert_session->checkboxNotChecked('Select one');
    $assert_session->checkboxNotChecked('Created');
    $page->uncheckField('Body');
    $page->checkField('Select one');
    $page->fillField('Title', 'Node title');

    $page->pressButton('Save');

    $node = $this->drupalGetNodeByTitle('Node title', TRUE);
    $wrapper = new ListPageWrapper($node);
    $actual_exposed_filters = $wrapper->getConfiguration()['exposed_filters'];
    $this->assertEquals([
      'list_facet_source_node_content_type_onestatus' => 'list_facet_source_node_content_type_onestatus',
      'select_one' => 'select_one',
    ], $actual_exposed_filters);

    $this->drupalGet($node->toUrl('edit-form'));
    $page->selectFieldOption('Source bundle', 'Content type two');
    $assert_session->assertWaitOnAjaxRequest();
    $page->checkField('Select two');
    $page->pressButton('Save');

    $node = $this->drupalGetNodeByTitle('Node title', TRUE);
    $wrapper = new ListPageWrapper($node);
    $actual_exposed_filters = $wrapper->getConfiguration()['exposed_filters'];
    $this->assertEquals([
      'list_facet_source_node_content_type_twofield_select_two' => 'list_facet_source_node_content_type_twofield_select_two',
    ], $actual_exposed_filters);

    $this->drupalGet($node->toUrl('edit-form'));
    $assert_session->checkboxChecked('Select two');
    $assert_session->checkboxNotChecked('Facet for status');

    // Unselect all the exposed filters and assert that we have overridden
    // the list page to not show any exposed filters.
    $page->uncheckField('Select two');
    $page->uncheckField('Facet for status');
    $page->pressButton('Save');

    $node = $this->drupalGetNodeByTitle('Node title', TRUE);
    $wrapper = new ListPageWrapper($node);
    $actual_exposed_filters = $wrapper->getConfiguration()['exposed_filters'];
    $actual_override_exposed_filters = $wrapper->getConfiguration()['override_exposed_filters'];
    $this->assertEquals(TRUE, $actual_override_exposed_filters);
    $this->assertEquals([], $actual_exposed_filters);

    // Add back some overridden exposed filters so that we can remove them all
    // at once.
    $this->drupalGet($node->toUrl('edit-form'));
    $page->checkField('Select two');
    $page->checkField('Facet for status');
    $page->pressButton('Save');

    $node = $this->drupalGetNodeByTitle('Node title', TRUE);
    $wrapper = new ListPageWrapper($node);
    $actual_exposed_filters = $wrapper->getConfiguration()['exposed_filters'];
    $actual_override_exposed_filters = $wrapper->getConfiguration()['override_exposed_filters'];
    $this->assertEquals(TRUE, $actual_override_exposed_filters);
    $this->assertEquals([
      'list_facet_source_node_content_type_twofield_select_two' => 'list_facet_source_node_content_type_twofield_select_two',
      'list_facet_source_node_content_type_twostatus' => 'list_facet_source_node_content_type_twostatus',
    ], $actual_exposed_filters);

    // Disable the overridden exposed filters to return back to the defaults.
    $this->drupalGet($node->toUrl('edit-form'));
    $assert_session->checkboxChecked('Override default exposed filters');
    $assert_session->checkboxChecked('Select two');
    $assert_session->checkboxChecked('Facet for status');
    // Switch to other ct and check overridden is maintained.
    $page->selectFieldOption('Source bundle', 'Content type one');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->checkboxChecked('Published');
    $assert_session->checkboxChecked('Body');
    $assert_session->checkboxNotChecked('Select one');
    $assert_session->checkboxNotChecked('Created');
    // Switch back.
    $page->selectFieldOption('Source bundle', 'Content type two');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->checkboxChecked('Select two');
    $assert_session->checkboxNotChecked('Facet for status');
    $page->uncheckField('Override default exposed filters');
    $page->pressButton('Save');

    $node = $this->drupalGetNodeByTitle('Node title', TRUE);
    $wrapper = new ListPageWrapper($node);
    $actual_exposed_filters = $wrapper->getConfiguration()['exposed_filters'];
    $actual_override_exposed_filters = $wrapper->getConfiguration()['override_exposed_filters'];
    $this->assertEquals(FALSE, $actual_override_exposed_filters);
    $this->assertEquals([], $actual_exposed_filters);
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

}
