<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\widget\MultiselectWidget;

/**
 * Test for Multiselect widget.
 */
class MultiSelectWidgetTest extends ListsSourceBaseTest {

  /**
   * The widget.
   *
   * @var \Drupal\oe_list_pages\Plugin\facets\widget\MultiselectWidget
   */
  protected $widget;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->widget = new MultiselectWidget([], 'oe_list_pages_multiselect', []);
  }

  /**
   * Tests widget value conversion.
   */
  public function testWidgetFormValue(): void {
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $facet_active = $this->createFacet('body', $default_list_id);
    $facet_active->setActiveItems(['message']);
    $output = $this->widget->build($facet_active);
    // There are no results so we should not see the widget element.
    $this->assertFalse(isset($output[$facet_active->id()]));
  }

  /**
   * Tests query type using the active filter.
   */
  public function testQueryType(): void {
    $this->createTestContent('item', 4);
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $item_list->getIndex()->indexItems();

    // Create facets for categories.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $facet_categories = $this->createFacet('category', $default_list_id, '', 'oe_list_pages_multiselect', []);

    // Search for categories.
    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $list->getQuery(['preset_filters' => [$facet_categories->id() => 'cat1']]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(3, $results->getResultItems());

    $query = $list->getQuery(['preset_filters' => [$facet_categories->id() => 'cat2']]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(1, $results->getResultItems());
  }

}
