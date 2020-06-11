<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the JavaScript functionality of the OpenEuropa List Pages module.
 *
 * @group oe_list_pages
 */
class ListPagesPluginTest extends WebDriverTestBase {

  use TaxonomyTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'taxonomy',
    'node',
    'oe_list_pages_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType([
      'type' => 'node_type1',
      'name' => 'Node Type 1',
    ]);

    $this->drupalCreateContentType([
      'type' => 'node_type2',
      'name' => 'Node Type 2',
    ]);

    $this->drupalCreateContentType([
      'type' => 'node_type3',
      'name' => 'Node Type 3',
    ]);

    Vocabulary::create([
      'vid' => 'voc1',
      'name' => 'Vocabulary 1',
    ])->save();

    Vocabulary::create([
      'vid' => 'voc2',
      'name' => 'Vocabulary 2',
    ])->save();

    Vocabulary::create([
      'vid' => 'voc3',
      'name' => 'Vocabulary 3',
    ])->save();

    /** @var \Drupal\emr\EntityMetaRelationInstaller $installer */
    $installer = \Drupal::service('emr.installer');
    $installer->installEntityMetaTypeOnContentEntityType('oe_list_page', 'node', [
      'node_type1',
    ]);
  }

  /**
   * Test List Page entity meta plugin and available entity types/bundles.
   */
  public function testListPagePluginConfigForm(): void {

    $this->drupalLogin($this->rootUser);
    $this->drupalGet('node/add/node_type1');
    $this->clickLink('List Page');

    $actual_entity_types = $this->getSelectOptions('Source entity type');

    $expected_entity_types = [
      '' => '- Select -',
      'entity_meta_relation' => 'Entity Meta Relation',
      'entity_meta' => 'Entity meta',
      'node' => 'Content',
      'path_alias' => 'URL alias',
      'search_api_task' => 'Search task',
      'user' => 'User',
      'taxonomy_term' => 'Taxonomy term',
    ];
    $this->assertEquals($expected_entity_types, $actual_entity_types);
    $this->assertOptionSelected('Source entity type', 'Content');

    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'node_type1' => 'Node Type 1',
      'node_type2' => 'Node Type 2',
      'node_type3' => 'Node Type 3',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'voc1' => 'Vocabulary 1',
      'voc2' => 'Vocabulary 2',
      'voc3' => 'Vocabulary 3',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    $config = \Drupal::configFactory()->getEditable('oe_list_pages_test.settings');
    $config->set('allowed_entity_types_bundles', [
      'node' => [
        'node_type2' => 'node_type2',
        'node_type3' => 'node_type3',
      ],
      'taxonomy_term' => [
        'voc1' => 'voc1',
        'voc3' => 'voc3',
      ],
    ]);
    $config->save();
    $this->drupalGet('node/add/node_type1');
    $this->clickLink('List Page');
    $actual_entity_types = $this->getSelectOptions('Source entity type');
    $this->assertEquals([
      '' => '- Select -',
      'node' => 'Content',
      'taxonomy_term' => 'Taxonomy term',
    ], $actual_entity_types);
    $this->assertOptionSelected('Source entity type', 'Content');
    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'node_type2' => 'Node Type 2',
      'node_type3' => 'Node Type 3',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'voc1' => 'Vocabulary 1',
      'voc3' => 'Vocabulary 3',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);
  }

  /**
   * Get available options of select box.
   *
   * @param string $field
   *   The label, id or name of select box.
   *
   * @return array
   *   Select box options.
   */
  protected function getSelectOptions(string $field): array {
    $page = $this->getSession()->getPage();
    $options = $page->findField($field)->findAll('css', 'option');
    $actual_options = [];
    foreach ($options as $option) {
      $actual_options[$option->getValue()] = $option->getText();
    }
    return $actual_options;
  }

}
