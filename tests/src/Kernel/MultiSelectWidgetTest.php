<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\widget\MultiselectWidget;

/**
 * Test for Multiselect widget.
 */
class MultiSelectWidgetTest extends ListsSourceTestBase {

  /**
   * The widget.
   *
   * @var \Drupal\oe_list_pages\Plugin\facets\widget\MultiselectWidget
   */
  protected $widget;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->widget = new MultiselectWidget([], 'oe_list_pages_multiselect', [], $this->entityTypeManager, $this->container->get('entity_field.manager'), $this->container->get('plugin.manager.multiselect_filter_field'), $this->container->get('plugin.manager.facets.processor'));
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
    $facet_keywords = $this->createFacet('keywords', $default_list_id, '', 'oe_list_pages_multiselect', []);

    // Search for categories.
    // KEY1.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_categories->id()) => new ListPresetFilter($facet_categories->id(), ['cat1'], ListPresetFilter::OR_OPERATOR),
      ],
      'results' => 3,
    ];

    // KEY2.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_categories->id()) => new ListPresetFilter($facet_categories->id(), ['cat2'], ListPresetFilter::OR_OPERATOR),
      ],
      'results' => 1,
    ];

    // KEY1 AND KEY2.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id()) => new ListPresetFilter($facet_keywords->id(), [
          'key1',
          'key2',
        ], ListPresetFilter::AND_OPERATOR),
      ],
      'results' => 1,
    ];
    // KEY1 OR KEY2.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id()) => new ListPresetFilter($facet_keywords->id(), [
          'key1',
          'key2',
        ], ListPresetFilter::OR_OPERATOR),
      ],
      'results' => 4,
    ];
    // NOT KEY1.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id()) => new ListPresetFilter($facet_keywords->id(), ['key1'], ListPresetFilter::NOT_OPERATOR),
      ],
      'results' => 2,
    ];
    // KEY1 AND NOT KEY2.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id()) => new ListPresetFilter($facet_keywords->id(), ['key1'], ListPresetFilter::OR_OPERATOR),
        DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id()) => new ListPresetFilter($facet_keywords->id(), ['key2'], ListPresetFilter::NOT_OPERATOR),
      ],
      'results' => 1,
    ];
    // KEY1 AND NOT (KEY2 OR KEY3)
    $filters = [];
    $filters[DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id())] = new ListPresetFilter($facet_keywords->id(), ['key1'], ListPresetFilter::AND_OPERATOR);
    $filters[DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id(), array_keys($filters))] = new ListPresetFilter($facet_keywords->id(), [
      'key2',
      'key3',
    ], ListPresetFilter::NOT_OPERATOR);
    $expected_key_results[] = [
      'filters' => $filters,
      'results' => 1,
    ];
    // KEY1 AND (KEY2 OR KEY3)
    $filters = [];
    $filters[DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id())] = new ListPresetFilter($facet_keywords->id(), ['key1'], ListPresetFilter::OR_OPERATOR);
    $filters[DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id(), array_keys($filters))] = new ListPresetFilter($facet_keywords->id(), [
      'key2',
      'key3',
    ], ListPresetFilter::OR_OPERATOR);
    $expected_key_results[] = [
      'filters' => $filters,
      'results' => 1,
    ];
    // KEY2 AND (KEY2 OR KEY3)
    $filters = [];
    $filters[DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id())] = new ListPresetFilter($facet_keywords->id(), ['key2'], ListPresetFilter::OR_OPERATOR);
    $filters[DefaultFilterConfigurationBuilder::generateFilterId($facet_keywords->id(), array_keys($filters))] = new ListPresetFilter($facet_keywords->id(), [
      'key2',
      'key3',
    ], ListPresetFilter::OR_OPERATOR);
    $expected_key_results[] = [
      'filters' => $filters,
      'results' => 3,
    ];

    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    foreach ($expected_key_results as $category) {
      $this->container->get('kernel')->rebuildContainer();
      $query = $list->getQuery(['preset_filters' => $category['filters']]);
      $query->execute();
      $results = $query->getResults();
      $this->assertCount($category['results'], $results->getResultItems());
    }
  }

}
