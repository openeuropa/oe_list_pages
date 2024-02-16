<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Plugin\PluginBase;
use Drupal\oe_list_pages\ListSourceInterface;

/**
 * Tests the available List sources and their available filters.
 */
class ListsSourceTest extends ListsSourceTestBase {

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
    $this->assertArrayHasKey('list_facet_source_entity_test_mulrev_changed_entity_test_mulrev_changedcategory', $filters);
    $this->assertArrayHasKey('list_facet_source_entity_test_mulrev_changed_entity_test_mulrev_changedkeywords', $filters);
    $this->assertArrayHasKey('list_facet_source_entity_test_mulrev_changed_entity_test_mulrev_changedwidth', $filters);
    // Filters for item bundle.
    $filters_item = $item_list->getAvailableFilters();
    $this->assertCount(2, $filters_item);
    $this->assertArrayHasKey('list_facet_source_entity_test_mulrev_changed_itemcategory', $filters_item);
    $this->assertArrayNotHasKey('list_facet_source_entity_test_mulrev_changed_itemkeywords', $filters_item);
    $this->assertArrayHasKey('list_facet_source_entity_test_mulrev_changed_itemwidth', $filters_item);
  }

  /**
   * Tests that the correct index is being used creating list page sources.
   */
  public function testListPageIndexConfig(): void {
    /** @var \Drupal\oe_list_pages\ListSourceFactoryInterface $list_source_factory */
    $list_source_factory = $this->container->get('oe_list_pages.list_source.factory');
    $list_source = $list_source_factory->get('entity_test_mulrev_changed', 'item');
    $this->assertInstanceOf(ListSourceInterface::class, $list_source);
    $index = $list_source->getIndex();
    $this->assertEquals('database_search_index', $index->id());

    // Create another index that is not meant for list pages.
    $second_index = $this->index->createDuplicate();
    $second_index->set('id', 'database_search_index_two');
    $second_index->setThirdPartySetting('oe_list_pages', 'lists_pages_index', FALSE);
    $second_index->save();

    // Clear the service from the container to remove the static cache.
    $this->container->set('oe_list_pages.list_source.factory', NULL);
    /** @var \Drupal\oe_list_pages\ListSourceFactoryInterface $list_source_factory */
    $list_source_factory = $this->container->get('oe_list_pages.list_source.factory');
    $list_source = $list_source_factory->get('entity_test_mulrev_changed', 'item');
    $this->assertInstanceOf(ListSourceInterface::class, $list_source);
    $index = $list_source->getIndex();
    // Assert that the list source still uses the first index.
    $this->assertEquals('database_search_index', $index->id());

    // Make the second index the list pages one.
    $second_index->setThirdPartySetting('oe_list_pages', 'lists_pages_index', TRUE);
    $second_index->save();
    $this->index->setThirdPartySetting('oe_list_pages', 'lists_pages_index', FALSE);
    $this->index->save();
    $this->container->set('oe_list_pages.list_source.factory', NULL);
    /** @var \Drupal\oe_list_pages\ListSourceFactoryInterface $list_source_factory */
    $list_source_factory = $this->container->get('oe_list_pages.list_source.factory');
    $list_source = $list_source_factory->get('entity_test_mulrev_changed', 'item');
    $this->assertInstanceOf(ListSourceInterface::class, $list_source);
    // Assert that now the list source uses the other index.
    $index = $list_source->getIndex();
    $this->assertEquals('database_search_index_two', $index->id());
  }

}
