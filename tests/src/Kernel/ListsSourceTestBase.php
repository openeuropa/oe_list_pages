<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\oe_list_pages\Traits\FacetsTestTrait;

/**
 * Tests the List sources and their properties.
 */
abstract class ListsSourceTestBase extends EntityKernelTestBase {

  use FacetsTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages',
    'node',
    'language',
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
   * A test index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * A test index datasource.
   *
   * @var \Drupal\search_api\Datasource\DatasourceInterface
   */
  protected $datasource;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('facets_facet');
    $this->installEntitySchema('search_api_task');
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('entity_meta');
    $this->installEntitySchema('entity_meta_relation');
    $this->installSchema('emr', ['entity_meta_default_revision']);

    \Drupal::state()->set('search_api_use_tracking_batch', FALSE);

    // Set tracking page size so tracking will work properly.
    \Drupal::configFactory()
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    $this->installConfig([
      'emr',
      'emr_node',
      'facets',
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
    $this->index->setThirdPartySetting('oe_list_pages', 'lists_pages_index', TRUE);
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
   * Create test facets.
   */
  protected function createTestFacets(): void {
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
   * Create test content.
   *
   * @param string $bundle
   *   The bundle.
   * @param int $count
   *   The number of items to create.
   * @param array $values
   *   An array with predefined values.
   */
  protected function createTestContent(string $bundle, int $count, array $values = []): void {
    $titles = $values['titles'] ?? [
      'With nothing',
      'With a void',
      'With a message',
      'None',
    ];

    $keywords = $values['keywords'] ?? [
        ['key1'],
        ['key2'],
        ['key1', 'key2'],
        ['key2', 'key3'],
    ];
    $categories = $values['categories'] ?? [
        ['cat1'],
        ['cat2'],
        ['cat1'],
        ['cat1'],
    ];
    $bodies = $values['bodies'] ?? [
      'Sending message',
      'Receiving a Message ',
      'None',
      'Receiving',
    ];

    $dates = $values['dates'] ?? [
      strtotime('2020-08-06 12:00:00'),
      strtotime('2020-08-13 12:00:00'),
      strtotime('2020-08-20 12:00:00'),
      strtotime('2020-08-27 12:00:00'),
    ];

    // Add new entities.
    $entity_test_storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed');
    for ($i = 1; $i <= $count; $i++) {
      $entity_test_storage->create([
        'name' => $titles[$i % $count],
        'body' => $bodies[$i % $count],
        'keywords' => $keywords[$i % $count],
        'category' => $categories[$i % $count],
        'type' => $bundle,
        'created' => $dates[$i % $count],
      ])->save();
    }
  }

}
