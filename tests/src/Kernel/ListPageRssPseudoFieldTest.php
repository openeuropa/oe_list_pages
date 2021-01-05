<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\node\Entity\NodeType;

/**
 * Tests the List page RSS feed pseudo field.
 */
class ListPageRssPseudoFieldTest extends ListsEntityMetaTestBase {

  /**
   * Node with list page metadata configured.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $listPageNode;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $values = ['type' => 'simple_content_type', 'name' => 'Simple content type'];
    $this->nodeType = NodeType::create($values);
    $this->nodeType->save();
  }

  /**
   * Test presence of RSS feed pseudo field.
   */
  public function testRssFeedPseudoField(): void {
    $content_types = [
      'simple_content_type' => FALSE,
      'list_page' => TRUE,
    ];
    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    foreach ($content_types as $node_type => $available) {
      $node_type_view_display = $display_repository->getViewDisplay('node', $node_type);
      $this->assertNull($node_type_view_display->getComponent('rss_link'), 'By default RSS link is not visible even if available.');
      $hidden_fields = $node_type_view_display->get('hidden');
      if ($available) {
        $this->assertArrayHasKey('rss_link', $hidden_fields);
      }
      else {
        $this->assertArrayNotHasKey('rss_link', $hidden_fields);
      }
    }
  }

}
