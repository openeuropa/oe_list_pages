<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages_link_list_source\FunctionalJavascript;

use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;
use Drupal\Tests\oe_list_pages\FunctionalJavascript\ListPagePluginFormTestBase;

/**
 * Tests the list page link source plugin.
 */
class ListPageLinkSourcePluginTest extends ListPagePluginFormTestBase {

  use LinkListTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'oe_list_pages_link_list_source',
    'oe_link_lists_test',
    'oe_list_pages_event_subscriber_test',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages_filters_test',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
    'block',
  ];

  /**
   * Tests the plugin configuration form.
   */
  public function testPluginConfigurationForm(): void {
    $web_user = $this->drupalCreateUser([
      'create dynamic link list',
      'edit dynamic link list',
    ]);
    $this->drupalLogin($web_user);

    $this->assertListPageEntityTypeSelection();

    // In link lists, we disable the exposed filters.
    $this->goToListPageConfiguration();
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'vocabulary_one');
    $this->assertSession()->pageTextNotContains('An illegal choice has been detected.');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldNotExists('Override default exposed filters');
    $this->assertSession()->fieldNotExists('Exposed filters');

    $this->getSession()->getPage()->selectFieldOption('Link display', 'Foo');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Save the link list.
    $this->getSession()->getPage()->fillField('Administrative title', 'List page plugin test');
    $this->getSession()->getPage()->fillField('Title', 'List page list');
    $this->getSession()->getPage()->pressButton('Save');

    $link_list = $this->getLinkListByTitle('List page list', TRUE);
    $configuration = new ListPageConfiguration($link_list->getConfiguration()['source']['plugin_configuration']);
    $this->assertEquals('taxonomy_term', $configuration->getEntityType());
    $this->assertEquals('vocabulary_one', $configuration->getBundle());
    $this->assertEmpty($configuration->getExposedFilters());
    $this->assertFalse($configuration->isExposedFiltersOverridden());

    // Edit the link list and check the values are correctly pre-populated.
    $this->drupalGet($link_list->toUrl('edit-form'));

    $this->assertOptionSelected('Source entity type', 'Taxonomy term');
    $this->assertOptionSelected('Source bundle', 'Vocabulary one');

    // Change the source to a Node type.
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextNotContains('An illegal choice has been detected.');
    $this->getSession()->getPage()->pressButton('Save');

    $link_list = $this->getLinkListByTitle('List page list', TRUE);
    $configuration = new ListPageConfiguration($link_list->getConfiguration()['source']['plugin_configuration']);
    $this->assertEquals('node', $configuration->getEntityType());
    $this->assertEquals('content_type_one', $configuration->getBundle());
    $this->assertEmpty($configuration->getExposedFilters());
    $this->assertFalse($configuration->isExposedFiltersOverridden());
  }

  /**
   * Test list page preset filters form level validations.
   */
  public function testListPagePresetFilterValidations(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);

    $this->goToListPageConfiguration();
    $this->assertListPagePresetFilterValidations('configuration[0][link_source][plugin_configuration_wrapper][list_pages][list_page_configuration][wrapper][default_filter_values]');
  }

  /**
   * Test list page preset filters configuration.
   */
  public function testListPagePresetFilters(): void {
    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();
    $this->getSession()->getPage()->fillField('Administrative title', 'List page plugin test');
    $this->getSession()->getPage()->fillField('Title', 'List page list');
    $this->getSession()->getPage()->selectFieldOption('Link display', 'Foo');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertListPagePresetFilters('configuration[0][link_source][plugin_configuration_wrapper][list_pages][list_page_configuration][wrapper][default_filter_values]');
  }

  /**
   * {@inheritdoc}
   */
  protected function goToListPageConfiguration(): void {
    $this->drupalGet('link_list/add/dynamic');
    $this->getSession()->getPage()->selectFieldOption('Link source', 'List page');
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

}
