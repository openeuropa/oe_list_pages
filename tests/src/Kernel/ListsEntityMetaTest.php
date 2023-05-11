<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

/**
 * Tests the List Pages metadata.
 */
class ListsEntityMetaTest extends ListsEntityMetaTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
  ];

  /**
   * Tests that node works correctly with entity meta.
   */
  public function testListPagesEntityMeta(): void {
    $node = $this->nodeStorage->create([
      'type' => $this->nodeType->id(),
      'title' => 'List Page',
    ]);
    $node->save();

    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $node->get('emr_entity_metas')->getEntityMeta('oe_list_page');
    $this->assertFalse($entity_meta->isNew());
    /** @var \Drupal\oe_list_pages\ListPageWrapper $wrapper */
    $wrapper = $entity_meta->getWrapper();
    $this->assertEquals('node:list_page', $wrapper->getSource());
    $this->assertEquals([], $wrapper->getConfiguration());
    $wrapper->setSource('node', 'list_page');
    $wrapper->setConfiguration(['exposed' => ['list']]);
    $node->get('emr_entity_metas')->attach($entity_meta);
    $node->save();

    $updated_node = $this->nodeStorage->load($node->id());
    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $updated_node->get('emr_entity_metas')
      ->getEntityMeta('oe_list_page');

    /** @var \Drupal\oe_list_pages\ListPageWrapper $wrapper */
    $wrapper = $entity_meta->getWrapper();
    $this->assertEquals($wrapper->getConfiguration(), ['exposed' => ['list']]);
  }

}
