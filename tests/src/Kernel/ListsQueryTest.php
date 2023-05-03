<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\facets\Entity\Facet;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\search_api\Item\Field;
use Drupal\search_api\Query\ConditionGroup;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\Query\QueryInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

/**
 * Tests the List sources querying functionality.
 */
class ListsQueryTest extends ListsSourceTestBase {

  /**
   * The default list to test.
   *
   * @var \Drupal\oe_list_pages\ListSourceInterface|null
   */
  protected $list;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createTestFacets();
    $this->createTestContent('entity_test_mulrev_changed', 5);
    // Get the list.
    $this->list = $this->listFactory->get('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    // Index items.
    $this->list->getIndex()->indexItems();
  }

  /**
   * Tests the query functionality without filters.
   */
  public function testQuery(): void {
    $this->createTestContent('item', 6);
    // Another list for another bundle.
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $item_list->getIndex()->indexItems();

    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $default_query = $this->list->getQuery();
    $default_query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $default_results = $default_query->getResults();

    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $item_query = $item_list->getQuery(['limit' => 2]);
    $item_query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $item_results = $item_query->getResults();

    // Asserts results.
    $this->assertEquals(5, $default_results->getResultCount());
    $this->assertEquals(6, $item_results->getResultCount());

    $this->assertCount(5, $default_results->getResultItems());
    $this->assertCount(2, $item_results->getResultItems());

    $default_facets = $default_results->getExtraData('search_api_facets');
    $expected_facets_category = [
      [
        'count' => 2,
        'filter' => '"second class"',
      ],
      [
        'count' => 2,
        'filter' => '"third class"',
      ],
      [
        'count' => 1,
        'filter' => '"first class"',
      ],
    ];

    $this->assertEquals($expected_facets_category, $default_facets['category']);
  }

