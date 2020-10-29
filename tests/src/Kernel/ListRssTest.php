<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\oe_list_pages\Controller\ListPageRssController;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests the List page RSS feeds.
 */
class ListRssTest extends ListsEntityMetaTestBase {


  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'language',
    'oe_list_pages_event_subscriber_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['language']);
    // Set up a current user to ensure anonymous users are available.
    $this->setUpCurrentUser();
  }

  /**
   * Test access to list page RSS route.
   */
  public function testRssAccess(): void {
    $user = $this->createUser([], ['access content']);
    // Create a node with list page metadata.
    $list_page = $this->nodeStorage->create([
      'type' => $this->nodeType->id(),
      'title' => 'List Page',
    ]);
    $list_page->save();

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

    // Assert we can access an RSS route for a node that has list page
    // metadata assigned to it.
    $route = Url::fromRoute('entity.node.list_page_rss', ['node' => $list_page->id()]);
    $this->assertTrue($route->access($user));

    // Assert we can not access an RSS route for a node that does not have
    // list page metadata assigned to it.
    $route = Url::fromRoute('entity.node.list_page_rss', ['node' => $article->id()]);
    $this->assertFalse($route->access($user));
  }

  /**
   * Test access to list page RSS route.
   */
  public function testRssControllerBuild(): void {
    // Create a node with list page metadata.
    $list_page = $this->nodeStorage->create([
      'type' => $this->nodeType->id(),
      'title' => 'List Page',
    ]);
    $list_page->save();

    // Create the list page rss controller.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $event_dispatcher = $this->container->get('event_dispatcher');
    $theme_manager = $this->container->get('theme.manager');
    $controller = new ListPageRssController($entity_type_manager, $event_dispatcher, $theme_manager);
    $response = $controller->build($list_page);

    // Assert the response has the correct content type header set.
    $this->assertEquals('application/rss+xml; charset=utf-8', $response->headers->get('Content-Type'));
    $crawler = new Crawler($response->getContent());

    // Assert contents of channel elements.
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('List Page - RSS', $channel->filterXPath('//title')->text());
    $this->assertEquals('http://localhost/node/1', $channel->filterXPath('//link')->text());
    $this->assertEquals('', $channel->filterXPath('//description')->text());
    $this->assertEquals('en', $channel->filterXPath('//language')->text());
    $this->assertEquals('http://localhost/core/misc/favicon.ico', $channel->filterXPath('//image/url')->text());
    $this->assertEquals('List Page - RSS', $channel->filterXPath('//image/title')->text());
    $this->assertEquals('http://localhost/node/1', $channel->filterXPath('//image/link')->text());
    // Assert modules subscribing to the ListPageRssBuildAlterEvent can
    // alter the build.
    $this->assertEquals('Copyright 2019 Dries Buytaert', $channel->filterXPath('//copyright')->text());
  }

}
