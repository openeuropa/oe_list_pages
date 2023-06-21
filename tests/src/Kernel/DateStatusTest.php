<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\query_type\DateStatus;

/**
 * Test for status date processor and query type.
 */
class DateStatusTest extends ListsSourceTestBase {

  /**
   * The facet without processor configuration.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected $facet;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $this->facet = $this->createFacet('created', $default_list_id, '', 'oe_list_pages_multiselect', []);
    $this->facet->addProcessor([
      'processor_id' => 'oe_list_pages_date_status_processor',
      'weights' => [],
      'settings' => [],
    ]);

    $this->facet->save();
  }

  /**
   * Tests the widget with a configured default value.
   */
  public function testWithConfiguredDefaults(): void {
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    // Configuration options for the processor.
    $processor_options = [
      'default_status' => DateStatus::UPCOMING,
      'upcoming_label' => 'Coming items',
      'past_label' => 'Past items',
    ];
    $facet_with_config = $this->createFacet('created', $default_list_id, 'options', 'oe_list_pages_multiselect', []);
    $facet_with_config->addProcessor([
      'processor_id' => 'oe_list_pages_date_status_processor',
      'weights' => ['pre_query' => 60, 'build' => 35],
      'settings' => $processor_options,
    ]);
    $facet_with_config->save();

    // Run the query before building the widget as the query subscriber puts
    // the default values onto the query.
    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $list->getQuery();
    $query->execute();

    $build = $this->facetManager->build($this->facet);
    $actual = $build[0][$this->facet->id()];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('select', $actual['#type']);
    $default_options = [
      DateStatus::UPCOMING => t('Upcoming'),
      DateStatus::PAST => t('Past'),
    ];
    // Assert the query has the corresponding cache tag from the facet.
    $expected_tag = 'config:facets.facet.list_facet_source_entity_test_mulrev_changed_itemcreated';
    $this->assertContains($expected_tag, $this->facet->getCacheTags());
    $this->assertContains($expected_tag, $query->getCacheTags());

    $this->assertEquals($default_options, $actual['#options']);
    $this->assertEquals([], $actual['#default_value']);

    $build = $this->facetManager->build($facet_with_config);
    $actual = $build[0][$facet_with_config->id()];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('select', $actual['#type']);
    $default_options = [
      DateStatus::UPCOMING => t('Coming items'),
      DateStatus::PAST => t('Past items'),
    ];
    // Assert the query has the corresponding cache tag from the facet.
    $expected_tag = 'config:facets.facet.list_facet_source_entity_test_mulrev_changed_itemcreated';
    $this->assertContains($expected_tag, $this->facet->getCacheTags());
    $this->assertContains($expected_tag, $query->getCacheTags());

    $this->assertEquals($default_options, $actual['#options']);
    $this->assertEquals([DateStatus::UPCOMING], $actual['#default_value']);
  }

  /**
   * Tests query type using the active filter.
   */
  public function testQueryType(): void {
    $values = [];
    $values['dates'] = [
      strtotime('-2 days'),
      strtotime('-1 days'),
      strtotime('+1 days'),
      strtotime('+2 days'),
    ];
    $values['titles'] = [
      'oldest',
      'old',
      'tomorrow',
      'future',
    ];
    $this->createTestContent('item', 4, $values);
    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $list->getIndex()->indexItems();

    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $list->getQuery(['sort' => ['created' => 'DESC']]);
    $query->execute();
    $results = $query->getResults();

    // We have no facet configuration so we get all results.
    $this->assertCount(4, $results->getResultItems());

    // Assert that the query has been sorted by created DESC.
    $this->assertSort($results->getResultItems(), [
      'future',
      'tomorrow',
      'old',
      'oldest',
    ]);

    $filter = new ListPresetFilter($this->facet->id(), [DateStatus::PAST]);
    $query = $list->getQuery([
      'preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($this->facet->id()) => $filter],
      'sort' => ['created' => 'DESC'],
    ]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(2, $results->getResultItems());
    // The facet field identifier matches the configured query sort, to for
    // past items, the sort has been kept as DESC.
    $this->assertSort($results->getResultItems(), [
      'old',
      'oldest',
    ]);

    $this->container->get('kernel')->rebuildContainer();
    $filter = new ListPresetFilter($this->facet->id(), [DateStatus::UPCOMING]);
    $query = $list->getQuery([
      'preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($this->facet->id()) => $filter],
      'sort' => ['created' => 'DESC'],
    ]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(2, $results->getResultItems());
    // The facet field identifier matches the configured query sort, to for
    // upcoming items, the sort has altered as ASC.
    $this->assertSort($results->getResultItems(), [
      'tomorrow',
      'future',
    ]);

    $this->container->get('kernel')->rebuildContainer();
    $filter = new ListPresetFilter($this->facet->id(), [
      DateStatus::PAST,
      DateStatus::UPCOMING,
    ]);

    $query = $list->getQuery([
      'preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($this->facet->id()) => $filter],
      'sort' => ['created' => 'DESC'],
    ]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(4, $results->getResultItems());
    // The facet field identifier matches the configured query sort, to for
    // both past and upcoming items, the sort has been kept as DESC.
    $this->assertSort($results->getResultItems(), [
      'future',
      'tomorrow',
      'old',
      'oldest',
    ]);

    // Update the processor config to use the body field as the sort alter
    // field.
    $configs = $this->facet->getProcessorConfigs();
    $configs['oe_list_pages_date_status_processor']['settings']['sort_alter_field_identifier'] = 'name';
    $this->facet->set('processor_configs', $configs);
    $this->facet->save();

    $this->container->get('kernel')->rebuildContainer();
    $filter = new ListPresetFilter($this->facet->id(), [DateStatus::UPCOMING]);
    $query = $list->getQuery([
      'preset_filters' => [DefaultFilterConfigurationBuilder::generateFilterId($this->facet->id()) => $filter],
      'sort' => ['name' => 'DESC'],
    ]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(2, $results->getResultItems());
    // The query sort field is now different so assert that the query gets
    // filtered by the correct date field (so show only upcoming items) but the
    // sort alter is applied to the configured query sort. This means that it
    // will be sorted by name ASC.
    $this->assertSort($results->getResultItems(), [
      'future',
      'tomorrow',
    ]);
  }

  /**
   * Asserts the results are sorted in the correct order.
   *
   * @param \Drupal\search_api\Item\ItemInterface[] $results
   *   The results.
   * @param array $expected_titles
   *   The expected titles in the correct order.
   */
  protected function assertSort(array $results, array $expected_titles): void {
    $titles = [];
    foreach ($results as $result) {
      $titles[] = $result->getOriginalObject()->getValue()->label();
    }

    $this->assertEquals($expected_titles, $titles);
  }

}