  /**
   * Tests the query functionality for multilingual fallback.
   */
  public function testQueryMultilingual(): void {
    ConfigurableLanguage::createFromLangcode('es')->save();
    ConfigurableLanguage::createFromLangcode('pt-pt')->save();

    // Another list for another bundle.
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $index = $item_list->getIndex();
    $field_lang = new Field($index, 'language_with_fallback');
    $field_lang->setType('string');
    $field_lang->setPropertyPath('language_with_fallback');
    $field_lang->setLabel('Language (with fallback)');
    $index->addField($field_lang);
    $processor = \Drupal::getContainer()
      ->get('search_api.plugin_helper')
      ->createProcessorPlugin($this->index, 'language_with_fallback');
    $index->addProcessor($processor);
    $index->setOption('index_directly', TRUE);
    $index->save();

    // Create test content and index it.
    $this->createTranslatedTestContent('item');
    $index->indexItems();

    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $item_query_no_lang = $item_list->getQuery();
    $item_query_en = $item_list->getQuery(['language' => 'en']);
    $item_query_es = $item_list->getQuery(['language' => 'es']);
    $item_query_pt = $item_list->getQuery(['language' => 'pt-pt']);

    $item_query_no_lang->execute();
    $item_query_en->execute();
    $item_query_es->execute();
    $item_query_pt->execute();

    /** @var \Drupal\search_api\Query\ResultSetInterface $item_results_no_lang */
    $item_results_no_lang = $item_query_no_lang->getResults();
    /** @var \Drupal\search_api\Query\ResultSetInterface $item_results_en */
    $item_results_en = $item_query_en->getResults();
    /** @var \Drupal\search_api\Query\ResultSetInterface $item_results_es */
    $item_results_es = $item_query_es->getResults();
    /** @var \Drupal\search_api\Query\ResultSetInterface $item_results_pt */
    $item_results_pt = $item_query_pt->getResults();

    // Assert result counts.
    $this->assertEquals(6, $item_results_no_lang->getResultCount());
    $this->assertCount(6, $item_results_no_lang->getResultItems());

    $this->assertEquals(4, $item_results_en->getResultCount());
    $this->assertCount(4, $item_results_en->getResultItems());

    $this->assertEquals(4, $item_results_es->getResultCount());
    $this->assertCount(4, $item_results_es->getResultItems());

    $this->assertEquals(4, $item_results_pt->getResultCount());
    $this->assertCount(4, $item_results_pt->getResultItems());

    // Assert that the results are coming in the right languages.
    $expected_no_lang = [
      ['en' => 'first'],
      ['es' => 'primero'],
      ['en' => 'second'],
      ['es' => 'segundo'],
      ['en' => 'third'],
      ['pt-pt' => 'Portugues'],
    ];
    $actual_no_lang = [];
    foreach ($item_results_no_lang->getResultItems() as $item) {
      $entity = $item->getOriginalObject()->getEntity();
      $actual_no_lang[] = [
        $entity->language()->getId() => $entity->label(),
      ];
    }

    $this->assertEquals($expected_no_lang, $actual_no_lang);

    $expected_en = [
      ['en' => 'first'],
      ['en' => 'second'],
      ['en' => 'third'],
      ['pt-pt' => 'Portugues'],
    ];
    $actual_en = [];
    foreach ($item_results_en->getResultItems() as $item) {
      $entity = $item->getOriginalObject()->getEntity();
      $actual_en[] = [
        $entity->language()->getId() => $entity->label(),
      ];
    }

    $this->assertEquals($expected_en, $actual_en);

    $expected_es = [
      ['es' => 'primero'],
      ['es' => 'segundo'],
      ['en' => 'third'],
      ['pt-pt' => 'Portugues'],
    ];
    $actual_es = [];
    foreach ($item_results_es->getResultItems() as $item) {
      $entity = $item->getOriginalObject()->getEntity();
      $actual_es[] = [
        $entity->language()->getId() => $entity->label(),
      ];
    }

    $this->assertEquals($expected_es, $actual_es);

    $expected_pt = [
      ['en' => 'first'],
      ['en' => 'second'],
      ['en' => 'third'],
      ['pt-pt' => 'Portugues'],
    ];
    $actual_pt = [];
    foreach ($item_results_pt->getResultItems() as $item) {
      $entity = $item->getOriginalObject()->getEntity();
      $actual_pt[] = [
        $entity->language()->getId() => $entity->label(),
      ];
    }

    $this->assertEquals($expected_pt, $actual_pt);

    $facets_en = $item_results_en->getExtraData('search_api_facets');
    $facets_es = $item_results_es->getExtraData('search_api_facets');
    $facets_pt = $item_results_pt->getExtraData('search_api_facets');
    $expected_facets_category['en'] = [
      [
        'count' => 1,
        'filter' => '"first"',
      ],
      [
        'count' => 1,
        'filter' => '"Portugues"',
      ],
      [
        'count' => 1,
        'filter' => '"second"',
      ],
      [
        'count' => 1,
        'filter' => '"third"',
      ],
    ];
    $expected_facets_category['es'] = [
      [
        'count' => 1,
        'filter' => '"Portugues"',
      ],
      [
        'count' => 1,
        'filter' => '"primero"',
      ],
      [
        'count' => 1,
        'filter' => '"segundo"',
      ],
      [
        'count' => 1,
        'filter' => '"third"',
      ],
    ];

    $expected_facets_category['pt'] = [
      [
        'count' => 1,
        'filter' => '"first"',
      ],
      [
        'count' => 1,
        'filter' => '"Portugues"',
      ],
      [
        'count' => 1,
        'filter' => '"second"',
      ],
      [
        'count' => 1,
        'filter' => '"third"',
      ],
    ];

    $this->assertEquals($expected_facets_category['en'], $facets_en['category']);
    $this->assertEquals($expected_facets_category['es'], $facets_es['category']);
    $this->assertEquals($expected_facets_category['pt'], $facets_pt['category']);
  }

