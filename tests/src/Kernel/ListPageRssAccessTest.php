<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\oe_list_pages\Traits\ListPageTestTrait;

/**
 * Tests the List page RSS feed access.
 */
class ListPageRssAccessTest extends ListsSourceTestBase {

  use ListPageTestTrait;

  /**
   * Node with list page metadata configured.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $listPageNode;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
  ];

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|object
   */
  protected $nodeStorage;

  /**
   * A node type used in the tests.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $nodeType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['language']);
    // Set up a current user to ensure anonymous users are available.
    $this->setUpCurrentUser();

    $this->installSchema('node', ['node_access']);

    $values = ['type' => 'list_page', 'name' => 'List page'];
    $this->nodeType = NodeType::create($values);
    $this->nodeType->save();

    $this->nodeStorage = $this->entityTypeManager->getStorage('node');

    $this->installListPageFields($this->nodeType->id());

    // Create a node with list page metadata.
    $this->listPageNode = $this->nodeStorage->create([
      'type' => $this->nodeType->id(),
      'title' => 'List Page',
    ]);
    $this->listPageNode->save();
  }

  /**
   * Test access to list page RSS route.
   */
  public function testListPageRssAccess(): void {
    // Create a content type without list page metadata.
    $values = ['type' => 'article', 'name' => 'Article'];
    $this->nodeType = NodeType::create($values);
    $this->nodeType->save();
    // Create a node without list page metadata.
    $article = $this->nodeStorage->create([
      'type' => 'article',
      'title' => 'Article',
    ]);
    $article->save();

    // Assert we can not access an RSS route if the user does not have
    // node access permissions.
    $user = $this->createUser();
    $route = Url::fromRoute('entity.node.list_page_rss', ['node' => $this->listPageNode->id()]);
    $this->assertFalse($route->access($user));

    // Assert we can access an RSS route for a node that has list page
    // metadata assigned to it and a user with appropriate permissions.
    $user = $this->createUser(['access content']);
    $this->assertTrue($route->access($user));

    // Assert we can not access an RSS route for a node that does not have
    // list page metadata assigned to it even with a user with appropriate
    // permissions.
    $route = Url::fromRoute('entity.node.list_page_rss', ['node' => $article->id()]);
    $this->assertFalse($route->access($user));
  }

}
