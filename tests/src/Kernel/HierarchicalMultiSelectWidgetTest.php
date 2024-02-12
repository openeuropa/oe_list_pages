<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\Plugin\facets\widget\HierarchicalMultiselectWidget;
use Drupal\search_api\Item\Field;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Test for Hierarchical Multiselect widget.
 */
class HierarchicalMultiSelectWidgetTest extends ListsSourceTestBase {

  /**
   * The widget.
   *
   * @var \Drupal\oe_list_pages\Plugin\facets\widget\HierarchicalMultiselectWidget
   */
  protected $widget;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'label' => 'Hierarchy',
      'entity_type' => 'entity_test_mulrev_changed',
      'field_name' => 'field_hierarchy',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ])->save();

    $this->entityTypeManager->getStorage('field_config')->create([
      'label' => 'Hierarchy',
      'required' => FALSE,
      'field_name' => 'field_hierarchy',
      'entity_type' => 'entity_test_mulrev_changed',
      'bundle' => 'item',
    ])->save();

    $this->entityTypeManager->getStorage('field_storage_config')->create([
      'label' => 'Subject',
      'entity_type' => 'entity_test_mulrev_changed',
      'field_name' => 'field_subject',
      'type' => 'skos_concept_entity_reference',
      'settings' => [
        'target_type' => 'skos_concept',
      ],
    ])->save();

    $this->entityTypeManager->getStorage('field_config')->create([
      'label' => 'Subject',
      'required' => FALSE,
      'field_name' => 'field_subject',
      'entity_type' => 'entity_test_mulrev_changed',
      'bundle' => 'item',
    ])->save();

    $this->addFieldToIndex('field_subject', 'Subject', 'string');
    $this->addFieldToIndex('field_hierarchy', 'Hierarchy', 'integer');

    $this->widget = new HierarchicalMultiselectWidget([], 'oe_list_pages_hierarchical_multiselect_widget', [], $this->entityTypeManager, $this->container->get('entity_field.manager'), $this->container->get('plugin.manager.multiselect_filter_field'), $this->container->get('plugin.manager.facets.processor'));
  }

  /**
   * Adds a field to the entity test index.
   *
   * @param string $id
   *   The id for the field.
   * @param string $label
   *   The label for the field.
   * @param string $type
   *   The type for the field.
   */
  protected function addFieldToIndex(string $id, string $label, string $type): void {
    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $index = $item_list->getIndex();
    $field = new Field($index, $id);
    $field->setType($type);
    $field->setPropertyPath($id);
    $field->setLabel($label);
    $field->setDatasourceId('entity:entity_test_mulrev_changed');
    $index->addField($field);
    $index->save();
  }

  /**
   * Add hierarchical test values to content.
   *
   * @param string $bundle
   *   The bundle.
   */
  protected function addHierarchicalValuesToTestContent(string $bundle): void {
    // Configure the RDF SKOS graphs.
    $graphs = [
      'europa_digital_thesaurus' => 'http://data.europa.eu/uxp',
    ];
    \Drupal::service('rdf_skos.skos_graph_configurator')->addGraphs($graphs);

    // Create vocabulary.
    $voc = Vocabulary::create(['vid' => 'hierarchy']);
    $voc->save();

    // Create some terms.
    $values = [
      'name' => 'Parent',
      'vid' => 'hierarchy',
    ];
    $parent_term = Term::create($values);
    $parent_term->save();

    $values = [
      'name' => 'Child',
      'vid' => 'hierarchy',
      'parent' => $parent_term->id(),
    ];
    $child_term = Term::create($values);
    $child_term->save();

    $values = [
      'name' => 'Grandchild',
      'vid' => 'hierarchy',
      'parent' => $child_term->id(),
    ];
    $grandchild_term = Term::create($values);
    $grandchild_term->save();

    $taxonomy_terms = [
      $parent_term->id(),
      $child_term->id(),
      $grandchild_term->id(),
    ];

    $skos_terms = [
      // Financing policy, parent.
      'http://data.europa.eu/uxp/2466',
      // Investment policy, child.
      'http://data.europa.eu/uxp/2463',
      // Investment, grandchild.
      'http://data.europa.eu/uxp/1488',
      // Trade policy, not in the previous hierarchy.
      'http://data.europa.eu/uxp/2449',
    ];

    $entity_test_storage = \Drupal::entityTypeManager()->getStorage('entity_test_mulrev_changed');
    $items = $entity_test_storage->loadByProperties(['type' => $bundle]);
    $i = 0;
    foreach ($items as $item) {
      $item->set('field_hierarchy', $taxonomy_terms[$i % count($taxonomy_terms)]);
      $item->set('field_subject', $skos_terms[$i % count($skos_terms)]);
      $item->save();
      $i++;
    }

  }

  /**
   * Tests query type using the active filter.
   */
  public function testQueryType(): void {
    $this->createTestContent('item', 4);
    $this->addHierarchicalValuesToTestContent('item');

    $item_list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    $item_list->getIndex()->indexItems();

    // Create facets for categories.
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $facet_hierarchy = $this->createFacet('field_hierarchy', $default_list_id, '', 'oe_list_pages_hierarchical_multiselect_widget', []);
    $facet_subject = $this->createFacet('field_subject', $default_list_id, '', 'oe_list_pages_hierarchical_multiselect_widget', []);

    // Test OR without hierarchy.
    // Grandchild taxonomy.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_hierarchy->id()) => new ListPresetFilter($facet_hierarchy->id(), ['3'], ListPresetFilter::OR_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/3:en',
      ],
    ];

    // Child taxonomy.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_hierarchy->id()) => new ListPresetFilter($facet_hierarchy->id(), ['2'], ListPresetFilter::OR_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/2:en',
      ],
    ];

    // Parent taxonomy.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_hierarchy->id()) => new ListPresetFilter($facet_hierarchy->id(), ['1'], ListPresetFilter::OR_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/1:en',
        'entity:entity_test_mulrev_changed/4:en',
      ],
    ];

    // Test OR with hierarchy.
    // Grandchild taxonomy.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_hierarchy->id()) => new ListPresetFilter($facet_hierarchy->id(), ['3'], HierarchicalMultiselectWidget::OR_WITH_HIERARCHY_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/3:en',
      ],
    ];

    // Child taxonomy.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_hierarchy->id()) => new ListPresetFilter($facet_hierarchy->id(), ['2'], HierarchicalMultiselectWidget::OR_WITH_HIERARCHY_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/2:en',
        'entity:entity_test_mulrev_changed/3:en',
      ],
    ];

    // Parent taxonomy.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_hierarchy->id()) => new ListPresetFilter($facet_hierarchy->id(), ['1'], HierarchicalMultiselectWidget::OR_WITH_HIERARCHY_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/1:en',
        'entity:entity_test_mulrev_changed/2:en',
        'entity:entity_test_mulrev_changed/3:en',
        'entity:entity_test_mulrev_changed/4:en',
      ],
    ];

    // Test AND with hierarchy
    // Parent and Child taxonomy are returned.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_hierarchy->id()) => new ListPresetFilter($facet_hierarchy->id(), [
          '1',
          '2',
        ], HierarchicalMultiselectWidget::AND_WITH_HIERARCHY_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/2:en',
        'entity:entity_test_mulrev_changed/3:en',
      ],
    ];

    // Test NONE with hierarchy
    // Only parent is returned. It is marked in two items.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_hierarchy->id()) => new ListPresetFilter($facet_hierarchy->id(), [
          '2',
        ], HierarchicalMultiselectWidget::NONE_WITH_HIERARCHY_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/1:en',
        'entity:entity_test_mulrev_changed/4:en',
      ],
    ];

    // Test skos with hierarchy.
    // Parent skos concept.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_subject->id()) => new ListPresetFilter($facet_subject->id(), ['http://data.europa.eu/uxp/2466'], HierarchicalMultiselectWidget::OR_WITH_HIERARCHY_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/1:en',
        'entity:entity_test_mulrev_changed/2:en',
        'entity:entity_test_mulrev_changed/3:en',
      ],
    ];

    // Test skos with hierarchy.
    // Child skos concept.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_subject->id()) => new ListPresetFilter($facet_subject->id(), ['http://data.europa.eu/uxp/2463'], HierarchicalMultiselectWidget::OR_WITH_HIERARCHY_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/2:en',
        'entity:entity_test_mulrev_changed/3:en',
      ],
    ];

    // Test skos without hierarchy.
    // Parent skos concept.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_subject->id()) => new ListPresetFilter($facet_subject->id(), ['http://data.europa.eu/uxp/2466'], ListPresetFilter::OR_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/1:en',
      ],
    ];

    // Test AND with hierarchy
    // Only first two items returned.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_subject->id()) => new ListPresetFilter($facet_subject->id(), [
          'http://data.europa.eu/uxp/2466',
          'http://data.europa.eu/uxp/2463',
        ], HierarchicalMultiselectWidget::AND_WITH_HIERARCHY_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/2:en',
        'entity:entity_test_mulrev_changed/3:en',
      ],
    ];

    // Test NONE with hierarchy
    // Return "Trade policy", not in the previous hierarchy.
    $expected_key_results[] = [
      'filters' => [
        DefaultFilterConfigurationBuilder::generateFilterId($facet_subject->id()) => new ListPresetFilter($facet_subject->id(), [
          'http://data.europa.eu/uxp/2466',
        ], HierarchicalMultiselectWidget::NONE_WITH_HIERARCHY_OPERATOR),
      ],
      'results' => [
        'entity:entity_test_mulrev_changed/4:en',
      ],
    ];

    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');
    foreach ($expected_key_results as $category) {
      $this->container->get('kernel')->rebuildContainer();
      $query = $list->getQuery(['preset_filters' => $category['filters']]);
      $query->execute();
      $results = $query->getResults();
      $count = count($category['results']);
      $this->assertCount($count, $results->getResultItems());
      $i = 0;
      foreach ($results->getResultItems() as $item) {
        $this->assertEquals($item->getId(), $category['results'][$i]);
        $i++;
      }
    }
  }

}
