<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\widget\FulltextWidget;

/**
 * Test for Fulltext widget and query type.
 */
class FulltextWidgetTest extends ListsSourceTestBase {

  /**
   * The widget.
   *
   * @var \Drupal\oe_list_pages\Plugin\facets\widget\FulltextWidget
   */
  protected $widget;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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

    // Add another one with the body in a Cyrillic language.
    $entity_test_storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed');
    $entity_test_storage->create([
      'name' => 'Това е банан',
      'body' => 'Това е банан',
      'type' => 'item',
    ])->save();

    // Another list for another bundle.
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');

    $ignorecase_processor = \Drupal::getContainer()->get('search_api.plugin_helper')->createProcessorPlugin($item_list->getIndex(), 'ignorecase', ['all_fields' => TRUE]);
    $item_list->getIndex()->addProcessor($ignorecase_processor);
    $item_list->getIndex()->save();
    $item_list->getIndex()->indexItems();

    // Create facets for body and name.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $facet_body = $this->createFacet('body', $default_list_id, '', 'oe_list_pages_fulltext', ['fulltext_all_fields' => TRUE]);
    $facet_name = $this->createFacet('name', $default_list_id, '', 'oe_list_pages_fulltext', ['fulltext_all_fields' => FALSE]);

    // Search for body.
    $this->container->get('kernel')->rebuildContainer();
    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $filter = new ListPresetFilter($facet_body->id(), ['message']);
    $query = $list->getQuery(['preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($facet_body->id()) => $filter]]);
    $query->execute();
    $results = $query->getResults();
    // Asserts results.
    $this->assertCount(3, $results->getResultItems());

    // Search for multi filters.
    $this->container->get('kernel')->rebuildContainer();
    $multi_filters = [];
    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $filter_message = new ListPresetFilter($facet_name->id(), ['message']);
    $filter_message_id = DefaultFilterConfigurationBuilder::generateFilterId($facet_name->id());
    $multi_filters[$filter_message_id] = $filter_message;
    $filter_with = new ListPresetFilter($facet_name->id(), ['with']);
    $filter_with_id = DefaultFilterConfigurationBuilder::generateFilterId($facet_name->id(), array_keys($multi_filters));
    $multi_filters[$filter_with_id] = $filter_with;
    $query = $list->getQuery(['preset_filters' => $multi_filters]);
    $query->execute();
    $results = $query->getResults();
    // Asserts results.
    $this->assertCount(1, $results->getResultItems());

    // Search for body with Uppercase.
    $this->container->get('kernel')->rebuildContainer();
    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $filter = new ListPresetFilter($facet_body->id(), ['Message']);
    $query = $list->getQuery(['preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($facet_body->id()) => $filter]]);
    $query->execute();
    $results = $query->getResults();
    // Asserts results.
    $this->assertCount(3, $results->getResultItems());

    // Search for name.
    $this->container->get('kernel')->rebuildContainer();
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $filter = new ListPresetFilter($facet_name->id(), ['message']);
    $query = $list->getQuery(['preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($facet_name->id()) => $filter]]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(1, $results->getResultItems());

    // Search in Cyrillic with Uppercase.
    $this->container->get('kernel')->rebuildContainer();
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $filter = new ListPresetFilter($facet_name->id(), ['message']);
    $query = $list->getQuery(['preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($facet_name->id()) => $filter]]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(1, $results->getResultItems());

    // Search in Cyrillic with lowercase.
    $this->container->get('kernel')->rebuildContainer();
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $filter = new ListPresetFilter($facet_name->id(), ['това е банан']);
    $query = $list->getQuery(['preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($facet_name->id()) => $filter]]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(1, $results->getResultItems());

    // Search for non-existing.
    $this->container->get('kernel')->rebuildContainer();
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $filter = new ListPresetFilter($facet_name->id(), ['not found']);
    $query = $list->getQuery(['preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($facet_name->id()) => $filter]]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(0, $results->getResultItems());
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
