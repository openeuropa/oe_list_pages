<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\widget\FulltextWidget;

/**
 * Test for Fulltext widget and query type.
 */
class FulltextWidgetTest extends ListsSourceBaseTest {

  /**
   * The widget.
   *
   * @var \Drupal\oe_list_pages\Plugin\facets\widget\FulltextWidget
   */
  protected $widget;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->widget = new FulltextWidget(['fulltext_all_fields' => TRUE], 'oe_list_pages_fulltext', []);
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
    $this->assertEquals('textfield', $output['#type']);
    $this->assertEquals('message', $output['#default_value']);

    // Now without active result.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed', 'inactive');
    $facet_inactive = $this->createFacet('body', $default_list_id, 'inactive');
    $output = $this->widget->build($facet_inactive)[$facet_inactive->id()];
    $this->assertSame('array', gettype($output));
    $this->assertEquals('textfield', $output['#type']);
    $this->assertEmpty($output['#default_value']);
  }

  /**
   * Tests query type with and without fulltext in all fields.
   */
  public function testQueryType(): void {
    $this->createTestContent('item', 4);
    // Another list for another bundle.
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $item_list->getIndex()->indexItems();

    // Create facets for body and name.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $facet_body = $this->createFacet('body', $default_list_id, '', 'oe_list_pages_fulltext', ['fulltext_all_fields' => TRUE]);
    $facet_name = $this->createFacet('name', $default_list_id, '', 'oe_list_pages_fulltext', ['fulltext_all_fields' => FALSE]);

    // Search for body.
    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $list->getQuery(0, 0, NULL, [], [], [$facet_body->id() => 'message']);
    $query->execute();
    $results = $query->getResults();

    // Asserts results.
    $this->assertCount(3, $results->getResultItems());

    // Search for name.
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $list->getQuery(0, 0, NULL, [], [], [$facet_name->id() => 'message']);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(1, $results->getResultItems());
  }

  /**
   * Tests query type.
   */
  public function testGetQueryType(): void {
    $result = $this->widget->getQueryType();
    $this->assertEquals('fulltext_comparison', $result);
  }

  /**
   * Tests a default configuration.
   */
  public function testDefaultConfiguration(): void {
    $default_config = $this->widget->defaultConfiguration();
    $this->assertArrayHasKey('fulltext_all_fields', $default_config);
    $this->assertTrue($default_config['fulltext_all_fields']);
  }

}