  /**
   * Tests ignored filters.
   */
  public function testIgnoredFilters(): void {
    // Rebuild the container so the facets get reinitialized.
    $this->container->get('kernel')->rebuildContainer();

    // Change current request and set category to "third class".
    $search_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');
    $facet_id = $this->generateFacetId('category', $search_id);
    $request = $this->container->get('request_stack')->getMainRequest();
    $request->query->set('f', [$facet_id . ':third class']);

    // Run a query and assert it gets filtered by the "third class" category.
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $this->list->getQuery([]);
    $query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $results = $query->getResults();
    $this->assertEquals(2, $results->getResultCount());
    $this->assertEquals(2, count($results->getResultItems()));
    $this->assertQueryConditions(['category' => ['third class']], $query);

    $this->container->get('kernel')->rebuildContainer();
    $request = $this->container->get('request_stack')->getMainRequest();
    $request->query->set('f', [$facet_id . ':third class']);

    // Run the query, but this time, ignore the "category" facet.
    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $this->list->getQuery([
      'ignored_filters' => [$facet_id],
    ]);
    $query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $results = $query->getResults();

    // We have all 5 results this time because we ignored the query so we don't
    // have any conditions.
    $this->assertEquals(5, $results->getResultCount());
    $this->assertEquals(5, count($results->getResultItems()));
    $this->assertQueryConditions([], $query);
  }

  /**
   * Tests the preset filters.
   *
   * Preset filters should always be set, but for multiselect-based filters,
   * active filters should be merged in to allow for more values for a given
   * filter.
   */
  public function testPresetFilters(): void {
    $search_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'entity_test_mulrev_changed');

    // Set the category facet to use the multiselect widget.
    $facet_id = $this->generateFacetId('category', $search_id);
    /** @var \Drupal\facets\FacetInterface $facet */
    $facet = Facet::load($facet_id);
    $facet->setWidget('oe_list_pages_multiselect');
    $facet->save();

    // Rebuild the container every time so the facets don't get statically
    // cached in the facets manager.
    $this->container->get('kernel')->rebuildContainer();

    /** @var \Drupal\search_api\Query\QueryInterface $default_query */
    $query = $this->list->getQuery();
    $query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $results = $query->getResults();

    // Without any preset filters, we get all result items.
    $this->assertEquals(5, $results->getResultCount());
    $this->assertCount(5, $results->getResultItems());
    // There are no relevant conditions.
    $this->assertQueryConditions([], $query);

