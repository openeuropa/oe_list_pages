<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBaseTest;
use Drupal\node\Entity\NodeType;

/**
 * Tests the List Pages.
 */
class ListPagesTest extends EntityKernelTestBaseTest {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|object
   */
  protected $nodeStorage;

  /**
   * The entity meta storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|object
   */
  protected $entityMetaStorage;

  /**
   * A node type to use for the site tree tests.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $nodeType;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_reference_revisions',
    'oe_list_pages',
    'node',
    'emr',
    'emr_node',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['oe_list_pages', 'emr', 'emr_node']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('entity_meta');
    $this->installEntitySchema('entity_meta_relation');

    $values = ['type' => 'list_page', 'name' => 'List page'];
    $this->nodeType = NodeType::create($values);
    $this->nodeType->save();

    /** @var \Drupal\emr\EntityMetaRelationInstaller $installer */
    $installer = \Drupal::service('emr.installer');
    $installer->installEntityMetaTypeOnContentEntityType('oe_list_page', 'node', [
      'list_page',
    ]);

    $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    $this->entityMetaStorage = $this->entityTypeManager->getStorage('entity_meta');
  }

  /**
   * Tests that node works correctly with entity meta.
   */
  public function testListPagesEntityMeta(): void {
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node = $node_storage->create([
      'type' => $this->nodeType->id(),
      'title' => 'List Page',
    ]);
    $node->save();

    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $node->get('emr_entity_metas')
      ->getEntityMeta('oe_list_page');
    $this->assertFalse($entity_meta->isNew());
    /** @var \Drupal\oe_list_pages\ListPageWrapper $wrapper */
    $wrapper = $entity_meta->getWrapper();
    $this->assertFalse($wrapper->getListPageConfiguration());
    $wrapper->setListPageSource('node', 'list_page');
    $wrapper->getEntityMeta()->save();

    $updated_node = $node_storage->load($node->id());
    /** @var \Drupal\emr\Entity\EntityMetaInterface $entity_meta */
    $entity_meta = $updated_node->get('emr_entity_metas')
      ->getEntityMeta('oe_list_page');

    /** @var \Drupal\oe_list_pages\ListPageWrapper $wrapper */
    $wrapper = $entity_meta->getWrapper();
    $this->assertEqual($wrapper->getListPageConfiguration(), [
      'entity_type' => 'node',
      'bundle' => 'list_page',
    ]);
  }

}
