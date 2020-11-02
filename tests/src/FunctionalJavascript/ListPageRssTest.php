<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
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
    'oe_list_pages_filters_test',
    'oe_list_page_content_type',
    'node',
    'emr',
    'emr_node',
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

    $node = $this->drupalGetNodeByTitle('List page test');
    $this->drupalLogout();
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()]));
    $response = $this->getTextContent();

    $crawler = new Crawler($response);
    // Assert contents of channel elements.
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('List page test - RSS', $channel->filterXPath('//title')->text());
    $this->assertEquals('http://web:8080/build/node/1', $channel->filterXPath('//link')->text());
    $this->assertEquals('', $channel->filterXPath('//description')->text());
    $this->assertEquals('en', $channel->filterXPath('//language')->text());
    $this->assertEquals('© European Union, 1995-' . date('Y'), $channel->filterXPath('//copyright')->text());
    $this->assertEquals('http://web:8080/build/core/misc/favicon.ico', $channel->filterXPath('//image/url')->text());
    $this->assertEquals('List page test - RSS', $channel->filterXPath('//image/title')->text());
    $this->assertEquals('http://web:8080/build/node/1', $channel->filterXPath('//image/link')->text());

    // Change the node title and assert the response has changed.
    $node->set('title', 'List page test updated');
    $node->save();
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('List page test updated - RSS', $channel->filterXPath('//title')->text());
  }

}
