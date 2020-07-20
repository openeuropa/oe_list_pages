<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Functional;

use Drupal\Tests\facets\Functional\FacetsTestBase;

/**
 * Tests widgets of a facets.
 *
 * @group facets
 */
class ListWidgetIntegrationTest extends FacetsTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'views',
    'node',
    'search_api',
    'facets',
    'block',
    'facets_search_api_dependency',
    'facets_query_processor',
    'facets_custom_widget',
    'oe_list_pages',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->drupalLogin($this->adminUser);

    $this->setUpExampleStructure();
    $this->insertExampleContent();
    $this->assertEquals($this->indexItems($this->indexId), 5, '5 items were indexed.');
  }

  /**
   * Tests checkbox widget.
   */
  public function testFulltextWidget(): void {
    $id = 'ft';
    $this->createFacet('Facet fulltext widget', $id, 'name');
    $this->drupalGet('admin/config/search/facets/' . $id . '/edit');
    $this->drupalPostForm(NULL, ['widget' => 'oe_list_pages_fulltext'], 'Save');
    $this->drupalGet('search-api-test-fulltext');
    $this->assertFacetBlocksAppear();
    $this->getSession()->getPage()->hasContent('Facet fulltext widget');
  }

}
