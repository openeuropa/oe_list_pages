<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Functional;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\search_api\Entity\Index;

/**
 * Tests the list facets form.
 */
class FacetsFormTest extends WebDriverTestBase {

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
    $this->drupalGet('/facets-form-test');
    $this->assertSession()->pageTextContains('Facets form test');

    $assert = $this->assertSession();
    $assert->fieldExists('Body');
    $this->assertDefaultFormStatus();

    // Filter by body to only find 1 result.
    $this->getSession()->getPage()->fillField('Body', 'banana');
    $this->getSession()->getPage()->pressButton('Search');

    // Assert results and form changes.
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $assert->fieldValueEquals('Body', 'banana');
    $this->assertEquals([
      'test1',
    ], array_values($this->getSelectOptions('Select one')));

    // Reset the form.
    $this->getSession()->getPage()->pressButton('Reset');
    $this->assertDefaultFormStatus();

    // Filter by body and multiselect.
    $this->getSession()->getPage()->fillField('Body', 'cherry');
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test1');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $this->getSession()->getPage()->selectFieldOption('Select one', 'test2');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');

    // Filter by date.
    $this->getSession()->getPage()->pressButton('Reset');
    $this->getSession()->getPage()->fillField('created_first_date[date]', '10/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
    $this->getSession()->getPage()->fillField('created_first_date[date]', '10/15/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
    $this->getSession()->getPage()->selectFieldOption('created_op', 'lt');
    $this->assertFalse($this->getSession()->getPage()->findField('created_second_date[date]')->isVisible());
    $this->assertTrue($this->getSession()->getPage()->findField('created_first_date[date]')->isVisible());
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextNotContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $this->getSession()->getPage()->fillField('created_first_date[date]', '10/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $this->getSession()->getPage()->fillField('created_first_date[date]', '11/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextContains('that red fruit');
    $this->getSession()->getPage()->selectFieldOption('created_op', 'bt');
    $this->assertTrue($this->getSession()->getPage()->findField('created_second_date[date]')->isVisible());
    $this->assertTrue($this->getSession()->getPage()->findField('created_first_date[date]')->isVisible());
    $this->getSession()->getPage()->fillField('created_first_date[date]', '10/15/2020');
    $this->getSession()->getPage()->fillField('created_second_date[date]', '10/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
    $this->getSession()->getPage()->fillField('created_first_date[date]', '11/15/2020');
    $this->getSession()->getPage()->fillField('created_second_date[date]', '11/25/2020');
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
    $this->getSession()->getPage()->fillField('created_first_date[date]', '10/15/2020');
    $this->getSession()->getPage()->fillField('created_second_date[date]', '10/25/2020');
    $this->getSession()->getPage()->pressButton('Search');
    $assert->pageTextContains('that yellow fruit');
    $assert->pageTextNotContains('that red fruit');
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
    $this->assertFalse($this->getSession()->getPage()->findField('created_second_date[date]')->isVisible());
    $this->assertTrue($this->getSession()->getPage()->findField('created_first_date[date]')->isVisible());

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
