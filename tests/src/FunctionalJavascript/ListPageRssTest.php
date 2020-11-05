<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Test list page RSS feeds.
 */
class ListPageRssTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_list_pages',
    'oe_list_pages_event_subscriber_test',
    'oe_list_pages_filters_test',
    'oe_list_page_content_type',
    'node',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
  ];

  /**
   * Tests the access and rendering of the RSS page of a list page.
   */
  public function testListPageRssPage() {

    // Create list page.
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Source bundle', 'Content type one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->fillField('Title', 'List page test');
    $page->pressButton('Save');

    // Create some test nodes to index and search in.
    $date = new DrupalDateTime('20-10-2020');
    $values = [
      'title' => 'that yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a banana',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    $values = [
      'title' => 'that red fruit',
      'type' => 'content_type_one',
      'body' => 'this is a cherry',
      'field_select_one' => 'test2',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    // Index the nodes.
    $index->indexItems();

    $node = $this->drupalGetNodeByTitle('List page test');
    $this->drupalLogout();

    // Assert contents of channel elements.
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('Drupal | List page test', $channel->filterXPath('//title')->text());
    $this->assertEquals('http://web:8080/build/node/1', $channel->filterXPath('//link')->text());
    $this->assertEquals('', $channel->filterXPath('//description')->text());
    $this->assertEquals('en', $channel->filterXPath('//language')->text());
    $this->assertEquals('© European Union, 1995-' . date('Y'), $channel->filterXPath('//copyright')->text());
    $this->assertEquals('http://web:8080/build/core/themes/classy/logo.svg', $channel->filterXPath('//image/url')->text());
    $this->assertEquals('European Commission logo', $channel->filterXPath('//image/title')->text());
    $this->assertEquals('http://web:8080/build/', $channel->filterXPath('//image/link')->text());
    // Assert modules subscribing to the ListPageRssBuildAlterEvent can
    // alter the build.
    $this->assertEquals('custom_value', $channel->filterXPath('//custom_tag')->text());

    // Change the node title and assert the response has changed.
    $node->set('title', 'List page test updated');
    $node->save();
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('Drupal | List page test updated', $channel->filterXPath('//title')->text());

    // Set filter values on the url and assert the description was changed.
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()], ['query' => ['f[0]' => 'status:1']]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('Published: Yes', $channel->filterXPath('//description')->text());

    // Set a filter with multiple values on the url and asser the change.
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()], ['query' => ['f[0]' => 'select_one:test1', 'f[1]' => 'select_one:test2']]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('Select one: test1, test2', $channel->filterXPath('//description')->text());
  }

}
