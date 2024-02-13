<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;

/**
 * Tests the list page sort configuration.
 *
 * @group oe_list_pages
 */
class ListPagesSortTest extends ListPagePluginFormTestBase {

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
    'rdf_skos',
    'search_api',
    'search_api_db',
    'oe_list_pages_event_subscriber_test',
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
  }

  /**
   * Tests selecting the sort when creating a list page.
   */
  public function testBackendSort(): void {
    // Log in and create a list page.
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();

    // No sort field visible.
    $assert_session = $this->assertSession();
    $this->assertTrue($assert_session->optionExists('Source entity type', 'node')->isSelected());
    $assert_session->fieldNotExists('Sort');

    // Select a bundle with no default sort (normally this should not happen
    // but in case the bundle is not fully configured).
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_two');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldNotExists('Sort');

    // Select a bundle that has the sort configured and assert we don't see the
    // sort field (as we only have 1 sort option).
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldNotExists('Sort');

    // Subscribe to the event and provide another sort option.
    \Drupal::state()->set('oe_list_pages_test.alter_sort_options', TRUE);
    $this->goToListPageConfiguration();
    $this->assertTrue($assert_session->optionExists('Source entity type', 'node')->isSelected());
    // This bundle only has the event subscriber sort so no Sort select should
    // show up.
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_two');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldNotExists('Sort');
    // Save the node and assert we have no sort info in the entity meta.
    $this->getSession()->getPage()->fillField('Title', 'Node title');
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('List page Node title has been created.');
    $this->assertEmpty($this->getSortInformationFromNodeMeta('Node title'));

    // Edit the node and switch to the content type which has more options due
    // to the subscriber.
    $node = $this->drupalGetNodeByTitle('Node title');
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->selectExists('Sort');
    $actual_options = $this->getSelectOptions('Sort');
    $expected_options = [
      'created__DESC' => 'Default',
      'field_test_boolean__DESC' => 'Boolean',
    ];
    $this->assertEquals($expected_options, $actual_options);
    // Assert also that the Default option is selected.
    $this->assertTrue($assert_session->optionExists('Sort', 'Default')->isSelected());

    // Save the node with the default sort selected and assert that again, no
    // sort has been saved in the meta because the defaults should not be saved.
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertEmpty($this->getSortInformationFromNodeMeta('Node title'));
    $this->assertResultsAreInCorrectOrder([
      'First by created',
      'Second by created',
      'Third by created',
      'Fourth by created',
      'First by boolean field',
    ]);

    // Edit again the node and save a different sort.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $this->getSession()->getPage()->selectFieldOption('Sort', 'field_test_boolean__DESC');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertEquals([
      'name' => 'field_test_boolean',
      'direction' => 'DESC',
    ], $this->getSortInformationFromNodeMeta('Node title'));

    $this->assertResultsAreInCorrectOrder([
      'First by boolean field',
      'First by created',
      'Second by created',
      'Third by created',
      'Fourth by created',
    ]);

    // Switch back to the default sort.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $this->assertTrue($assert_session->optionExists('Sort', 'Boolean')->isSelected());
    $this->getSession()->getPage()->selectFieldOption('Sort', 'created__DESC');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertEmpty($this->getSortInformationFromNodeMeta('Node title'));
    $this->assertResultsAreInCorrectOrder([
      'First by created',
      'Second by created',
      'Third by created',
      'Fourth by created',
      'First by boolean field',
    ]);
  }

  /**
   * Tests the sort for the front end users.
   */
  public function testFrontendSort(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();
    $assert_session = $this->assertSession();
    $this->assertTrue($assert_session->optionExists('Source entity type', 'node')->isSelected());
    $this->assertTrue($assert_session->optionExists('Source bundle', 'content_type_one')->isSelected());
    // Since we don't disallow the sort exposing, assert the checkbox is there.
    $assert_session->checkboxNotChecked('Expose sort');
    $this->getSession()->getPage()->fillField('Title', 'Node title');
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('List page Node title has been created.');
    $this->assertResultsAreInCorrectOrder([
      'First by created',
      'Second by created',
      'Third by created',
      'Fourth by created',
      'First by boolean field',
    ]);

    // Since we don't have any other sort options and the sort is not even
    // exposed, we shouldn't see the sort element.
    $assert_session->fieldNotExists('Sort by');

    // Disallow the sort exposing, assert the form checkbox is gone, then allow
    // back to expose the sort.
    \Drupal::state()->set('oe_list_pages_test.disallow_frontend_sort', TRUE);
    $node = $this->drupalGetNodeByTitle('Node title');
    $this->drupalGet($node->toUrl('edit-form'));
    $assert_session->fieldNotExists('Expose sort');
    \Drupal::state()->set('oe_list_pages_test.disallow_frontend_sort', FALSE);
    $this->getSession()->reload();
    $assert_session->fieldExists('Expose sort');
    $this->clickLink('List Page');
    $this->getSession()->getPage()->checkField('Expose sort');
    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('List page Node title has been updated.');

    $this->drupalGet($node->toUrl());
    // Still no exposed sort as we only have 1 sort option.
    $assert_session->fieldNotExists('Sort by');

    // Implement the subscriber and provide another sort option.
    \Drupal::state()->set('oe_list_pages_test.alter_sort_options', TRUE);
    $this->getSession()->reload();
    $assert_session->fieldExists('Sort by');
    $this->assertEquals([
      'created__DESC' => 'Default',
      'field_test_boolean__DESC' => 'From 1 to 0',
    ], $this->getSelectOptions('Sort by'));

    // Disallow the sort and reload to assert the exposed sort is gone.
    \Drupal::state()->set('oe_list_pages_test.disallow_frontend_sort', TRUE);
    $this->getSession()->reload();
    $assert_session->fieldNotExists('Sort by');

    \Drupal::state()->set('oe_list_pages_test.disallow_frontend_sort', FALSE);
    $this->getSession()->reload();
    $assert_session->fieldExists('Sort by');

    // Change the sort.
    $this->markPageToDetectReload();
    $this->getSession()->getPage()->selectFieldOption('Sort by', 'From 1 to 0');
    $this->waitForPageReload();
    $this->assertResultsAreInCorrectOrder([
      'First by boolean field',
      'First by created',
      'Second by created',
      'Third by created',
      'Fourth by created',
    ]);

    // Assert that if there is no valid sort in the URL, the sort doesn't take
    // effect.
    $url = $node->toUrl();
    // First, a sort that works.
    $sort = ['name' => 'field_test_boolean', 'direction' => 'DESC'];
    $url->setOption('query', ['sort' => $sort]);
    $this->drupalGet($url);
    $this->assertResultsAreInCorrectOrder([
      'First by boolean field',
      'First by created',
      'Second by created',
      'Third by created',
      'Fourth by created',
    ]);

    // Next, a sort field that doesn't exist.
    $sort = ['name' => 'not_existing', 'direction' => 'DESC'];
    $url->setOption('query', ['sort' => $sort]);
    $this->drupalGet($url);
    $this->assertResultsAreInCorrectOrder([
      'First by created',
      'Second by created',
      'Third by created',
      'Fourth by created',
      'First by boolean field',
    ]);
    // Finally, a wrong direction.
    $sort = ['name' => 'field_test_boolean', 'direction' => 'WRONG'];
    $url->setOption('query', ['sort' => $sort]);
    $this->drupalGet($url);
    $this->assertResultsAreInCorrectOrder([
      'First by created',
      'Second by created',
      'Third by created',
      'Fourth by created',
      'First by boolean field',
    ]);
  }

  /**
   * Loads the entity meta of a given node and returns the sort value.
   *
   * @param string $title
   *   The node title to load.
   *
   * @return array
   *   The sort value.
   */
  protected function getSortInformationFromNodeMeta(string $title): array {
    $node = $this->drupalGetNodeByTitle($title, TRUE);
    /** @var \Drupal\emr\Field\ComputedEntityMetasItemList $entity_meta_list */
    $entity_meta_list = $node->get('emr_entity_metas');
    $entity_meta = $entity_meta_list->getEntityMeta('oe_list_page');
    /** @var \Drupal\oe_list_pages\ListPageWrapper $entity_meta_wrapper */
    $entity_meta_wrapper = $entity_meta->getWrapper();
    return $entity_meta_wrapper->getConfiguration()['sort'];
  }

  /**
   * Asserts that the list page result titles are in the correct order.
   *
   * @param array $expected_title_order
   *   The expected order of the titles.
   */
  protected function assertResultsAreInCorrectOrder(array $expected_title_order): void {
    $actual_title_order = [];
    foreach ($this->getSession()->getPage()->findAll('css', 'article h2 a') as $element) {
      $actual_title_order[] = $element->getText();
    }

    $this->assertEquals($expected_title_order, $actual_title_order);
  }

  /**
   * {@inheritdoc}
   */
  protected function goToListPageConfiguration(): void {
    $this->drupalGet('node/add/oe_list_page');
    $this->clickLink('List Page');
  }

  /**
   * Marks a page so that we can detect when a reload has been completed.
   *
   * The class will not be present upon reload.
   */
  protected function markPageToDetectReload(): void {
    $script = <<<JS
(function() {
  document.body.classList.add('marked-for-reload');
}())
JS;
    $this->getSession()->executeScript($script);
    $this->assertTrue($this->assertSession()->elementExists('css', 'body')->hasClass('marked-for-reload'));
  }

  /**
   * Waits until the page has been reloaded.
   *
   * Must be preceded by ::markPageToDetectReload() or it won't have any effect.
   */
  protected function waitForPageReload(): void {
    $this->assertNotNull($this->assertSession()->waitForElement('css', 'body:not(.marked-for-reload)'));
  }

}
