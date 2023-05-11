<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;

/**
 * Tests the List page RSS feed access.
 */
class ListPageRssAccessTest extends ListsEntityMetaTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['language']);
    // Set up a current user to ensure anonymous users are available.
    $this->setUpCurrentUser();

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
    $user = $this->createUser([], ['access content']);
    $this->assertTrue($route->access($user));

    // Assert we can not access an RSS route for a node that does not have
    // list page metadata assigned to it even with a user with appropriate
    // permissions.
    $route = Url::fromRoute('entity.node.list_page_rss', ['node' => $article->id()]);
    $this->assertFalse($route->access($user));
  }

}
