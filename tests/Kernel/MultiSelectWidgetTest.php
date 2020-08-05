<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\widget\ListPagesMultiselectWidget;

/**
 * Test for Multiselect widget and query type.
 */
class MultiSelectWidgetTest extends ListsSourceBaseTest {

  /**
   * The widget.
   *
   * @var \Drupal\oe_list_pages\Plugin\facets\widget\ListPagesMultiselectWidget
   */
  protected $widget;

  /**
   * Creates a new processor object for use in the tests.
   */
  protected function setUp() {
    parent::setUp();
    $this->widget = new ListPagesMultiselectWidget([], 'oe_list_pages_multiselect', []);
  }

  /**
   * Tests widget value conversion.
   */
  public function testWidgetFormValue(): void {
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $facet_active = $this->createFacet('body', $default_list_id);
    $facet_active->setActiveItems(['message']);
    $output = $this->widget->build($facet_active)[$facet_active->id()];
    $this->assertSame('array', gettype($output));
    $this->assertEquals('select', $output['#type']);
    $this->assertEquals('message', $output['#default_value']);

    // Now without active result.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed', 'inactive');
    $facet_inactive = $this->createFacet('body', $default_list_id, 'inactive');
    $output = $this->widget->build($facet_inactive)[$facet_inactive->id()];
    $this->assertSame('array', gettype($output));
    $this->assertEquals('select', $output['#type']);
    $this->assertEmpty($output['#default_value']);
  }

  /**
   * Tests query type using the active filter.
   */
  public function testQueryType(): void {
    $this->createTestContent('item', 4);
    // Another list for another bundle.
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $item_list->getIndex()->indexItems();

    // Create facets for body and name.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $facet_name = $this->createFacet('name', $default_list_id, '', 'oe_list_pages_multiselect', []);
    $facet_categories = $this->createFacet('category', $default_list_id, '', 'oe_list_pages_multiselect', []);

    // Search for body.
    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $list->getQuery(0, 0, [], [$facet_name->id() => 'None']);
    $query->execute();
    $results = $query->getResults();

    // Asserts results.
    $this->assertCount(1, $results->getResultItems());

    // Search for name.
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $list->getQuery(0, 0, [], [$facet_categories->id() => 'cat1']);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(3, $results->getResultItems());
  }

  /**
   * Create test content.
   *
   * @param string $bundle
   *   The bundle.
   * @param int $count
   *   The number of items to create.
   */
  protected function createTestContent(string $bundle, int $count): void {
    $titles = ['With nothing', 'With a void', 'With a message', 'None'];
    $categories = ['cat1', 'cat2', 'cat1', 'cat1'];

    // Add new entities.
    $entity_test_storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed');
    for ($i = 1; $i <= $count; $i++) {
      $entity_test_storage->create([
        'name' => $titles[$i % $count],
        'category' => $categories[$i % $count],
        'type' => $bundle,
      ])->save();
    }
  }

}
