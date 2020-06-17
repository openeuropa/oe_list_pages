<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Plugin\PluginBase;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBaseTest;

/**
 * Tests the List internal functionality..
 */
class ListsTest extends EntityKernelTestBaseTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
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
      $available_facet_sources[] = $facet_source_id;
    }

    foreach ($indexed_bundles as $entity_type => $bundles) {
      foreach ($bundles as $bundle) {
        $id = $entity_type . PluginBase::DERIVATIVE_SEPARATOR . $bundle['id'];
        $position = array_search($id, $available_facet_sources);
        $this->assertNotEqual($position, '-1');
        unset($available_facet_sources[$position]);
      }
    }

    // Only indexed bundles have an associate facet source.
    $this->assertEmpty($available_facet_sources);
  }

}
