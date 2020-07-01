<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBaseTest;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\search_api\Entity\Index;

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
    // Create facets for default bundle.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $this->createFacet('category', $default_list_id);
    $this->createFacet('keywords', $default_list_id);
    $this->createFacet('width', $default_list_id);

    // Create facets for item bundle.
    $item_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $this->createFacet('category', $item_list_id);
    $this->createFacet('width', $item_list_id);

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
    $entity = Facet::create([
      // Id just needs to be unique when generated.
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
