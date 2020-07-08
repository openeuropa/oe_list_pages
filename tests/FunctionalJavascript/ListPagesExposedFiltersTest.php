<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\oe_list_pages\Traits\SearchApiTestTrait;

/**
 * Tests the List pages exposed filters.
 *
 * @group oe_list_pages
 */
class ListPagesExposedFiltersTest extends WebDriverTestBase {

  use SearchApiTestTrait;

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
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    /** @var \Drupal\emr\EntityMetaRelationInstaller $installer */
    $installer = \Drupal::service('emr.installer');
    $installer->installEntityMetaTypeOnContentEntityType('oe_list_page', 'node', [
      'content_type_one',
    ]);

    // Index new bundles.
    $this->index = Index::load('node');
    $this->datasource = $this->index->getDatasource('entity:node');
    $this->datasource->setConfiguration([
      'bundles' => [
        'default' => FALSE,
        'selected' => ['content_type_one', 'content_type_two'],
      ],
    ]);

    $this->index->save();
  }

  /**
   * Test configuring of exposed filters.
   */
  public function testListPagePluginFilters(): void {
    // Create facets for content type one.
    $ct_one = ListSourceFactory::generateFacetSourcePluginId('node', 'content_type_one');
    $this->createFacet('field_select_one', $ct_one);
    $this->createFacet('status', $ct_one);

    // Create facets for content type two.
    $ct_two = ListSourceFactory::generateFacetSourcePluginId('node', 'content_type_two');
    $this->createFacet('field_select_two', $ct_two);
    $this->createFacet('status', $ct_two);

    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/node/add/content_type_one');
    $this->clickLink('List Page');
    $actual_entity_types = $this->getSelectOptions('Source entity type');
    $expected_entity_types = [
      'entity_meta_relation' => 'Entity Meta Relation',
      'entity_meta' => 'Entity meta',
      'node' => 'Content',
      'path_alias' => 'URL alias',
      'search_api_task' => 'Search task',
      'user' => 'User',
    ];
    $this->assertEquals($expected_entity_types, $actual_entity_types);
    // By default, Node is selected if there are no stored values.
    $this->assertOptionSelected('Source entity type', 'Content');

    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'content_type_one' => 'Content type one',
      'content_type_two' => 'Content type two',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);
    $page = $this->getSession()->getPage();
    $page->checkField('select one');
    $page->checkField('Published');
    $page->fillField('Title', 'Node title');

    $page->pressButton('Save');

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('entity_meta_relation')->resetCache();

    $node = Node::load(1);
    /** @var \Drupal\emr\Field\ComputedEntityMetasItemList $entity_meta_list */
    $entity_meta_list = $node->get('emr_entity_metas');
    $entity_meta = $entity_meta_list->getEntityMeta('oe_list_page');
    $entity_meta_wrapper = $entity_meta->getWrapper();
    $actual_exposed_filters = $entity_meta_wrapper->getConfiguration()['exposed_filters'];
    $this->assertEquals($actual_exposed_filters, [
      'field_select_one' => 'field_select_one',
      'status' => 'status',
    ]);

    $this->drupalGet('/node/1/edit');
    $this->clickLink('List Page');
    $page->selectFieldOption('Source bundle', 'Content type two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->checkField('select two');
    $page->pressButton('Save');

    \Drupal::entityTypeManager()->getStorage('node')->resetCache();
    \Drupal::entityTypeManager()->getStorage('entity_meta_relation')->resetCache();

    $node = Node::load(1);
    /** @var \Drupal\emr\Field\ComputedEntityMetasItemList $entity_meta_list */
    $entity_meta_list = $node->get('emr_entity_metas');
    $entity_meta = $entity_meta_list->getEntityMeta('oe_list_page');
    $entity_meta_wrapper = $entity_meta->getWrapper();
    $actual_exposed_filters = $entity_meta_wrapper->getConfiguration()['exposed_filters'];
    $this->assertEquals($actual_exposed_filters, [
      'field_select_two' => 'field_select_two',
    ]);

    $this->drupalGet('/node/1/edit');
    $this->clickLink('List Page');
    $this->assertFieldChecked('select two');
    $this->assertNoFieldChecked('Published');
  }

}
