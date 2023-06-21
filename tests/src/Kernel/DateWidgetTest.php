<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\query_type\Date;
use Drupal\oe_list_pages\Plugin\facets\widget\DateWidget;

/**
 * Test for Multiselect widget.
 */
class DateWidgetTest extends ListsSourceTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

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

    $this->widget = new DateWidget([], 'oe_list_pages_date', []);
  }

  /**
   * Tests widget value conversion.
   */
  public function testWidgetFormValue(): void {
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');

    $facet_active = $this->createFacet('created', $default_list_id, '', 'oe_list_pages_date', ['date_type' => Date::DATETIME_TYPE_DATE]);
    // Set the date in ATOM format as submitted also by the widget.
    $facet_active->setActiveItems([
      'bt|2020-08-14T15:26:45+02:00|2020-08-21T15:26:45+02:00',
    ]);

    $build = $this->widget->build($facet_active);
    $actual = $build[$facet_active->id() . '_op'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('select', $actual['#type']);
    $this->assertEquals('bt', $actual['#default_value']);

    $actual = $build[$facet_active->id() . '_first_date_wrapper'][$facet_active->id() . '_first_date'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('datetime', $actual['#type']);
    $this->assertEquals('date', $actual['#date_date_element']);
    $this->assertEquals('none', $actual['#date_time_element']);
    $this->assertEquals('14/08/2020', $actual['#default_value']->format('d/m/Y'));

    $actual = $build[$facet_active->id() . '_second_date_wrapper'][$facet_active->id() . '_second_date'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('datetime', $actual['#type']);
    $this->assertEquals('date', $actual['#date_date_element']);
    $this->assertEquals('none', $actual['#date_time_element']);
    $this->assertEquals('21/08/2020', $actual['#default_value']->format('d/m/Y'));

    // Test widget if we choose the greater than or less than operator.
    foreach (['gt', 'lt'] as $operator_key) {
      $facet_active->setActiveItems([
        "$operator_key|2020-08-14T15:26:45+02:00",
      ]);
      $build = $this->widget->build($facet_active);
      $actual = $build[$facet_active->id() . '_op'];
      $this->assertSame('array', gettype($actual));
      $this->assertEquals('select', $actual['#type']);
      $this->assertEquals($operator_key, $actual['#default_value']);

      $actual = $build[$facet_active->id() . '_first_date_wrapper'][$facet_active->id() . '_first_date'];
      $this->assertSame('array', gettype($actual));
      $this->assertEquals('datetime', $actual['#type']);
      $this->assertEquals('date', $actual['#date_date_element']);
      $this->assertEquals('none', $actual['#date_time_element']);
      $this->assertEquals('14/08/2020', $actual['#default_value']->format('d/m/Y'));

      // The second date element should be there in the form but hidden via
      // #states.
      $actual = $build[$facet_active->id() . '_second_date_wrapper'][$facet_active->id() . '_second_date'];
      $this->assertSame('array', gettype($actual));
      $this->assertEquals('datetime', $actual['#type']);
      $this->assertEquals('date', $actual['#date_date_element']);
      $this->assertEquals('none', $actual['#date_time_element']);
      $this->assertEmpty($actual['#default_value']);
    }

    // Test the widget form with datetime type.
    $facet_active = $this->createFacet('created', $default_list_id, 'datetime', 'oe_list_pages_date', ['date_type' => Date::DATETIME_TYPE_DATETIME]);
    $facet_active->setActiveItems([
      'bt|2020-08-14T15:26:45+02:00|2020-08-21T15:26:45+02:00',
    ]);

    $build = $this->widget->build($facet_active);
    $actual = $build[$facet_active->id() . '_op'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('select', $actual['#type']);
    $this->assertEquals('bt', $actual['#default_value']);

    $actual = $build[$facet_active->id() . '_first_date_wrapper'][$facet_active->id() . '_first_date'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('datetime', $actual['#type']);
    $this->assertEquals('date', $actual['#date_date_element']);
    $this->assertEquals('time', $actual['#date_time_element']);
    $this->assertEquals('14/08/2020', $actual['#default_value']->format('d/m/Y'));

    $actual = $build[$facet_active->id() . '_second_date_wrapper'][$facet_active->id() . '_second_date'];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('datetime', $actual['#type']);
    $this->assertEquals('date', $actual['#date_date_element']);
    $this->assertEquals('time', $actual['#date_time_element']);
    $this->assertEquals('21/08/2020', $actual['#default_value']->format('d/m/Y'));

    // Now without active result.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $facet_inactive = $this->createFacet('created', $default_list_id, 'inactive', 'oe_list_pages_date', ['date_type' => Date::DATETIME_TYPE_DATE]);
    $build = $this->widget->build($facet_inactive);
    $form_elements = [
      $facet_inactive->id() . '_first_date' => 'datetime',
      $facet_inactive->id() . '_second_date' => 'datetime',
    ];
    foreach ($form_elements as $form_key => $field_type) {
      $actual = $build[$form_key . '_wrapper'][$form_key];
      $this->assertSame('array', gettype($actual));
      $this->assertEquals($field_type, $actual['#type']);
      $this->assertEmpty($actual['#default_value']);
    }
  }

  /**
   * Tests the date query type using the active filter.
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
    foreach ($this->getTestFilterDateData() as $message => $data) {
      /** @var \Drupal\search_api\Query\QueryInterface $query */
      $this->container->get('kernel')->rebuildContainer();
      $filter = new ListPresetFilter($facet_date->id(), [implode('|', $data['filters'])]);
      $query = $list->getQuery(['preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($facet_date->id()) => $filter]]);
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
    $this->assertEquals('date', $default_config['date_type']);
  }

  /**
   * Returns an array of test data for filters.
   *
   * @return array
   *   The array of test data.
   */
  protected function getTestFilterDateData(): array {
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
