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
class ListsSourceBaseTest extends EntityKernelTestBaseTest {

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
   * Generate a valid facet it.
   *
   * @param string $field
   *   The field.
   * @param string $search_id
   *   The search id.
   * @param string $suffix
   *   The suffix.
   *
   * @return string
   *   The facet id.
   */
  protected function generateFacetId($field, $search_id, $suffix = ''): string {
    return str_replace(':', '_', $search_id . $field . $suffix);
  }

  /**
   * Creates a facet for the specified field.
   *
   * @param string $field
   *   The field.
   * @param string $search_id
   *   The search id.
   * @param string $suffix
   *   The suffix.
   * @param string $widget_id
   *   The widget id.
   * @param array $widget_config
   *   The widget config.
   *
   * @return \Drupal\facets\FacetInterface
   *   The created facet.
   */
  protected function createFacet(string $field, string $search_id, string $suffix = '', string $widget_id = '', array $widget_config = []): FacetInterface {
    $facet_id = $this->generateFacetId($field, $search_id, $suffix);
    $entity = Facet::create([
      'id' => $facet_id,
      'name' => 'Facet for ' . $field,
    ]);
    $entity->setUrlAlias($facet_id);
    $entity->setFieldIdentifier($field);
    $entity->setEmptyBehavior(['behavior' => 'none']);
    $entity->setFacetSourceId($search_id);
    if (!empty($widget_id)) {
      $entity->setWidget($widget_id, $widget_config);
    }
    else {
      $entity->setWidget('links', ['show_numbers' => TRUE]);
    }

    $entity->addProcessor([
      'processor_id' => 'url_processor_handler',
      'weights' => ['pre_query' => -10, 'build' => -10],
      'settings' => [],
    ]);
    $entity->save();

    return $entity;
  }

}
