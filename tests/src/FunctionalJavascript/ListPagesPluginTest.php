<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\node\Entity\Node;

/**
 * Tests the List pages EMR plugin form.
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
    'emr',
    'emr_node',
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
   * Test List Page entity meta plugin and available entity types/bundles.
   */
  public function testListPagePluginForm(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->drupalGet('node/add/oe_list_page');

    $this->assertListPageEntityTypeSelection();

    // Save the node.
    $this->getSession()->getPage()->fillField('Title', 'Node title');
    $this->getSession()->getPage()->pressButton('Save');

    // Assert the entity meta was correctly saved.
    $node = Node::load(1);
    $this->assertEquals(1, $node->getRevisionId());
    $this->assertEquals('Node title', $node->label());
    /** @var \Drupal\emr\Field\ComputedEntityMetasItemList $entity_meta_list */
    $entity_meta_list = $node->get('emr_entity_metas');
    $entity_meta = $entity_meta_list->getEntityMeta('oe_list_page');
    $this->assertFalse($entity_meta->isNew());

    /** @var \Drupal\oe_list_pages\ListPageWrapper $entity_meta_wrapper */
    $entity_meta_wrapper = $entity_meta->getWrapper();
    $this->assertEquals('taxonomy_term:vocabulary_one', $entity_meta_wrapper->getSource());
    $this->assertEquals('taxonomy_term', $entity_meta_wrapper->getSourceEntityType());
    $this->assertEquals('vocabulary_one', $entity_meta_wrapper->getSourceEntityBundle());

    // Edit the node and assert that we show correct values in the form.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $this->assertOptionSelected('Source entity type', 'Taxonomy term');
    $this->assertOptionSelected('Source bundle', 'Vocabulary one');

    // Change the source to a Node type.
    $this->getSession()->getPage()->fillField('Title', 'Node title 2');
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('entity_meta_relation')->resetCache();
    $node = Node::load(1);
    $this->assertEquals(2, $node->getRevisionId());
    /** @var \Drupal\emr\Field\ComputedEntityMetasItemList $entity_meta_list */
    $entity_meta_list = $node->get('emr_entity_metas');
    $entity_meta = $entity_meta_list->getEntityMeta('oe_list_page');
    $entity_meta_wrapper = $entity_meta->getWrapper();
    $this->assertEquals('node:content_type_one', $entity_meta_wrapper->getSource());
    $this->assertEquals('node', $entity_meta_wrapper->getSourceEntityType());
    $this->assertEquals('content_type_one', $entity_meta_wrapper->getSourceEntityBundle());

    // Assert the previous entity meta revision kept the old value.
    $first_revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision(1);
    $this->assertEquals(1, $first_revision->getRevisionId());
    /** @var \Drupal\emr\Field\ComputedEntityMetasItemList $entity_meta_list */
    $entity_meta_list = $first_revision->get('emr_entity_metas');
    $entity_meta = $entity_meta_list->getEntityMeta('oe_list_page');
    $this->assertFalse($entity_meta->isNew());

    /** @var \Drupal\oe_list_pages\ListPageWrapper $entity_meta_wrapper */
    $entity_meta_wrapper = $entity_meta->getWrapper();
    $this->assertEquals('taxonomy_term:vocabulary_one', $entity_meta_wrapper->getSource());
    $this->assertEquals('taxonomy_term', $entity_meta_wrapper->getSourceEntityType());
    $this->assertEquals('vocabulary_one', $entity_meta_wrapper->getSourceEntityBundle());
  }

  /**
   * {@inheritdoc}
   */
  protected function goToListPageConfiguration(): void {
    $this->drupalGet('node/add/oe_list_page');
    $this->clickLink('List Page');
  }

}
