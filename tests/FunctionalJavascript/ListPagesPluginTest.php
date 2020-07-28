<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the List pages EMR plugin form.
 *
 * @group oe_list_pages
 */
class ListPagesPluginTest extends WebDriverTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'taxonomy',
    'node',
    'oe_list_pages_event_subscriber_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'node_type_one',
      'name' => 'Node Type 1',
    ]);

    $this->drupalCreateContentType([
      'type' => 'node_type_two',
      'name' => 'Node Type 2',
    ]);

    $this->drupalCreateContentType([
      'type' => 'node_type_three',
      'name' => 'Node Type 3',
    ]);

    Vocabulary::create([
      'vid' => 'vocab_one',
      'name' => 'Vocabulary 1',
    ])->save();

    Vocabulary::create([
      'vid' => 'vocab_two',
      'name' => 'Vocabulary 2',
    ])->save();

    Vocabulary::create([
      'vid' => 'vocab_three',
      'name' => 'Vocabulary 3',
    ])->save();

    /** @var \Drupal\emr\EntityMetaRelationInstaller $installer */
    $installer = \Drupal::service('emr.installer');
    $installer->installEntityMetaTypeOnContentEntityType('oe_list_page', 'node', [
      'node_type_one',
    ]);
  }

  /**
   * Test List Page entity meta plugin and available entity types/bundles.
   */
  public function testListPagePluginForm(): void {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('node/add/node_type_one');

    // Open the list page details element.
    $this->clickLink('List Page');

    $actual_entity_types = $this->getSelectOptions('Source entity type');

    $expected_entity_types = [
      'entity_meta_relation' => 'Entity Meta Relation',
      'entity_meta' => 'Entity meta',
      'node' => 'Content',
      'path_alias' => 'URL alias',
      'search_api_task' => 'Search task',
      'user' => 'User',
      'taxonomy_term' => 'Taxonomy term',
    ];
    $this->assertEquals($expected_entity_types, $actual_entity_types);
    // By default, Node is selected if there are no stored values.
    $this->assertOptionSelected('Source entity type', 'Content');

    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'node_type_one' => 'Node Type 1',
      'node_type_two' => 'Node Type 2',
      'node_type_three' => 'Node Type 3',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    // Switch to the taxonomy term and assert that we have different bundles.
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'vocab_one' => 'Vocabulary 1',
      'vocab_two' => 'Vocabulary 2',
      'vocab_three' => 'Vocabulary 3',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    // Switch to a bundle-less entity type and assert we have only one bundle
    // selection available.
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'user');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'user' => 'User',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    // Set state values to trigger the test event subscriber and make some
    // limitations.
    $allowed = [
      'node' => [
        'node_type_two',
        'node_type_three',
      ],
      'taxonomy_term' => [
        'vocab_one',
        'vocab_three',
      ],
    ];
    \Drupal::state()->set('oe_list_pages_test.allowed_entity_types_bundles', $allowed);

    $this->drupalGet('node/add/node_type_one');
    $this->clickLink('List Page');
    $actual_entity_types = $this->getSelectOptions('Source entity type');
    $this->assertEquals([
      'node' => 'Content',
      'taxonomy_term' => 'Taxonomy term',
    ], $actual_entity_types);
    $this->assertOptionSelected('Source entity type', 'Content');
    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'node_type_two' => 'Node Type 2',
      'node_type_three' => 'Node Type 3',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'vocab_one' => 'Vocabulary 1',
      'vocab_three' => 'Vocabulary 3',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'vocab_three');

    // Select a bundle and save the node.
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
    $this->assertEquals('taxonomy_term:vocab_three', $entity_meta_wrapper->getSource());
    $this->assertEquals('taxonomy_term', $entity_meta_wrapper->getSourceEntityType());
    $this->assertEquals('vocab_three', $entity_meta_wrapper->getSourceEntityBundle());

    // Edit the node and assert that we show correct values in the form.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->clickLink('List Page');
    $this->assertOptionSelected('Source entity type', 'Taxonomy term');
    $this->assertOptionSelected('Source bundle', 'Vocabulary 3');

    // Change the source to a Node type.
    $this->getSession()->getPage()->fillField('Title', 'Node title 2');
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'node_type_two');
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
    $this->assertEquals('node:node_type_two', $entity_meta_wrapper->getSource());
    $this->assertEquals('node', $entity_meta_wrapper->getSourceEntityType());
    $this->assertEquals('node_type_two', $entity_meta_wrapper->getSourceEntityBundle());

    // Assert the previous entity meta revision kept the old value.
    $first_revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision(1);
    $this->assertEquals(1, $first_revision->getRevisionId());
    /** @var \Drupal\emr\Field\ComputedEntityMetasItemList $entity_meta_list */
    $entity_meta_list = $first_revision->get('emr_entity_metas');
    $entity_meta = $entity_meta_list->getEntityMeta('oe_list_page');
    $this->assertFalse($entity_meta->isNew());

    /** @var \Drupal\oe_list_pages\ListPageWrapper $entity_meta_wrapper */
    $entity_meta_wrapper = $entity_meta->getWrapper();
    $this->assertEquals('taxonomy_term:vocab_three', $entity_meta_wrapper->getSource());
    $this->assertEquals('taxonomy_term', $entity_meta_wrapper->getSourceEntityType());
    $this->assertEquals('vocab_three', $entity_meta_wrapper->getSourceEntityBundle());
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
