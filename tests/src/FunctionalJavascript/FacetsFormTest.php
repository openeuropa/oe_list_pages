<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\query_type\DateStatus;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\oe_list_pages\Traits\FacetsTestTrait;

/**
 * Tests the list facets form.
 */
class FacetsFormTest extends WebDriverTestBase {

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
    'node',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
    'datetime',
  ];

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    /** @var \Drupal\emr\EntityMetaRelationInstaller $installer */
    $installer = \Drupal::service('emr.installer');
    $installer->installEntityMetaTypeOnContentEntityType('oe_list_page', 'node', [
      'content_type_one',
    ]);

    $this->state = \Drupal::service('state');

    // Create some test nodes to index and search in.
    $date = new DrupalDateTime('20-10-2020');
    $values = [
      'title' => 'that yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a banana',
      'field_select_one' => 'test1',
      'created' => $date->getTimestamp(),
    ];

    $node = Node::create($values);
    $node->save();

    $date = new DrupalDateTime('20-11-2020');
    $values = [
      'title' => 'that red fruit',
      'type' => 'content_type_one',
      'body' => 'this is a cherry',
      'field_select_one' => 'test2',
      'created' => $date->getTimestamp(),
    ];

    $node = Node::create($values);
    $node->save();

    // Index the nodes.
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    $index->indexItems();
  }

  /**
   * Tests the facets list form.
   */
  public function testFacetsForm(): void {
    // Navigate to the form page.
    $this->drupalGet('/facets-form-test');
    $this->assertSession()->pageTextContains('Facets form test');

    $assert = $this->assertSession();
    $assert->fieldExists('Body');
    $this->assertDefaultFormStatus();

    // Filter by body to only find 1 result.
    $this->getSession()->getPage()->fillField('Body', 'Banana');
    $this->getSession()->getPage()->pressButton('Search');

    // Assert results and form changes.
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $assert->fieldValueEquals('Body', 'Banana');
    $this->assertEquals([
      'test1',
    ], array_values($this->getSelectOptions('Select one')));

    // Check that the text-based facet ignores the case.
    $this->getSession()->getPage()->fillField('Body', 'baNAna');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $assert->fieldValueEquals('Body', 'baNAna');

    // Reset the form.
    $this->getSession()->getPage()->pressButton('Clear filters');
    $this->assertDefaultFormStatus();

    // Filter by body and multiselect.
    $this->getSession()->getPage()->fillField('Body', 'cherry');
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test1');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $this->getSession()->getPage()->pressButton('Clear filters');
    $this->assertDefaultFormStatus();
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test2');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');

    // Filter by date.
    $this->getSession()->getPage()->pressButton('Clear filters');
    $this->getSession()->getPage()->selectFieldOption('created_op', 'gt');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '10/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '10/15/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
    $this->getSession()->getPage()->selectFieldOption('created_op', 'lt');
    $this->assertFalse($this->getSession()->getPage()->findField('created_second_date_wrapper[created_second_date][date]')->isVisible());
    $this->assertTrue($this->getSession()->getPage()->findField('created_first_date_wrapper[created_first_date][date]')->isVisible());
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '10/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '11/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
    $this->getSession()->getPage()->selectFieldOption('created_op', 'bt');
    $this->assertTrue($this->getSession()->getPage()->findField('created_second_date_wrapper[created_second_date][date]')->isVisible());
    $this->assertTrue($this->getSession()->getPage()->findField('created_first_date_wrapper[created_first_date][date]')->isVisible());
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '10/15/2020');
    $this->getSession()->getPage()->fillField('created_second_date_wrapper[created_second_date][date]', '10/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '11/15/2020');
    $this->getSession()->getPage()->fillField('created_second_date_wrapper[created_second_date][date]', '11/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');

    // Filter by date and body.
    $this->getSession()->getPage()->fillField('Body', 'cherry');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
    $this->getSession()->getPage()->fillField('Body', 'banana');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $this->getSession()->getPage()->fillField('created_first_date_wrapper[created_first_date][date]', '10/15/2020');
    $this->getSession()->getPage()->fillField('created_second_date_wrapper[created_second_date][date]', '10/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
  }

  /**
   * Tests the DateStatus facet processor and the widget default values.
   */
  public function testDateStatusFacet(): void {
    $date = new DrupalDateTime('now');
    $date->modify('-1 month');
    $values = [
      'title' => 'the node from the past',
      'type' => 'content_type_one',
      'body' => 'the past body',
      'created' => $date->getTimestamp(),
    ];

    $node = Node::create($values);
    $node->save();

    $date->modify('+ 2 months');
    $values = [
      'title' => 'the node from the future',
      'type' => 'content_type_one',
      'body' => 'the future body',
      'created' => $date->getTimestamp(),
    ];

    $node = Node::create($values);
    $node->save();

    // Index the nodes.
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    $index->indexItems();

    // Go to the form and assert we see both nodes.
    $this->drupalGet('/facets-form-test');
    $this->assertSession()->pageTextContains('Facets form test');

    $assert = $this->assertSession();
    $assert->pageTextContains('the node from the past');
    $assert->pageTextContains('the node from the future');

    // Create the status facet, defaulting to UPCOMING.
    $source_id = ListSourceFactory::generateFacetSourcePluginId('node', 'content_type_one');
    $facet = $this->createFacet('created', $source_id, '', 'oe_list_pages_multiselect');
    $facet->set('name', 'Status');
    $processor_options = [
      'default_status' => DateStatus::UPCOMING,
      'upcoming_label' => 'Upcoming',
      'past_label' => 'Past',
    ];
    $facet->addProcessor([
      'processor_id' => 'oe_list_pages_date_status_processor',
      'weights' => ['pre_query' => 60, 'build' => 35],
      'settings' => $processor_options,
    ]);
    $facet->save();

    // Go back to the form and assert we don't see the past node anymore.
    $this->drupalGet('/facets-form-test');
    $assert->pageTextNotContains('the node from the past');
    $assert->pageTextContains('the node from the future');
    $this->assertEquals([
      'Upcoming',
      'Past',
    ], array_values($this->getSelectOptions('Status')));
    // The widget element has the UPCOMING option as pre-selected.
    $this->assertEquals('selected', $this->getSession()->getPage()->findField('Status')->find('css', 'option[value="upcoming"]')->getAttribute('selected'));
    $this->assertNull($this->getSession()->getPage()->findField('Status')->find('css', 'option[value="past"]')->getAttribute('selected'));

    // Unset the Status field and search by the Body.
    $this->getSession()->getPage()->findField('Status')->setValue([]);
    $this->getSession()->getPage()->fillField('Body', 'past');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('the node from the past');
    $assert->pageTextNotContains('the node from the future');
    // None of the Status values are pre-selected.
    $this->assertNull($this->getSession()->getPage()->findField('Status')->find('css', 'option[value="upcoming"]')->getAttribute('selected'));
    $this->assertNull($this->getSession()->getPage()->findField('Status')->find('css', 'option[value="past"]')->getAttribute('selected'));
  }

  /**
   * Tests the facets list form with ignored filters.
   */
  public function testFacetsIgnoredFiltersForm(): void {
    // Tests the facets list form with ignored filters.
    $this->state->set('oe_list_pages_test.ignored_filters', ['body']);
    $this->drupalGet('/facets-form-test');
    $this->assertSession()->pageTextContains('Facets form test');
    $assert = $this->assertSession();

    // Body is not present.
    $this->assertSession()->fieldNotExists('Body');

    // Filter by multiselect.
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test1');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test2');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
  }

  /**
   * Asserts the form elements are correct when the form is initially loaded.
   */
  protected function assertDefaultFormStatus(): void {
    $assert = $this->assertSession();
    // Assert that we can see both nodes.
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
    // Assert that we can see all form elements.
    $names = ['Body', 'Created', 'Select one'];
    foreach ($names as $name) {
      $assert->fieldExists($name);
    }

    // Assert the date field elements.
    $assert->fieldExists('created_op');
    $this->assertFalse($this->getSession()->getPage()->findField('created_first_date_wrapper[created_first_date][date]')->isVisible());
    $this->assertFalse($this->getSession()->getPage()->findField('created_second_date_wrapper[created_second_date][date]')->isVisible());

    // Assert the multiselect has two values.
    $this->assertEquals([
      'test1',
      'test2',
    ], array_values($this->getSelectOptions('Select one')));
  }

  /**
   * Get available options of select box.
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
