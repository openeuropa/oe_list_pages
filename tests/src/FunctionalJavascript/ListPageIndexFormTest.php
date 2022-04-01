<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\search_api\Entity\Index;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the index alter form and saving of third party settings.
 */
class ListPageIndexFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'options',
    'oe_list_pages',
    'oe_list_pages_filters_test',
    'node',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
    'datetime',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    /** @var \Drupal\emr\EntityMetaRelationInstaller $installer */
    $installer = \Drupal::service('emr.installer');
    $installer->installEntityMetaTypeOnContentEntityType('oe_list_page', 'node', [
      'content_type_one',
    ]);
  }

  /**
   * Tests the index form alter.
   */
  public function testIndexFormAlter(): void {
    $user = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/search/search-api');
    $this->clickLink('Node');
    $column = $this->getSession()->getPage()->find('css', 'table.search-api-index-summary')->find('css', 'tr.list-pages-index td');
    // By default, the index is marked as Yes for list pages.
    $this->assertEquals('Yes', $column->getText());

    // Edit the index and uncheck the box.
    $this->drupalGet('admin/config/search/search-api/index/node/edit');
    $this->assertSession()->checkboxChecked('options[oe_list_pages][lists_pages_index]');
    $this->getSession()->getPage()->uncheckField('options[oe_list_pages][lists_pages_index]');
    $this->getSession()->getPage()->pressButton('Save');
    file_put_contents('/var/www/html/print.html', $this->getSession()->getPage()->getContent());

    $this->assertSession()->pageTextContains('The index was successfully saved.');
    // Assert the index is no longer marked as such.
    $column = $this->getSession()->getPage()->find('css', 'table.search-api-index-summary')->find('css', 'tr.list-pages-index td');
    $this->assertEquals('No', $column->getText());
    $index = Index::load('node');
    $this->assertFalse((bool) $index->getThirdPartySetting('oe_list_pages', 'lists_pages_index'));
  }

}