    $query = $this->list->getQuery([
      'preset_filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_id) => new ListPresetFilter($facet_id, [
          'first class',
          'second class',
        ]),
      ],
    ]);
    $query->execute();

    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $results = $query->getResults();
    // There are three results that have these two categories.
    $this->assertCount(3, $results->getResultItems());
    $this->assertQueryConditions(['category' => ['first class', 'second class']], $query);

    $this->container->get('kernel')->rebuildContainer();

    // If we run the query again, without the preset filters, it should change
    // the category to only include "first class".
    $request = $this->container->get('request_stack')->getMainRequest();
    $request->query->set('f', [$facet_id . ':first class']);

    $query = $this->list->getQuery();
    $query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $results = $query->getResults();
    $this->assertCount(1, $results->getResultItems());
    $this->assertQueryConditions(['category' => ['first class']], $query);
  }

  /**
   * Tests that the query can sort by a given field.
   */
  public function testQuerySorting(): void {
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $item_list->getIndex()->indexItems();

    $query = $this->list->getQuery();
    $query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $results = $query->getResults();
    $titles = [];
    foreach ($results as $result) {
      $titles[] = $result->getOriginalObject()->getEntity()->label();
    }

    $this->assertEquals([
      'foo bar baz 1',
      'foo bar baz 2',
      'foo bar baz 3',
      'foo bar baz 4',
      'foo bar baz 5',
    ], $titles);

    $query = $this->list->getQuery([
      'limit' => 10,
      'sort' => ['name' => 'DESC'],
    ]);
    $query->execute();
    /** @var \Drupal\search_api\Query\ResultSetInterface $results */
    $results = $query->getResults();
    $titles = [];
    foreach ($results as $result) {
      $titles[] = $result->getOriginalObject()->getEntity()->label();
    }

    $this->assertEquals([
      'foo bar baz 5',
      'foo bar baz 4',
      'foo bar baz 3',
      'foo bar baz 2',
      'foo bar baz 1',
    ], $titles);
  }

  /**
   * Tests that the options passed to the query builder are correct.
   */
  public function testQueryOptions(): void {
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');

    $valid = [
      'limit' => 10,
      'page' => 0,
      'language' => NULL,
      'sort' => [],
      'ignored_filters' => [],
      'preset_filters' => [],
    ];

    // This throws an exception if invalid.
    $item_list->getQuery($valid);

    $invalid = [
      'limit' => 'string',
      'page' => 'string',
      'language' => [],
      'sort' => 'string',
      'ignored_filters' => 'string',
      'preset_filters' => 'string',
    ];

    foreach ($invalid as $name => $value) {
      $e = NULL;
      try {
        $item_list->getQuery([$name => $value]);
      }
      catch (\Exception $exception) {
        $e = $exception;
      }

      $this->assertInstanceOf(InvalidOptionsException::class, $e, sprintf('The %s option is invalid', $name));
    }
  }

  /**
   * Creates test translated content.
   */
  protected function createTranslatedTestContent(string $bundle, array $values = []): void {
    $names = [
      ['en' => 'first', 'es' => 'primero'],
      ['en' => 'second', 'es' => 'segundo'],
      ['en' => 'third'],
    ];

    // Add new entities with translations.
    $entity_test_storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed');
    for ($i = 0; $i <= count($names) - 1; $i++) {
      $entity = $entity_test_storage->create([
        'name' => $names[$i]['en'],
        'category' => $names[$i]['en'],
        'type' => $bundle,
      ]);

      if (!empty($names[$i]['es'])) {
        $entity->addTranslation('es', [
          'category' => $names[$i]['es'],
          'name' => $names[$i]['es'],
        ]);
      }

      $entity->save();
    }

    // Add a last item in a different default language.
    $entity_test_storage = \Drupal::entityTypeManager()
      ->getStorage('entity_test_mulrev_changed');
    $entity = $entity_test_storage->create([
      'name' => 'Portugues',
      'category' => 'Portugues',
      'langcode' => 'pt-pt',
      'type' => $bundle,
    ]);
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function createTestContent(string $bundle, int $count, array $values = []): void {
    $categories = ['first class', 'second class', 'third class'];
    $bodies = $values['bodies'] ?? [
      'Sending message',
      'Receiving a Message',
      'None',
    ];

    // Add new entities.
    $entity_test_storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed');
    for ($i = 1; $i <= $count; $i++) {
      $entity_test_storage->create([
        'name' => 'foo bar baz ' . $i,
        'type' => $bundle,
        'body' => $bodies[$i % 3],
        'category' => $categories[$i % 3],
      ])->save();
    }
  }

  /**
   * Asserts that the query contains the given conditions.
   *
   * @param array $expected
   *   Expected conditions.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   */
  protected function assertQueryConditions(array $expected, QueryInterface $query): void {
    $group = $query->getConditionGroup();
    $conditions = $this->extractConditionFromGroup($group);

    $this->assertEquals($expected, $conditions);
  }

  /**
   * Extracts the conditions from a condition group, recursively.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $group
   *   The condition group.
   *
   * @return array
   *   The conditions.
   */
  protected function extractConditionFromGroup(ConditionGroupInterface $group): array {
    $conditions = [];
    foreach ($group->getConditions() as $condition) {
      if ($condition instanceof ConditionGroup) {
        $conditions += $this->extractConditionFromGroup($condition);
        continue;
      }

      // Skip the default ones to avoid a lot of duplication.
      if (in_array($condition->getValue(), [
        'entity_test_mulrev_changed',
        'entity:entity_test_mulrev_changed',
      ])) {
        continue;
      }
      $conditions[$condition->getField()][] = $condition->getValue();
    }

    return $conditions;
  }

}
