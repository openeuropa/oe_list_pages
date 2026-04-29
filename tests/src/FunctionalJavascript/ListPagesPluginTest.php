<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\node\Entity\Node;
use Drupal\oe_list_pages\ListPageWrapper;

/**
 * Tests the List pages configuration form.
 *
 * @group oe_list_pages
 */
class ListPagesPluginTest extends ListPagePluginFormTestBase {

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
    'rdf_skos',
    'search_api',
    'search_api_db',
    'oe_list_pages_event_subscriber_test',
    'oe_list_pages_filters_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test list page configuration form and available entity types/bundles.
   */
  public function testListPagePluginForm(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('node/add/oe_list_page');

    $this->assertListPageEntityTypeSelection();

    // Save the node.
    $this->getSession()->getPage()->fillField('Title', 'Node title');
    $this->getSession()->getPage()->pressButton('Save');

    // Assert the node-level list page fields were saved.
    $node = Node::load(1);
    $this->assertEquals(1, $node->getRevisionId());
    $this->assertEquals('Node title', $node->label());
    $wrapper = new ListPageWrapper($node);
    $this->assertEquals('taxonomy_term:vocabulary_one', $wrapper->getSource());
    $this->assertEquals('taxonomy_term', $wrapper->getSourceEntityType());
    $this->assertEquals('vocabulary_one', $wrapper->getSourceEntityBundle());

    // Edit the node and assert that we show correct values in the form.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertTrue($this->assertSession()->optionExists('Source entity type', 'Taxonomy term')->isSelected());
    $this->assertTrue($this->assertSession()->optionExists('Source bundle', 'Vocabulary one')->isSelected());

    // Change the source to a Node type.
    $this->getSession()->getPage()->fillField('Title', 'Node title 2');
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    $node = Node::load(1);
    $this->assertEquals(2, $node->getRevisionId());
    $wrapper = new ListPageWrapper($node);
    $this->assertEquals('node:content_type_one', $wrapper->getSource());
    $this->assertEquals('node', $wrapper->getSourceEntityType());
    $this->assertEquals('content_type_one', $wrapper->getSourceEntityBundle());

    // Assert the previous revision kept the old value.
    $first_revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision(1);
    $this->assertEquals(1, $first_revision->getRevisionId());
    $first_wrapper = new ListPageWrapper($first_revision);
    $this->assertEquals('taxonomy_term:vocabulary_one', $first_wrapper->getSource());
    $this->assertEquals('taxonomy_term', $first_wrapper->getSourceEntityType());
    $this->assertEquals('vocabulary_one', $first_wrapper->getSourceEntityBundle());
  }

  /**
   * {@inheritdoc}
   */
  protected function goToListPageConfiguration(): void {
    $this->drupalGet('node/add/oe_list_page');
  }

}
