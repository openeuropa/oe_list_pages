<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\processor\DateStatusProcessor;
use Drupal\oe_list_pages\Plugin\facets\query_type\DateStatusQueryType;

/**
 * Test for status date processor and query type.
 */
class StatusDateTest extends ListsSourceBaseTest {

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
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $this->facet_active = $this->createFacet('created', $default_list_id, '', 'oe_list_pages_multiselect', []);
    $this->facet_active->addProcessor([
      'processor_id' => 'oe_list_pages_date_status_processor',
      'weights' => [],
      'settings' => [],
    ]);
    $this->facet_active->save();
  }

  /**
   * Tests widget with status processor.
   */
  public function testWidgetFormValue(): void {

    $build = $this->facetManager->build($this->facet_active);
    $actual = $build[0][$this->facet_active->id()];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('select', $actual['#type']);
    $this->assertEquals(DateStatusProcessor::defaultOptions(), $actual['#options']);
  }

  /**
   * Tests query type using the active filter.
   */
  public function testQueryType(): void {
    $values = [];
    $values['dates'] = [
      strtotime('-2 days'),
      strtotime('-1 days'),
      time(),
      strtotime('+1 days'),
    ];
    $this->createTestContent('item', 4, $values);
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $item_list->getIndex()->indexItems();

    // Search for categories.
    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $list->getQuery(0, 0, [], [], []);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(1, $results->getResultItems());

    $query = $list->getQuery(0, 0, [], [], [$this->facet_active->id() => [DateStatusQueryType::PAST]]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(3, $results->getResultItems());

    $query = $list->getQuery(0, 0, [], [], [$this->facet_active->id() => [DateStatusQueryType::UPCOMING]]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(1, $results->getResultItems());

    $query = $list->getQuery(0, 0, [], [], [$this->facet_active->id() => [DateStatusQueryType::PAST, DateStatusQueryType::UPCOMING]]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(4, $results->getResultItems());
  }

}
