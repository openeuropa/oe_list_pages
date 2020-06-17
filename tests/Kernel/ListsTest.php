<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBaseTest;
use Drupal\oe_list_pages\ListSource;
use Drupal\search_api\Entity\Index;

/**
 * Tests the List internal functionality..
 */
class ListsTest extends EntityKernelTestBaseTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_test',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages',
    'node',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'search_api_test_example_content',
    'system',
  ];

  /**
   * The list manager.
   *
   * @var \Drupal\oe_list_pages\ListManager
   */
  protected $listManager;

  /**
   * The facet manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('facets_facet');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('entity_meta');
    $this->installEntitySchema('entity_meta_relation');

    \Drupal::state()->set('search_api_use_tracking_batch', FALSE);

    // Set tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    $this->installConfig([
      'emr',
      'emr_node',
      'oe_list_pages',
      'search_api_test_example_content',
      'search_api_test_db',
      'system',
    ]);

    entity_test_create_bundle('article', '', 'entity_test_mulrev_changed');
    entity_test_create_bundle('item', '', 'entity_test_mulrev_changed');

    // Index new bundles.
    $this->index = Index::load('database_search_index');
    $this->datasource = $this->index->getDatasource('entity:entity_test_mulrev_changed');
    $this->datasource->setConfiguration([
      'bundles' => [
        'default' => FALSE,
        'selected' => ['item', 'entity_test_mulrev_changed'],
      ],
    ]);

    $this->index->save();

    /** @var \Drupal\oe_list_pages\ListManager $listManager */
    $this->listManager = \Drupal::service('oe_list_pages.list_manager');

    /** @var \Drupal\facets\FacetManager\DefaultFacetManager facetManager */
    $this->facetManager = \Drupal::service('facets.manager');
  }

  /**
   * Tests that all indexed bundles have a list.
   */
  public function testListForEachBundle(): void {
    $indexed_bundles = $this->listManager->getAvailableLists();
    $facet_sources = $this->container
      ->get('plugin.manager.facets.facet_source')
      ->getDefinitions();

    $available_facet_sources = [];
    foreach ($facet_sources as $facet_source_id => $facet_source) {
      $available_facet_sources[] = $facet_source['display_id'];
    }

    // Item bundle is indexed.
    $article_plugin_id = 'entity_test_mulrev_changed' . PluginBase::DERIVATIVE_SEPARATOR . 'item';
    $this->assertContains($article_plugin_id, $available_facet_sources);
    // entity_test_mulrev_changed bundle is  indexed.
    $article_plugin_id = 'entity_test_mulrev_changed' . PluginBase::DERIVATIVE_SEPARATOR . 'entity_test_mulrev_changed';
    $this->assertContains($article_plugin_id, $available_facet_sources);
    // Article bundle is not indexed.
    $article_plugin_id = 'entity_test_mulrev_changed' . PluginBase::DERIVATIVE_SEPARATOR . 'article';
    $this->assertNotContains($article_plugin_id, $available_facet_sources);
  }

  /**
   * Tests that all available filters within a list.
   */
  public function testAvailableFilters(): void {
    // Create facets for default bundle.
    $default_list = new ListSource('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $this->createFacet('category', $default_list->id());
    $this->createFacet('keywords', $default_list->id());
    $this->createFacet('width', $default_list->id());

    // Create facets for item bundle.
    $item_list = new ListSource('entity_test_mulrev_changed', 'item');
    $this->createFacet('category', $item_list->id());
    $this->createFacet('width', $item_list->id());

    // Filters for default bundle.
    $filters = $default_list->getAvailableFilters();
    $this->assertCount(3, $filters);
    $this->assertArrayHasKey('category', $filters);
    $this->assertArrayHasKey('keywords', $filters);
    $this->assertArrayHasKey('width', $filters);

    // Filters for item bundle.
    $filters_item = $item_list->getAvailableFilters();
    $this->assertCount(2, $filters_item);
    $this->assertArrayHasKey('category', $filters_item);
    $this->assertArrayNotHasKey('keywords', $filters_item);
    $this->assertArrayHasKey('width', $filters_item);
  }

  /**
   * Creates a facet for the specified field.
   *
   * @param string $field
   *   The field.
   * @param string $search_id
   *   The search id.
   */
  private function createFacet(string $field, string $search_id): FacetInterface {
    $entity = Facet::create([
      'id' => md5($search_id) . '_' . $field,
      'name' => 'Facet for ' . $field,
    ]);
    $entity->setWidget('links');
    $entity->setFieldIdentifier($field);
    $entity->setEmptyBehavior(['behavior' => 'none']);
    $entity->setFacetSourceId($search_id);
    $entity->save();

    return $entity;
  }

}
