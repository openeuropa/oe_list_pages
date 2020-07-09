<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Plugin\PluginBase;

/**
 * Tests the available List sources and their available filters.
 */
class ListsSourceTest extends ListsSourceBaseTest {

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

}
