<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\widget\ListPagesDateWidget;

/**
 * Test for Multiselect widget.
 */
class DateWidgetTest extends ListsSourceBaseTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'datetime',
  ];

  /**
   * The widget.
   *
   * @var \Drupal\oe_list_pages\Plugin\facets\widget\ListPagesMultiselectWidget
   */
  protected $widget;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->widget = new ListPagesDateWidget([], 'oe_list_pages_date', []);
  }

  /**
   * Tests widget value conversion.
   */
  public function testWidgetFormValue(): void {
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');

    $facet_active = $this->createFacet('created', $default_list_id, '', 'oe_list_pages_date', ['date_type' => DateTimeItem::DATETIME_TYPE_DATE]);
    $facet_active->setActiveItems(['bt', '14/08/2020', '21/08/2020']);

    $build = $this->widget->build($facet_active);
    $actual = $build[$facet_active->id() . '_op'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('select', $actual['#type']);
    $this->assertEquals('bt', $actual['#default_value']);

    $actual = $build[$facet_active->id() . '_date'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('date', $actual['#type']);
    $this->assertEquals('14/08/2020', $actual['#default_value']);

    $actual = $build[$facet_active->id() . '_end_date'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('date', $actual['#type']);
    $this->assertEquals('21/08/2020', $actual['#default_value']);

    // Test widget if we choose not 'In between' operator.
    foreach (['gt', 'lt'] as $operator_key) {
      $facet_active->setActiveItems([$operator_key, '14/08/2020', '21/08/2020']);
      $build = $this->widget->build($facet_active);
      $actual = $build[$facet_active->id() . '_op'];
      $this->assertSame('array', gettype($actual));
      $this->assertEquals('select', $actual['#type']);
      $this->assertEquals($operator_key, $actual['#default_value']);

      $actual = $build[$facet_active->id() . '_date'];
      $this->assertSame('array', gettype($actual));
      $this->assertEquals('date', $actual['#type']);
      $this->assertEquals('14/08/2020', $actual['#default_value']);

      $actual = $build[$facet_active->id() . '_end_date'];
      $this->assertSame('array', gettype($actual));
      $this->assertEquals('date', $actual['#type']);
      $this->assertEmpty($actual['#default_value']);
    }

    // Widget for with datetime type.
    $facet_active = $this->createFacet('created', $default_list_id, 'datetime', 'oe_list_pages_date', ['date_type' => DateTimeItem::DATETIME_TYPE_DATETIME]);
    $facet_active->setActiveItems(['bt', '14/08/2020', '21/08/2020']);

    $build = $this->widget->build($facet_active);
    $actual = $build[$facet_active->id() . '_op'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('select', $actual['#type']);
    $this->assertEquals('bt', $actual['#default_value']);

    $actual = $build[$facet_active->id() . '_date'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('datetime', $actual['#type']);
    $this->assertEquals('14/08/2020', $actual['#default_value']);

    $actual = $build[$facet_active->id() . '_end_date'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('datetime', $actual['#type']);
    $this->assertEquals('21/08/2020', $actual['#default_value']);

    // Now without active result.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $facet_inactive = $this->createFacet('created', $default_list_id, 'inactive', 'oe_list_pages_date', ['date_type' => DateTimeItem::DATETIME_TYPE_DATE]);
    $build = $this->widget->build($facet_inactive);
    $form_elements = [
      $facet_inactive->id() . '_op' => 'select',
      $facet_inactive->id() . '_date' => 'date',
      $facet_inactive->id() . '_end_date' => 'date',
    ];
    foreach ($form_elements as $form_key => $field_type) {
      $actual = $build[$form_key];
      $this->assertSame('array', gettype($actual));
      $this->assertEquals($field_type, $actual['#type']);
      $this->assertEmpty($actual['#default_value']);
    }
  }

  /**
   * Tests query type using the active filter.
   */
  public function testQueryType(): void {
    $this->createTestContent('item', 4);
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $item_list->getIndex()->indexItems();

    // Create facets for dates.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $facet_date = $this->createFacet('created', $default_list_id, '', 'oe_list_pages_date');

    // Search by date.
    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    foreach ($this->filtersData() as $message => $data) {
      /** @var \Drupal\search_api\Query\QueryInterface $query */
      $query = $list->getQuery(0, 0, [], [
        $facet_date->id() => array_values($data['filters']),
      ]);
      $query->execute();
      $results = $query->getResults();
      $this->assertCount($data['expected_count'], $results->getResultItems(), $message);
    }
  }

  /**
   * Tests a default configuration.
   */
  public function testDefaultConfiguration(): void {
    $default_config = $this->widget->defaultConfiguration();
    $this->assertArrayHasKey('date_type', $default_config);
    $this->assertEqual($default_config['date_type'], 'date');
  }

  /**
   * Test data for filters.
   *
   * @return array
   *   The array of test data.
   */
  protected function filtersData(): array {
    return [
      'range of dates' => [
        'filters' => [
          'operator' => 'bt',
          'start_date' => '2020-08-01',
          'end_date' => '2020-08-31',
        ],
        'expected_count' => 4,
      ],
      'range of dates starting from the date of the first end to the last item' => [
        'filters' => [
          'operator' => 'bt',
          'start_date' => '2020-08-06',
          'end_date' => '2020-08-27',
        ],
        'expected_count' => 4,
      ],
      'range of dates which should include 2 items in the middle' => [
        'filters' => [
          'operator' => 'bt',
          'start_date' => '2020-08-13',
          'end_date' => '2020-08-20',
        ],
        'expected_count' => 2,
      ],
      'items after beginning of the month' => [
        'filters' => [
          'operator' => 'gt',
          'start_date' => '2020-08-01',
        ],
        'expected_count' => 4,
      ],
      'items after first item' => [
        'filters' => [
          'operator' => 'gt',
          'start_date' => '2020-08-06',
        ],
        'expected_count' => 3,
      ],
      'filtering after the last item' => [
        'filters' => [
          'operator' => 'gt',
          'start_date' => '2020-08-27',
        ],
        'expected_count' => 0,
      ],
      'items before end of month' => [
        'filters' => [
          'operator' => 'lt',
          'start_date' => '2020-08-31',
        ],
        'expected_count' => 4,
      ],
      'items before last item' => [
        'filters' => [
          'operator' => 'lt',
          'start_date' => '2020-08-27',
        ],
        'expected_count' => 3,
      ],
      'items before first item' => [
        'filters' => [
          'operator' => 'lt',
          'start_date' => '2020-08-06',
        ],
        'expected_count' => 0,
      ],
    ];
  }

}
