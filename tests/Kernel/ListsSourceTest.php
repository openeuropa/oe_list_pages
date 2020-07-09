<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBaseTest;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the List sources and their properties.
 */
class ListsSourceTest extends EntityKernelTestBaseTest {

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
   * The facet manager.
   *
   * @var \Drupal\facets\FacetManager\DefaultFacetManager
   */
  protected $facetManager;

  /**
   * The list factory service.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactory
   */
  protected $listFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('facets_facet');
    $this->installEntitySchema('search_api_task');
    $this->installSchema('search_api', ['search_api_item']);
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

    // Create extra bundles for the test entity.
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

    $this->listFactory = \Drupal::service('oe_list_pages.list_source.factory');
    $this->facetManager = \Drupal::service('facets.manager');
  }

  /**
   * Tests that all indexed bundles have a list.
   */
  public function testListForEachBundle(): void {
    $facet_sources = $this->container
      ->get('plugin.manager.facets.facet_source')
      ->getDefinitions();

    $display_manager = $this->container->get('plugin.manager.search_api.display');

    $indexed = [
      'list_facet_source:entity_test_mulrev_changed' . PluginBase::DERIVATIVE_SEPARATOR . 'item',
      'list_facet_source:entity_test_mulrev_changed' . PluginBase::DERIVATIVE_SEPARATOR . 'entity_test_mulrev_changed',
    ];
    $not_indexed = [
      'list_facet_source:entity_test_mulrev_changed' . PluginBase::DERIVATIVE_SEPARATOR . 'article',
    ];

    foreach ($indexed as $id) {
      $this->assertArrayHasKey($id, $facet_sources);
      $this->assertTrue($display_manager->hasDefinition($facet_sources[$id]['display_id']));
    }
    foreach ($not_indexed as $id) {
      $this->assertArrayNotHasKey($id, $facet_sources);
      $this->assertFalse($display_manager->hasDefinition('list_facet_source:entity_test_mulrev_changed' . PluginBase::DERIVATIVE_SEPARATOR . 'article'));
    }
  }

  /**
   * Tests that all available filters within a list.
   */
  public function testAvailableFilters(): void {
    $this->createTestFacets();

    // Get the lists.
    $default_list = $this->listFactory->get('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');

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
   * Tests the query functionality.
   */
  public function testQuery(): void {
    $this->createTestFacets();
    $this->createTestContent('entity_test_mulrev_changed', 5);
    $this->createTestContent('item', 6);

    // Get the lists.
    $default_list = $this->listFactory->get('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');

    // Index items.
    $default_list->getIndex()->indexItems();
    $item_list->getIndex()->indexItems();

    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $default_query = $default_list->getQuery();
    $default_query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $default_results = $default_query->getResults();

    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $item_query = $item_list->getQuery(2);
    $item_query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $item_results = $item_query->getResults();

    // Asserts results.
    $this->assertEquals(5, $default_results->getResultCount());
    $this->assertEquals(6, $item_results->getResultCount());

    $this->assertCount(5, $default_results->getResultItems());
    $this->assertCount(2, $item_results->getResultItems());

    $default_facets = $default_results->getExtraData('search_api_facets');
    $item_facets = $default_results->getExtraData('search_api_facets');
    $expected_facets_category = [
      [
        'count' => 2,
        'filter' => '"second class"',
      ],
      [
        'count' => 2,
        'filter' => '"third class"',
      ],
      [
        'count' => 1,
        'filter' => '"first class"',
      ],
    ];

    $this->assertEquals($expected_facets_category, $default_facets['category']);
  }

  /**
   * Tests ignored filters.
   */
  public function testIgnoredFilters(): void {
    $this->createTestFacets();
    $this->createTestContent('entity_test_mulrev_changed', 5);
    $this->createTestContent('item', 6);

    // Change current request and set category to third class.
    $search_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $facet_id = $this->generateFacetId('category', $search_id);
    $request = new Request();
    $request->query->set('f', [$facet_id . ':third class']);
    \Drupal::requestStack()->push($request);

    // Get the lists.
    $default_list = $this->listFactory->get('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    // Index items.
    $default_list->getIndex()->indexItems();

    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $default_list->getQuery(2, 0, [$facet_id], []);
    $query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $results = $query->getResults();
    // Asserts results.
    $this->assertEquals(5, $results->getResultCount());
  }

  /**
   * Tests preset filters.
   */
  public function testPresetFilters(): void {
    $this->createTestFacets();
    $this->createTestContent('entity_test_mulrev_changed', 5);
    $this->createTestContent('item', 6);

    // Get the lists.
    $default_list = $this->listFactory->get('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    // Index items.
    $default_list->getIndex()->indexItems();

    $search_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $facet_id = $this->generateFacetId('category', $search_id);
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $default_list->getQuery(2, 0, [], [$facet_id => ['third class']]);
    $query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $results = $query->getResults();

    // Asserts results.
    $this->assertEquals(2, $results->getResultCount());
    $this->assertCount(2, $results->getResultItems());
  }

  /**
   * Create test content.
   */
  private function createTestContent($bundle, $count): void {

    $categories = ['first class', 'second class', 'third class'];

    // Add new entities.
    $entity_test_storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed');
    for ($i = 1; $i <= $count; $i++) {
      $entity_test_storage->create([
        'name' => 'foo bar baz ' . $i,
        'body' => 'test ' . $i . ' test',
        'type' => $bundle,
        'keywords' => ['orange'],
        'category' => $categories[$i % 3],
      ])->save();
    }
  }

  /**
   * Create test facets.
   */
  private function createTestFacets(): void {
    // Create facets for default bundle.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $this->createFacet('category', $default_list_id);
    $this->createFacet('keywords', $default_list_id);
    $this->createFacet('width', $default_list_id);

    // Create facets for item bundle.
    $item_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $this->createFacet('category', $item_list_id);
    $this->createFacet('width', $item_list_id);
  }

  /**
   * Generate a valid facet it.
   *
   * @param string $field
   *   The field.
   * @param string $search_id
   *   The search id.
   *
   * @return string
   *   The facet id.
   */
  private function generateFacetId($field, $search_id): string {
    return str_replace(':', '_', $search_id . $field);
  }

  /**
   * Creates a facet for the specified field.
   *
   * @param string $field
   *   The field.
   * @param string $search_id
   *   The search id.
   *
   * @return \Drupal\facets\FacetInterface
   *   The created facet.
   */
  private function createFacet(string $field, string $search_id): FacetInterface {
    $facet_id = $this->generateFacetId($field, $search_id);
    $entity = Facet::create([
      'id' => $facet_id,
      'name' => 'Facet for ' . $field,
    ]);
    $entity->setUrlAlias($facet_id);
    $entity->setFieldIdentifier($field);
    $entity->setEmptyBehavior(['behavior' => 'none']);
    $entity->setFacetSourceId($search_id);
    $entity->setWidget('links', ['show_numbers' => TRUE]);
    $entity->addProcessor([
      'processor_id' => 'url_processor_handler',
      'weights' => ['pre_query' => -10, 'build' => -10],
      'settings' => [],
    ]);
    $entity->save();

    return $entity;
  }

}
