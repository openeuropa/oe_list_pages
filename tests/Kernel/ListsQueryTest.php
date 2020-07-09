<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Plugin\PluginBase;
use Drupal\facets\Entity\Facet;
use Drupal\facets\FacetInterface;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBaseTest;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\search_api\Entity\Index;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the List sources querying functionality.
 */
class ListsQueryTest extends ListsSourceBaseTest {

  /**
   * Tests the query functionality without filters.
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
        'type' => $bundle,
        'category' => $categories[$i % 3],
      ])->save();
    }
  }

}
