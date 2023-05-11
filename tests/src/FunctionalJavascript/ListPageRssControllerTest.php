<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Url;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\SourceString;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Test the list page RSS feed controller.
 */
class ListPageRssControllerTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
    'locale',
    'node',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
    'facets',
    'oe_list_pages',
    'oe_list_pages_event_subscriber_test',
    'oe_list_pages_filters_test',
    'oe_list_page_content_type',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Translate the Aug (short month) string.
    $locale_storage = \Drupal::service('locale.storage');
    $source = $locale_storage->findString(['source' => 'Aug']);
    if (!$source instanceof SourceString) {
      // We need to make sure the string is available to be translated.
      $source = $locale_storage->createString();
      $source->setString('Aug')->save();
    }

    $new_translation = $locale_storage->createTranslation($source->getValues(['lid']) + ['language' => 'es']);
    $new_translation->setString('Ago');
    $new_translation->save();
  }

  /**
   * Tests the access and rendering of the RSS page of a list page.
   */
  public function testListPageRssPage(): void {
    $user = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($user);
    // Make the list pages translatable.
    \Drupal::service('content_translation.manager')->setEnabled('node', 'oe_list_page', TRUE);
    // Create the ES language.
    ConfigurableLanguage::createFromLangcode('es')->save();
    // Making the content translatable messes up the plugin cache somehow,
    // which makes the test fail on drone. We flush caches here to prevent
    // this from happening as a temporary solution.
    drupal_flush_all_caches();

    // Create list page.
    $this->drupalGet('/node/add/oe_list_page');
    $this->clickLink('List Page');
    $page = $this->getSession()->getPage();
    $page->selectFieldOption('Source bundle', 'Content type one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->fillField('Title', 'List page test');
    $page->pressButton('Save');

    // Create some test nodes to index and search in.
    $earlier_date = new DrupalDateTime('20-08-2020');
    $later_date = new DrupalDateTime('20-08-2021');
    $values = [
      'title' => 'that yellow fruit',
      'type' => 'content_type_one',
      'body' => 'this is a banana',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'created' => $earlier_date->getTimestamp(),
      'changed' => $later_date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    $values = [
      'title' => 'that red fruit',
      'type' => 'content_type_one',
      'body' => 'this is a cherry',
      'field_select_one' => 'test2',
      'status' => NodeInterface::PUBLISHED,
      'created' => $later_date->getTimestamp(),
      'changed' => $earlier_date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    for ($i = 1; $i <= 25; $i++) {
      $earlier_date->modify('-1 day');
      $values = [
        'title' => 'test node ' . $i,
        'type' => 'content_type_one',
        'body' => 'test node ' . $i,
        'field_select_one' => 'test2',
        'status' => NodeInterface::PUBLISHED,
        'created' => $earlier_date->getTimestamp(),
        'changed' => $earlier_date->getTimestamp(),
      ];
      $node = Node::create($values);
      $node->save();
    }
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    $index->indexItems();

    // Translate list page.
    $node = $this->drupalGetNodeByTitle('List page test');
    $spanish_values = $node->toArray();
    $spanish_values['title'] = 'List page test ES';
    $node->addTranslation('es', $spanish_values);
    $node->save();
    $this->drupalLogout();

    // Assert the default sorting order on the canonical page
    // is by creation date, descending, as a baseline for
    // comparison.
    $this->drupalGet(Url::fromRoute('entity.node.canonical', ['node' => $node->id()]));
    $page = $this->getSession()->getPage();
    $items = $page->findAll('css', 'div.item-list ul li h2');
    $expected_default_ordered_items = [
      'that red fruit',
      'that yellow fruit',
    ];
    array_walk($items, function (NodeElement &$item, $key) {
      $item = $item->getText();
    });
    // We check just the first to items for simplicity's sake.
    $items = array_slice($items, 0, 2);
    $this->assertEquals($items, $expected_default_ordered_items);

    // Assert presence or RSS link.
    $rss_link_selector = 'div.field--name-extra-field-oe-list-page-rss-linknodeoe-list-page a';
    $rss_link = $page->find('css', $rss_link_selector);
    $url = $rss_link->getAttribute('href');
    $this->assertEquals(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()])->toString(), $url);
    // Assert the current query parameters are passed onto the RSS link.
    $this->drupalGet(Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['query' => ['random_arg' => 'value']]));
    $rss_link = $page->find('css', $rss_link_selector);
    $url = $rss_link->getAttribute('href');
    $this->assertEquals(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()], ['query' => ['random_arg' => 'value']])->toString(), $url);
    // Assert pager options are not passed onto the RSS link.
    $this->drupalGet(Url::fromRoute('entity.node.canonical', ['node' => $node->id()], [
      'query' => [
        'random_arg' => 'value',
        'page' => '1',
      ],
    ]));
    $rss_link = $page->find('css', $rss_link_selector);
    $url = $rss_link->getAttribute('href');
    $this->assertEquals(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()], ['query' => ['random_arg' => 'value']])->toString(), $url);

    // Load and parse the RSS page.
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    // Assert contents of channel elements.
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('Drupal | List page test', $channel->filterXPath('//title')->text());
    $this->assertEquals('http://web:8080/build/node/1', $channel->filterXPath('//link')->text());
    $this->assertEquals('http://web:8080/build/node/1/rss', $channel->filterXPath('//atom:link')->attr('href'));
    $this->assertEquals('Drupal | List page test', $channel->filterXPath('//description')->text());
    $this->assertEquals('en', $channel->filterXPath('//language')->text());
    $this->assertEquals('© European Union, 1995-' . date('Y'), $channel->filterXPath('//copyright')->text());
    $this->assertEquals('http://web:8080/build/core/themes/starterkit_theme/logo.svg', $channel->filterXPath('//image/url')->text());
    $this->assertEquals('Drupal logo', $channel->filterXPath('//image/title')->text());
    $this->assertEquals('http://web:8080/build/node/1', $channel->filterXPath('//image/link')->text());
    // Assert modules subscribing to the ListPageRssAlterEvent can
    // alter the build.
    $this->assertEquals('custom_value', $channel->filterXPath('//custom_tag')->text());

    // Assert contents of items.
    $items = $channel->filterXPath('//item');
    // Assert only the first 25 items are shown.
    $this->assertEquals(25, $items->count());
    $first_item = $items->eq(0);
    $this->assertEquals('that yellow fruit', $first_item->filterXpath('//title')->text());
    $this->assertEquals('&lt;p&gt;this is a banana&lt;/p&gt; ', $first_item->filterXpath('//description')->html());
    $this->assertEquals('http://web:8080/build/node/2', $first_item->filterXpath('//link')->text());
    $this->assertEquals('http://web:8080/build/node/2', $first_item->filterXpath('//guid')->text());
    $this->assertEquals('Fri, 20 Aug 2021 00:00:00 +1000', $first_item->filterXpath('//pubDate')->text());
    // Assert modules subscribing to the ListPageRssItemAlterEvent can
    // alter the item build.
    $this->assertEquals('20/08/2020', $first_item->filterXpath('//creationDate')->text());

    $second_item = $items->eq(1);
    $this->assertEquals('that red fruit', $second_item->filterXpath('//title')->text());
    $this->assertEquals('&lt;p&gt;this is a cherry&lt;/p&gt; ', $second_item->filterXpath('//description')->html());
    $this->assertEquals('http://web:8080/build/node/3', $second_item->filterXpath('//link')->text());
    $this->assertEquals('http://web:8080/build/node/3', $second_item->filterXpath('//guid')->text());
    $this->assertEquals('Thu, 20 Aug 2020 00:00:00 +1000', $second_item->filterXpath('//pubDate')->text());
    // Assert modules subscribing to the ListPageRssItemAlterEvent can
    // alter the item build.
    $this->assertEquals('20/08/2021', $second_item->filterXpath('//creationDate')->text());

    // Assert the last item title to make sure we order
    // and limit the list correctly.
    $last_item = $items->eq(24);
    $this->assertEquals('test node 23', $last_item->filterXpath('//title')->text());

    // Assert that even if we pass pager options these are ignored.
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()], ['query' => ['page' => '1']]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    // Assert contents of items.
    $items = $channel->filterXPath('//item');
    // Assert only the first 25 items are shown.
    $this->assertEquals(25, $items->count());
    $first_item = $items->eq(0);
    $this->assertEquals('that yellow fruit', $first_item->filterXpath('//title')->text());
    $last_item = $items->eq(24);
    $this->assertEquals('test node 23', $last_item->filterXpath('//title')->text());

    // Change the node title and assert the response has changed.
    $node->set('title', 'List page test updated');
    $node->save();
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('Drupal | List page test updated', $channel->filterXPath('//title')->text());

    // Set filter values on the url and assert the description was changed.
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', [
      'node' => $node->id(),
    ], ['query' => ['f[0]' => 'status:1']]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('Published: Yes', $channel->filterXPath('//description')->text());

    // Set a filter with multiple values on the url and assert the change.
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', [
      'node' => $node->id(),
    ], [
      'query' => [
        'f[0]' => 'select_one:test1',
        'f[1]' => 'select_one:test2',
      ],
    ]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('Select one: test1, test2', $channel->filterXPath('//description')->text());

    // Set multiple filters on the url and assert the change.
    $this->drupalGet(Url::fromRoute('entity.node.list_page_rss', ['node' => $node->id()], [
      'query' => [
        'f[0]' => 'status:1',
        'f[1]' => 'select_one:test1',
        'f[2]' => 'select_one:test2',
        'f[3]' => 'oe_list_pages_filters_test_test_field:1',
        'f[4]' => 'oe_list_pages_filters_test_test_field:2',
      ],
    ]));
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('Published: Yes | Foo: One, Two | Select one: test1, test2', $channel->filterXPath('//description')->text());

    // Create a node translation of the first item.
    $node = $this->drupalGetNodeByTitle('that yellow fruit');
    $node->addTranslation('es', $node->toArray());
    $node->save();

    // Assert accessing the list page in Spanish shows translated strings.
    $this->drupalGet('/es/node/1/rss');
    $response = $this->getTextContent();
    $crawler = new Crawler($response);
    // Assert contents of channel elements.
    $channel = $crawler->filterXPath('//rss[@version=2.0]/channel');
    $this->assertEquals('Drupal | List page test ES', $channel->filterXPath('//title')->text());
    $this->assertEquals('http://web:8080/build/es/node/1', $channel->filterXPath('//link')->text());
    $this->assertEquals('es', $channel->filterXPath('//language')->text());
    $this->assertEquals('http://web:8080/build/es/node/1', $channel->filterXPath('//image/link')->text());
    // Assert the date is not translated.
    $items = $channel->filterXPath('//item');
    $first_item = $items->eq(1);
    $this->assertEquals('Thu, 20 Aug 2020 00:00:00 +1000', $first_item->filterXpath('//pubDate')->text());
  }

}
