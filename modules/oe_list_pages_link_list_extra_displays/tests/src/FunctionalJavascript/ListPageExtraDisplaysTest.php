<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages_link_list_extra_displays\FunctionalJavascript;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Tests\oe_list_pages\FunctionalJavascript\ListPagePluginFormTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\oe_list_pages\ListPageWrapper;
use Drupal\search_api\Entity\Index;

/**
 * Tests the list pages with extra link list displays.
 *
 * @group batch3
 */
class ListPageExtraDisplaysTest extends ListPagePluginFormTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'oe_link_lists_test',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages_filters_test',
    'oe_list_pages_link_list_displays',
    'oe_list_pages_link_list_extra_displays',
    'extra_field',
    'search_api',
    'search_api_db',
    'block',
    'oe_list_pages_event_subscriber_test',
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

    EntityViewDisplay::load('node.oe_list_page.default')
      ->setComponent('extra_field_oe_list_page_display_switcher:node:oe_list_page', [
        'weight' => -1,
        'region' => 'content',
      ])
      ->save();

    $date = new DrupalDateTime('20-10-2020');
    $nodes = [
      'that yellow fruit' => [
        'body' => 'this is a banana',
        'boolean' => 0,
      ],
      'that red fruit' => [
        'body' => 'this is a cherry',
        'boolean' => 1,
      ],
    ];
    foreach ($nodes as $title => $values) {
      Node::create([
        'title' => $title,
        'type' => 'content_type_one',
        'body' => $values['body'],
        'field_test_boolean' => $values['boolean'],
        'status' => NodeInterface::PUBLISHED,
        'created' => $date->getTimestamp(),
      ])->save();
      $date->modify('-1 day');
    }

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    $index->indexItems();
  }

  /**
   * Tests the extra displays configuration form.
   */
  public function testExtraDisplaysForm(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $page->fillField('Title', 'List page for ct1');
    $page->selectFieldOption('Display', 'Title');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('There are no extra displays.');

    // Without extra displays, the main display tab title is not required.
    $page->pressButton('Save');
    $assert_session->elementNotExists('css', '.messages--error');
    $node = $this->drupalGetNodeByTitle('List page for ct1');
    $configuration = (new ListPageWrapper($node))->getConfiguration();
    $this->assertArrayNotHasKey('oe_list_pages_link_list_extra_displays', $configuration['extra']);
    $assert_session->elementNotExists('css', '.oe-list-pages-display-switcher');

    // Add two extra displays and assert the validation.
    $this->drupalGet($node->toUrl('edit-form'));
    $page->pressButton('Add display');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Add display');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextNotContains('There are no extra displays.');
    $this->assertCount(2, $page->findAll('css', 'table[id^="edit-oe-list-page-wrapper-extra-displays-displays"] tbody tr'));
    // The tab title and the display of the extra displays are required.
    foreach ([0, 1] as $row) {
      $this->assertEquals('required', $page->findField("oe_list_page[wrapper][extra_displays][displays][$row][tab_title][title]")->getAttribute('required'));
      $this->assertEquals('required', $page->findField("oe_list_page[wrapper][extra_displays][displays][$row][display][plugin]")->getAttribute('required'));
    }
    // Disable the browser validation to assert the server side validation.
    $this->getSession()->executeScript("document.querySelectorAll('form').forEach((form) => form.setAttribute('novalidate', 'novalidate'));");
    $page->pressButton('Save');
    $assert_session->pageTextContains('The main display tab title is required when there are extra displays.');
    $assert_session->pageTextContains('Tab title field is required.');
    $assert_session->pageTextContains('Display field is required.');

    // Configure the main display tab title and the extra displays.
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][primary_title][title]', 'list');
    $this->assertFalse($page->findField('oe_list_page[wrapper][extra_displays][primary_title][custom_title]')->isVisible());
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][0][tab_title][title]', 'grid');
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][0][display][plugin]', 'test_markup');
    $assert_session->assertWaitOnAjaxRequest();
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][1][tab_title][title]', 'custom');
    $this->assertTrue($page->findField('oe_list_page[wrapper][extra_displays][displays][1][tab_title][custom_title]')->isVisible());
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][1][display][plugin]', 'test_configurable_title');
    $assert_session->assertWaitOnAjaxRequest();
    // The plugin configuration form is shown for the extra display.
    $link = $assert_session->fieldExists('oe_list_page[wrapper][extra_displays][displays][1][display][plugin_configuration_wrapper][test_configurable_title][link]');
    $this->assertTrue($link->isChecked());
    $link->uncheck();
    // The previous selections were kept after the Ajax requests.
    $this->assertEquals('test_markup', $page->findField('oe_list_page[wrapper][extra_displays][displays][0][display][plugin]')->getValue());
    $this->assertEquals('grid', $page->findField('oe_list_page[wrapper][extra_displays][displays][0][tab_title][title]')->getValue());

    // The custom title is required when choosing a custom title.
    $page->pressButton('Save');
    $assert_session->pageTextContains('The custom title is required.');
    $page->fillField('oe_list_page[wrapper][extra_displays][displays][1][tab_title][custom_title]', 'Compact');
    // The plugin configuration of the extra displays is validated.
    $page->fillField('oe_list_page[wrapper][extra_displays][displays][1][display][plugin_configuration_wrapper][test_configurable_title][no_validate]', 'Test validation');
    $page->pressButton('Save');
    $assert_session->pageTextContains('The no_validate value cannot be filled in.');
    $page->fillField('oe_list_page[wrapper][extra_displays][displays][1][display][plugin_configuration_wrapper][test_configurable_title][no_validate]', '');
    $page->pressButton('Save');
    $assert_session->elementNotExists('css', '.messages--error');

    $configuration = $this->getListPageConfiguration($node);
    // The main display configuration is not affected.
    $this->assertEquals([
      'plugin' => 'title',
      'plugin_configuration' => [
        'title' => NULL,
        'more' => [],
      ],
    ], $configuration['extra']['oe_list_pages_link_list_displays']['display']);
    $this->assertEquals([
      'primary_title' => [
        'title' => 'list',
        'custom_title' => '',
      ],
      'displays' => [
        [
          'title' => 'grid',
          'custom_title' => '',
          'plugin' => 'test_markup',
          'plugin_configuration' => [
            'title' => NULL,
            'more' => [],
          ],
        ],
        [
          'title' => 'custom',
          'custom_title' => 'Compact',
          'plugin' => 'test_configurable_title',
          'plugin_configuration' => [
            'link' => 0,
            'no_validate' => NULL,
          ],
        ],
      ],
    ], $configuration['extra']['oe_list_pages_link_list_extra_displays']);

    // Edit again and assert the form shows the stored values.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->assertEquals('list', $page->findField('oe_list_page[wrapper][extra_displays][primary_title][title]')->getValue());
    $this->assertEquals('grid', $page->findField('oe_list_page[wrapper][extra_displays][displays][0][tab_title][title]')->getValue());
    $this->assertEquals('test_markup', $page->findField('oe_list_page[wrapper][extra_displays][displays][0][display][plugin]')->getValue());
    $this->assertEquals('custom', $page->findField('oe_list_page[wrapper][extra_displays][displays][1][tab_title][title]')->getValue());
    $this->assertEquals('Compact', $page->findField('oe_list_page[wrapper][extra_displays][displays][1][tab_title][custom_title]')->getValue());
    $this->assertEquals('test_configurable_title', $page->findField('oe_list_page[wrapper][extra_displays][displays][1][display][plugin]')->getValue());
    $this->assertFalse($page->findField('oe_list_page[wrapper][extra_displays][displays][1][display][plugin_configuration_wrapper][test_configurable_title][link]')->isChecked());

    // Reorder the extra displays.
    $this->showRowWeights();
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][0][weight]', '1');
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][1][weight]', '0');
    $page->pressButton('Save');
    $assert_session->elementNotExists('css', '.messages--error');
    $configuration = $this->getListPageConfiguration($node);
    $displays = $configuration['extra']['oe_list_pages_link_list_extra_displays']['displays'];
    $this->assertEquals(['test_configurable_title', 'test_markup'], array_column($displays, 'plugin'));

    // Reorder the extra displays again and change the plugin of one of them
    // to assert the order is kept after the Ajax rebuild.
    $this->drupalGet($node->toUrl('edit-form'));
    $this->showRowWeights();
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][0][weight]', '1');
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][1][weight]', '0');
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][0][display][plugin]', 'title');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Save');
    $assert_session->elementNotExists('css', '.messages--error');
    $this->assertTabs(['List', 'Grid', 'Compact'], 0);

    // Add a third display and remove one of the displays.
    $this->drupalGet($node->toUrl('edit-form'));
    $page->pressButton('Add display');
    $assert_session->assertWaitOnAjaxRequest();
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][2][tab_title][title]', 'map');
    $page->selectFieldOption('oe_list_page[wrapper][extra_displays][displays][2][display][plugin]', 'title');
    $assert_session->assertWaitOnAjaxRequest();
    // Remove the custom titled display.
    $page->pressButton('extra_display_remove_1');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->fieldNotExists('oe_list_page[wrapper][extra_displays][displays][1][tab_title][title]');
    $page->pressButton('Save');
    $assert_session->elementNotExists('css', '.messages--error');
    $configuration = $this->getListPageConfiguration($node);
    $displays = $configuration['extra']['oe_list_pages_link_list_extra_displays']['displays'];
    $this->assertEquals(['test_markup', 'title'], array_column($displays, 'plugin'));
    $this->assertEquals(['grid', 'map'], array_column($displays, 'title'));

    // Remove all the extra displays.
    $this->drupalGet($node->toUrl('edit-form'));
    $page->pressButton('extra_display_remove_1');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('extra_display_remove_0');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->pageTextContains('There are no extra displays.');
    $page->pressButton('Save');
    $assert_session->elementNotExists('css', '.messages--error');
    $configuration = $this->getListPageConfiguration($node);
    $this->assertArrayNotHasKey('oe_list_pages_link_list_extra_displays', $configuration['extra']);
    $this->assertEquals('title', $configuration['extra']['oe_list_pages_link_list_displays']['display']['plugin']);
  }

  /**
   * Tests switching between the displays on the list page.
   */
  public function testDisplaySwitching(): void {
    $node = $this->createListPage('List page with displays', [
      'primary_title' => [
        'title' => 'list',
        'custom_title' => '',
      ],
      'displays' => [
        [
          'title' => 'grid',
          'custom_title' => '',
          'plugin' => 'test_markup',
          'plugin_configuration' => [],
        ],
        [
          'title' => 'custom',
          'custom_title' => 'Compact',
          'plugin' => 'test_configurable_title',
          'plugin_configuration' => [
            'link' => FALSE,
          ],
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $assert_session = $this->assertSession();
    $this->assertTabs(['List', 'Grid', 'Compact'], 0);
    $this->assertLinkedTitles(['that yellow fruit', 'that red fruit']);

    // Switch to the second display while having other query arguments in the
    // URL.
    $this->drupalGet($node->toUrl(), ['query' => ['foo' => 'bar', 'page' => '0']]);
    $this->assertTabs(['List', 'Grid', 'Compact'], 0);
    $tabs = $this->getSession()->getPage()->findAll('css', '.oe-list-pages-display-switcher a');
    $this->assertTabQuery(['foo' => 'bar'], $tabs[0]->getAttribute('href'));
    $this->assertTabQuery(['foo' => 'bar', 'display' => '1'], $tabs[1]->getAttribute('href'));
    $this->assertTabQuery(['foo' => 'bar', 'display' => '2'], $tabs[2]->getAttribute('href'));
    $tabs[1]->click();
    $this->assertTabQuery(['foo' => 'bar', 'display' => '1'], $this->getSession()->getCurrentUrl());
    $this->assertTabs(['List', 'Grid', 'Compact'], 1);
    $titles = [];
    foreach ($this->getSession()->getPage()->findAll('css', '.link-list-test--title') as $element) {
      $titles[] = $element->getText();
    }
    $this->assertEquals(['that yellow fruit', 'that red fruit'], $titles);

    // Switch to the third display which renders the titles without links.
    $this->clickLink('Compact');
    $this->assertTabs(['List', 'Grid', 'Compact'], 2);
    $assert_session->elementNotExists('css', '.link-list-test--title');
    $items = $this->getSession()->getPage()->findAll('css', '.field--name-extra-field-oe-list-page-resultsnodeoe-list-page ul li');
    $this->assertCount(2, $items);
    foreach ($items as $item) {
      $this->assertNull($item->find('css', 'a'));
    }

    // Switch back to the main display.
    $this->clickLink('List');
    $this->assertTabs(['List', 'Grid', 'Compact'], 0);
    $this->assertLinkedTitles(['that yellow fruit', 'that red fruit']);

    // Invalid deltas render the main display.
    foreach (['99', 'foo', '-1'] as $delta) {
      $this->drupalGet($node->toUrl(), ['query' => ['display' => $delta]]);
      $this->assertTabs(['List', 'Grid', 'Compact'], 0);
      $this->assertLinkedTitles(['that yellow fruit', 'that red fruit']);
    }

    // A list page without extra displays shows no tabs.
    $node = $this->createListPage('List page without displays');
    $this->drupalGet($node->toUrl(), ['query' => ['display' => '1']]);
    $assert_session->elementNotExists('css', '.oe-list-pages-display-switcher');
    $this->assertLinkedTitles(['that yellow fruit', 'that red fruit']);
  }

  /**
   * {@inheritdoc}
   */
  protected function goToListPageConfiguration(): void {
    $this->drupalGet('node/add/oe_list_page');
  }

  /**
   * Tests the selected display is kept when sorting and filtering the list.
   */
  public function testDisplayKeptWhenSortingAndFiltering(): void {
    // Provide a second sort option so that the sort element is shown.
    \Drupal::state()->set('oe_list_pages_test.alter_sort_options', TRUE);
    $node = $this->createListPage('List page with displays', [
      'primary_title' => [
        'title' => 'list',
        'custom_title' => '',
      ],
      'displays' => [
        [
          'title' => 'grid',
          'custom_title' => '',
          'plugin' => 'test_markup',
          'plugin_configuration' => [],
        ],
      ],
    ]);

    $page = $this->getSession()->getPage();
    $this->drupalGet($node->toUrl(), ['query' => ['display' => '1']]);
    $this->assertTabs(['List', 'Grid'], 1);
    $this->assertMarkupTitles(['that yellow fruit', 'that red fruit']);

    // Change the sort.
    $this->markPageToDetectReload();
    $page->selectFieldOption('Sort by', 'From 1 to 0');
    $this->waitForPageReload();
    $this->assertTabQuery([
      'sort' => [
        'name' => 'field_test_boolean',
        'direction' => 'DESC',
      ],
      'display' => '1',
    ], $this->getSession()->getCurrentUrl());
    $this->assertTabs(['List', 'Grid'], 1);
    $this->assertMarkupTitles(['that red fruit', 'that yellow fruit']);

    // Filter the list.
    $page->fillField('Body', 'banana');
    $page->pressButton('Search');
    $query = $this->getCurrentQuery();
    $this->assertEquals('1', $query['display']);
    $this->assertEquals([
      'name' => 'field_test_boolean',
      'direction' => 'DESC',
    ], $query['sort']);
    $this->assertNotEmpty($query['f']);
    $this->assertTabs(['List', 'Grid'], 1);
    $this->assertMarkupTitles(['that yellow fruit']);

    // Clear the filters, which also clears the sort.
    $page->pressButton('Clear filters');
    $this->assertTabQuery(['display' => '1'], $this->getSession()->getCurrentUrl());
    $this->assertTabs(['List', 'Grid'], 1);
    $this->assertMarkupTitles(['that yellow fruit', 'that red fruit']);

    // On the main display, nothing is added to the URL.
    $this->clickLink('List');
    $this->markPageToDetectReload();
    $page->selectFieldOption('Sort by', 'From 1 to 0');
    $this->waitForPageReload();
    $this->assertTabQuery([
      'sort' => [
        'name' => 'field_test_boolean',
        'direction' => 'DESC',
      ],
    ], $this->getSession()->getCurrentUrl());
    $this->assertTabs(['List', 'Grid'], 0);
    $this->assertLinkedTitles(['that red fruit', 'that yellow fruit']);
    $page->pressButton('Clear filters');
    $this->assertTabQuery([], $this->getSession()->getCurrentUrl());
    $this->assertTabs(['List', 'Grid'], 0);
  }

  /**
   * Creates a list page using the Title display.
   *
   * @param string $title
   *   The node title.
   * @param array $extra_displays
   *   The extra displays configuration.
   *
   * @return \Drupal\node\NodeInterface
   *   The list page.
   */
  protected function createListPage(string $title, array $extra_displays = []): NodeInterface {
    $node = Node::create([
      'type' => 'oe_list_page',
      'title' => $title,
      'status' => NodeInterface::PUBLISHED,
    ]);
    $configuration = [
      'limit' => 10,
      'exposed_sort' => TRUE,
      'extra' => [
        'oe_list_pages_link_list_displays' => [
          'display' => [
            'plugin' => 'title',
            'plugin_configuration' => [],
          ],
        ],
      ],
    ];
    if ($extra_displays) {
      $configuration['extra']['oe_list_pages_link_list_extra_displays'] = $extra_displays;
    }
    $wrapper = new ListPageWrapper($node);
    $wrapper->setSource('node', 'content_type_one');
    $wrapper->setConfiguration($configuration);
    $node->save();

    return $node;
  }

  /**
   * Returns the stored list page configuration of a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return array
   *   The configuration.
   */
  protected function getListPageConfiguration(NodeInterface $node): array {
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $storage->resetCache([$node->id()]);
    return (new ListPageWrapper($storage->load($node->id())))->getConfiguration();
  }

  /**
   * Shows the row weights of the tables using tabledrag.
   */
  protected function showRowWeights(): void {
    $button = $this->getSession()->getPage()->findButton('Show row weights');
    if ($button) {
      $button->press();
    }
  }

  /**
   * Asserts the display switcher tabs.
   *
   * @param array $expected_titles
   *   The expected tab titles.
   * @param int $active_delta
   *   The delta of the expected active tab.
   */
  protected function assertTabs(array $expected_titles, int $active_delta): void {
    $tabs = $this->getSession()->getPage()->findAll('css', '.oe-list-pages-display-switcher a');
    $titles = [];
    $active = [];
    $current = [];
    foreach ($tabs as $delta => $tab) {
      $titles[] = $tab->getText();
      if ($tab->hasClass('is-active')) {
        $active[] = $delta;
      }
      if ($tab->getAttribute('aria-current') === 'page') {
        $current[] = $delta;
      }
    }

    $this->assertEquals($expected_titles, $titles);
    $this->assertEquals([$active_delta], $active);
    $this->assertEquals([$active_delta], $current);
    $this->assertSession()->elementAttributeContains('css', 'nav.oe-list-pages-display-switcher', 'aria-label', 'Display options');
  }

  /**
   * Asserts the query arguments of a URL.
   *
   * @param array $expected
   *   The expected query arguments.
   * @param string $url
   *   The URL.
   */
  protected function assertTabQuery(array $expected, string $url): void {
    $this->assertEquals($expected, UrlHelper::parse($url)['query']);
  }

  /**
   * Returns the query arguments of the current URL.
   *
   * @return array
   *   The query arguments.
   */
  protected function getCurrentQuery(): array {
    return UrlHelper::parse($this->getSession()->getCurrentUrl())['query'];
  }

  /**
   * Asserts the results are rendered by the Title display with links.
   *
   * @param array $expected
   *   The expected titles.
   */
  protected function assertLinkedTitles(array $expected): void {
    $titles = [];
    foreach ($this->getSession()->getPage()->findAll('css', '.field--name-extra-field-oe-list-page-resultsnodeoe-list-page ul li a') as $link) {
      $titles[] = $link->getText();
    }
    $this->assertEquals($expected, $titles);
  }

  /**
   * Asserts the results are rendered by the Markup display.
   *
   * @param array $expected
   *   The expected titles.
   */
  protected function assertMarkupTitles(array $expected): void {
    $titles = [];
    foreach ($this->getSession()->getPage()->findAll('css', '.link-list-test--title') as $element) {
      $titles[] = $element->getText();
    }
    $this->assertEquals($expected, $titles);
  }

}
