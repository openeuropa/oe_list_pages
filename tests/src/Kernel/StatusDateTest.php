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
   * The facet without processor configuration.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected $facet_no_config;

  /**
   * The facet with processor configuration.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected $facet_with_config;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $this->facet_no_config = $this->createFacet('created', $default_list_id, '', 'oe_list_pages_multiselect', []);
    $this->facet_no_config->addProcessor([
      'processor_id' => 'oe_list_pages_date_status_processor',
      'weights' => [],
      'settings' => [
      ],
    ]);

    $this->facet_no_config->save();
  }

  /**
   * Tests widget with status processor.
   */
  public function testWidgetFormValue(): void {
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    // Configuration options for the processor.
    $processor_options = [
      'default_status' => DateStatusQueryType::UPCOMING,
      'upcoming_label' => 'Coming items',
      'past_label' => 'Past items'
    ];
    $this->facet_with_config = $this->createFacet('created', $default_list_id, 'options', 'oe_list_pages_multiselect', []);
    $this->facet_with_config->addProcessor([
      'processor_id' => 'oe_list_pages_date_status_processor',
      'weights' => ['pre_query' => 60, 'build' => 35],
      'settings' => $processor_options,
    ]);
    $this->facet_with_config->save();

    $build = $this->facetManager->build($this->facet_no_config);
    $actual = $build[0][$this->facet_no_config->id()];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('select', $actual['#type']);
    $default_options = [
      '' => t('- None -'),
      DateStatusQueryType::UPCOMING => t('Upcoming'),
      DateStatusQueryType::PAST => t('Past'),
    ];

    $this->assertEquals($default_options, $actual['#options']);
    $this->assertEquals([], $actual['#default_value']);
    $build = $this->facetManager->build($this->facet_with_config);
    $actual = $build[0][$this->facet_with_config->id()];
    $this->assertSame('array', gettype($actual));
    $this->assertEquals('select', $actual['#type']);

    $default_options = [
      '' => t('- None -'),
      DateStatusQueryType::UPCOMING => t('Coming items'),
      DateStatusQueryType::PAST => t('Past items'),
    ];

    $this->assertEquals($default_options, $actual['#options']);
    $this->assertEquals([DateStatusQueryType::UPCOMING], $actual['#default_value']);
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
    $this->assertCount(4, $results->getResultItems());

    $query = $list->getQuery(0, 0, [], [], [$this->facet_no_config->id() => [DateStatusQueryType::PAST]]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(3, $results->getResultItems());

    $query = $list->getQuery(0, 0, [], [], [$this->facet_no_config->id() => [DateStatusQueryType::UPCOMING]]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(1, $results->getResultItems());

    $query = $list->getQuery(0, 0, [], [], [$this->facet_no_config->id() => [DateStatusQueryType::PAST, DateStatusQueryType::UPCOMING]]);
    $query->execute();
    $results = $query->getResults();
    $this->assertCount(4, $results->getResultItems());
  }

}
