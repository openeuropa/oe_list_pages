<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\node\Entity\NodeType;

/**
 * Base test for testing the List page entity metadata.
 */
class ListsEntityMetaTestBase extends ListsSourceTestBase {

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
    'facets',
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

    $this->installSchema('node', ['node_access']);

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

}
