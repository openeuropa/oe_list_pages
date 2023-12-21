<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListSourceFactory;

/**
 * Tests the list builder.
 */
class ListBuilderTest extends ListsSourceTestBase {

  /**
   * Tests the facet cache tags are correctly applied on the render arrays.
   */
  public function testFacetCacheTags(): void {
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $facet = $this->createFacet('created', $default_list_id, '', 'oe_list_pages_multiselect', []);
    $facet->addProcessor([
      'processor_id' => 'oe_list_pages_date_status_processor',
      'weights' => [],
      'settings' => [],
    ]);
    $facet->save();

    $configuration = [
      'entity_type' => 'entity_test_mulrev_changed',
      'bundle' => 'item',
      'exposed_filters' => [],
      'exposed_filters_overridden' => FALSE,
      'default_filter_values' => [],
      'contextual_filters' => [],
    ];
    $listPageConfiguration = new ListPageConfiguration($configuration);

    $listBuilder = $this->container->get('oe_list_pages.builder');
    $render_array = $listBuilder->buildList($listPageConfiguration);

    $expected_cache_tags = [
      'config:search_api.index.database_search_index',
      'config:facets.facet.list_facet_source_entity_test_mulrev_changed_itemcreated',
      'entity_test_mulrev_changed_list:item',
    ];
    $this->assertEquals($expected_cache_tags, $render_array['#cache']['tags']);

    $render_array = $listBuilder->buildSelectedFilters($listPageConfiguration);
    $expected_cache_tags = [
      'config:facets.facet.list_facet_source_entity_test_mulrev_changed_itemcreated',
    ];
    $this->assertEquals($expected_cache_tags, $render_array['#cache']['tags']);
  }

}
