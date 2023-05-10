<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages_link_list_source\FunctionalJavascript;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\oe_link_lists\Traits\LinkListTestTrait;
use Drupal\Tests\oe_list_pages\FunctionalJavascript\ListPagePluginFormTestBase;

/**
 * Tests the list page link source translation aspects..
 */
class ListPageLinkSourceTranslationTest extends ListPagePluginFormTestBase {

  use LinkListTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'oe_list_pages',
    'oe_list_pages_link_list_source',
    'oe_link_lists_test',
    'facets',
    'entity_reference_revisions',
    'oe_list_pages_filters_test',
    'emr',
    'emr_node',
    'search_api',
    'search_api_db',
    'content_translation',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('fr')->save();
    \Drupal::service('content_translation.manager')->setEnabled('node', 'content_type_one', TRUE);
    \Drupal::service('kernel')->rebuildContainer();
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Tests that we load translated content.
   */
  public function testListPageTranslatedContent(): void {
    // Create a test node.
    $values = [
      'title' => 'Banana title EN',
      'type' => 'content_type_one',
      'body' => 'This is a banana EN',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
    ];
    $node = Node::create($values);
    $node->save();

    $node->addTranslation('fr', ['title' => 'Banana title FR'])->save();

    // Index the node.
    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    // Index the nodes.
    $index->indexItems();

    $admin = $this->createUser([], NULL, TRUE);
    $this->drupalLogin($admin);
    $this->goToListPageConfiguration();
    $this->getSession()->getPage()->fillField('Administrative title', 'List page plugin test');
    $this->getSession()->getPage()->fillField('Title', 'List page list');

    $this->getSession()->getPage()->selectFieldOption('Link display', 'Title');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'Content');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'Content type one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('No results behaviour', 'Hide');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Save');

    // In English we see the EN version.
    $this->assertSession()->pageTextContainsOnce('Banana title EN');

    // Switch to French and assert we see the translation.
    $link_list = $this->getLinkListByTitle('List page list');
    $this->drupalGet($link_list->toUrl('canonical', ['language' => \Drupal::languageManager()->getLanguage('fr')]));

    $this->assertSession()->pageTextContainsOnce('Banana title FR');
    $this->assertSession()->pageTextNotContains('Banana title EN');
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
