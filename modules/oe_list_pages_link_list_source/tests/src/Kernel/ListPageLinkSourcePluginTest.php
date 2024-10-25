<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages_link_list_source\Kernel;

use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\entity_test\Entity\EntityTestWithBundle;
use Drupal\search_api\Entity\Index;

/**
 * Tests the list page link source plugin.
 */
class ListPageLinkSourcePluginTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'user',
    'emr',
    'entity_test',
    'oe_link_lists',
    'oe_list_pages_link_list_source',
    'oe_list_pages_link_list_source_test',
    'oe_list_pages',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'facets',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_with_bundle');
    $this->installEntitySchema('facets_facet');
    $this->installEntitySchema('search_api_task');
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('entity_test_with_bundle');
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('user');

    $foo = EntityTestBundle::create(['id' => 'foo']);
    $foo->setThirdPartySetting('oe_list_pages', 'default_sort', [
      'name' => 'name',
      'direction' => 'ASC',
    ]);
    $foo->save();
    EntityTestBundle::create(['id' => 'bar'])->save();

    $this->container->get('state')->set('search_api_use_tracking_batch', FALSE);

    // Set tracking page size so tracking will work properly.
    $this->container->get('config.factory')
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    $this->installConfig([
      'facets',
      'oe_list_pages',
      'search_api_test_db',
      'system',
      'oe_link_lists',
    ]);

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = Index::load('database_search_index');
    $index->setThirdPartySetting('oe_list_pages', 'lists_pages_index', TRUE);
    $datasource = $this->container->get('search_api.plugin_helper')->createDatasourcePlugin($index, 'entity:entity_test_with_bundle');
    $datasource->setConfiguration([
      'bundles' => [
        'default' => FALSE,
        'selected' => ['foo', 'bar'],
      ],
    ]);

    // Remove the datasource that was installed from the contrib module and
    // create one for the "entity_test_with_bundle" entity type.
    $index->removeDatasource('entity:entity_test_mulrev_changed');
    $index->addDatasource($datasource);

    // Replace the "type" field from the index with the one that represents the
    // "entity_test_with_bundle" entity type bundle.
    $field_info = [
      'label' => 'Type',
      'type' => 'string',
      'datasource_id' => 'entity:entity_test_with_bundle',
      'property_path' => 'type',
    ];
    $index->removeField('type');
    $field = $this->container->get('search_api.fields_helper')->createField($index, 'type', $field_info);
    $index->addField($field);
    // Save the index to change the datasource.
    $index->save();

    // Add the "name" field back.
    $field_info = [
      'label' => 'Name',
      'type' => 'string',
      'datasource_id' => 'entity:entity_test_with_bundle',
      'property_path' => 'name',
    ];
    $field = $this->container->get('search_api.fields_helper')->createField($index, 'name', $field_info);
    $index->addField($field);
    $index->save();
  }

  /**
   * Tests the getLinks() method.
   *
   * @covers ::getLinks
   */
  public function testGetLinks(): void {
    // Create content of two types.
    $test_entities_by_bundle = [];
    foreach ($this->getTestEntities() as $entity_values) {
      $entity = EntityTestWithBundle::create($entity_values);
      $entity->save();

      // Group the entities to allow easier testing.
      $test_entities_by_bundle[$entity->bundle()][$entity->id()] = $entity->label();
    }

    $list_source_factory = $this->container->get('oe_list_pages.list_source.factory');
    foreach (['foo', 'bar'] as $bundle) {
      $item_list = $list_source_factory->get('entity_test_with_bundle', $bundle);
      $item_list->getIndex()->indexItems();
    }

    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');
    /** @var \Drupal\oe_list_pages_link_list_source\Plugin\LinkSource\ListPageLinkSource $plugin */
    $plugin = $plugin_manager->createInstance('list_pages');

    // Test a plugin without configuration.
    $this->assertEquals([], $plugin->getLinks()->toArray());

    // Test that only the entities of the specified bundle are returned.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test_with_bundle',
      'bundle' => 'foo',
    ]);
    // By default, without a limit passed all results are returned.
    $this->assertEquals($test_entities_by_bundle['foo'], $this->extractEntityNames($plugin->getLinks()->toArray()));
    $plugin->setConfiguration([
      'entity_type' => 'entity_test_with_bundle',
      'bundle' => 'bar',
    ]);
    $this->assertEquals($test_entities_by_bundle['bar'], $this->extractEntityNames($plugin->getLinks()->toArray()));

    // Test that the limit is applied to the results.
    $plugin->setConfiguration([
      'entity_type' => 'entity_test_with_bundle',
      'bundle' => 'foo',
    ]);
    $this->assertEquals(
      array_slice($test_entities_by_bundle['foo'], 0, 2, TRUE),
      $this->extractEntityNames($plugin->getLinks(2)->toArray())
    );
  }

  /**
   * Tests that the proper cacheability metadata is returned by the plugin.
   */
  public function testCacheabilityMetadata(): void {
    $plugin_manager = $this->container->get('plugin.manager.oe_link_lists.link_source');
    /** @var \Drupal\oe_link_lists_internal_source\Plugin\LinkSource\InternalLinkSource $plugin */
    $plugin = $plugin_manager->createInstance('list_pages');

    $links = $plugin->getLinks();
    $this->assertEquals([], $links->getCacheTags());
    $this->assertEquals(['route'], $links->getCacheContexts());
    $this->assertEquals(Cache::PERMANENT, $links->getCacheMaxAge());

    $plugin->setConfiguration([
      'entity_type' => 'entity_test_with_bundle',
      'bundle' => 'foo',
    ]);
    $links = $plugin->getLinks();
    $this->assertCount(0, $links);

    $this->assertEqualsCanonicalizing([
      'config:search_api.index.database_search_index',
      'entity_test_with_bundle_list:foo',
      'entity_test_with_bundle_list',
    ], $links->getCacheTags());
    $this->assertEquals(['route'], $links->getCacheContexts());
    $this->assertEquals(Cache::PERMANENT, $links->getCacheMaxAge());

    // Create a test entity.
    $entity = EntityTestWithBundle::create([
      'name' => 'Test entity',
      'type' => 'foo',
    ]);
    $entity->save();
    $list_source_factory = $this->container->get('oe_list_pages.list_source.factory');
    $item_list = $list_source_factory->get('entity_test_with_bundle', 'foo');
    $item_list->getIndex()->indexItems();

    // Kill the container because the list execution manager caches the
    // execution.
    $this->container->get('kernel')->rebuildContainer();
    /** @var \Drupal\oe_link_lists_internal_source\Plugin\LinkSource\InternalLinkSource $plugin */
    $plugin = $plugin_manager->createInstance('list_pages');
    $plugin->setConfiguration([
      'entity_type' => 'entity_test_with_bundle',
      'bundle' => 'foo',
    ]);
    $links = $plugin->getLinks();
    $this->assertCount(1, $links);
    $this->assertEqualsCanonicalizing([
      'config:search_api.index.database_search_index',
      'entity_test_with_bundle_list:foo',
      'entity_test_with_bundle_list',
      'entity_test_with_bundle:' . $entity->id(),
    ], $links->getCacheTags());
    $this->assertEquals(['route'], $links->getCacheContexts());
    $this->assertEquals(Cache::PERMANENT, $links->getCacheMaxAge());
  }

  /**
   * Helper method to extract entity ID and name from an array of test entities.
   *
   * @param \Drupal\oe_link_lists\EntityAwareLinkInterface[] $links
   *   A list of link objects.
   *
   * @return array
   *   A list of entity labels, keyed by entity ID.
   */
  protected function extractEntityNames(array $links): array {
    $labels = [];

    foreach ($links as $link) {
      $entity = $link->getEntity();
      $labels[$entity->id()] = $entity->label();
    }

    return $labels;
  }

  /**
   * Provides an array of entity data to be used in the test.
   *
   * @return array
   *   An array of entity data.
   */
  protected function getTestEntities(): array {
    $two_years_ago = $this->container->get('datetime.time')->getRequestTime() - 2 * 12 * 365 * 24 * 60 * 60;
    return [
      [
        'name' => 'A' . $this->randomString(),
        'type' => 'foo',
        'created' => $two_years_ago,
      ],
      [
        'name' => 'B' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'C' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'D' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'E' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'F' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'G' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'H' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'I' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'J' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'K' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'L' . $this->randomString(),
        'type' => 'foo',
      ],
      [
        'name' => 'T' . $this->randomString(),
        'type' => 'bar',
      ],
      [
        'name' => 'S' . $this->randomString(),
        'type' => 'bar',
      ],
      [
        'name' => 'B' . $this->randomString(),
        'type' => 'bar',
        'created' => $two_years_ago,
      ],
      [
        'name' => 'B' . $this->randomString(),
        'type' => 'bar',
        'created' => $two_years_ago,
      ],
      [
        'name' => 'M' . $this->randomString(),
        'type' => 'bar',
      ],
    ];
  }

}
