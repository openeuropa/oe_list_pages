<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\FunctionalJavascript;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\facets\Entity\Facet;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\oe_list_pages\DefaultFilterConfigurationBuilder;
use Drupal\search_api\Entity\Index;

/**
 * Base class for testing list page configuration forms.
 */
abstract class ListPagePluginFormTestBase extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';

  /**
   * Runs assertions for the preset filters form level validations.
   *
   * @param string $default_value_name_prefix
   *   The prefix of the preset filter element names.
   */
  public function assertListPagePresetFilterValidations(string $default_value_name_prefix): void {
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    // Filter ids.
    $body_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('body');
    $created_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('created');

    // Do not fill in the title and assert the validation limiting works.
    $page->selectFieldOption('Add default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Body field is required.');
    $assert->pageTextNotContains('Title field is required.');

    // Cancel and start over.
    $page->pressButton('Cancel');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextNotContains('Title field is required.');
    $assert->pageTextNotContains('Body field is required.');
    $page->fillField('Title', 'List page for ct1');

    // Set preset filter for Body and cancel.
    $page->selectFieldOption('Add default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $assert->pageTextContains('Set default value for Body');
    $page = $this->getSession()->getPage();
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $page->fillField($filter_selector, 'cherry');
    $page->pressButton('Cancel');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextNotContains('Title field is required.');
    $assert->pageTextNotContains('Body field is required.');
    $this->assertDefaultValueForFilters([
      [
        'key' => '',
        'value' => t('No default values set'),
      ],
    ]);

    // Set preset filter for Created.
    $page->selectFieldOption('Add default value for', 'Created');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Created');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $created_filter_id . ']';
    // Assert validations.
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Created field is required.');
    $assert->pageTextContains('The date is required. Please enter a date in the format');
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[created_op]', 'In between');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('The date is required. Please enter a date in the format');
    $assert->pageTextContains('The second date is required.');
    $this->getSession()->getPage()->fillField($filter_selector . '[created_first_date_wrapper][created_first_date][date]', '10/19/2019');
    $this->getSession()->getPage()->fillField($filter_selector . '[created_second_date_wrapper][created_second_date][date]', '10/17/2019');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('The second date cannot be before the first date.');
  }

  /**
   * Runs assertions for selecting the entity type/bundle.
   */
  protected function assertListPageEntityTypeSelection(): void {
    $this->goToListPageConfiguration();

    $actual_entity_types = $this->getSelectOptions('Source entity type');

    $expected_entity_types = [
      '' => '- Select -',
      'node' => 'Content',
      'taxonomy_term' => 'Taxonomy term',
    ];
    $this->assertEquals($expected_entity_types, $actual_entity_types);
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'content_type_one' => 'Content type one',
      'content_type_two' => 'Content type two',
      '' => '- Select -',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    // Switch to the taxonomy term and assert that we have different bundles.
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'vocabulary_one' => 'Vocabulary one',
      'vocabulary_two' => 'Vocabulary two',
      '' => '- Select -',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);

    // Select a bundle, then change back to Node. Wait for all the Ajax
    // requests to complete to ensure the callbacks work work.
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'vocabulary_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'content_type_one');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Set state values to trigger the test event subscriber and make some
    // limitations.
    $allowed = [
      'node' => [
        'content_type_one',
      ],
      'taxonomy_term' => [
        'vocabulary_one',
      ],
    ];
    \Drupal::state()->set('oe_list_pages_test.allowed_entity_types_bundles', $allowed);

    $this->goToListPageConfiguration();
    $actual_entity_types = $this->getSelectOptions('Source entity type');
    $expected_entity_types = [
      '' => '- Select -',
      'node' => 'Content',
      'taxonomy_term' => 'Taxonomy term',
    ];
    $this->assertEquals($expected_entity_types, $actual_entity_types);
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'node');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'content_type_one' => 'Content type one',
      '' => '- Select -',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);
    $this->getSession()->getPage()->selectFieldOption('Source entity type', 'taxonomy_term');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $actual_bundles = $this->getSelectOptions('Source bundle');
    $expected_bundles = [
      'vocabulary_one' => 'Vocabulary one',
      '' => '- Select -',
    ];
    $this->assertEquals($expected_bundles, $actual_bundles);
    $this->getSession()->getPage()->selectFieldOption('Source bundle', 'vocabulary_one');
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Runs assertions for the preset filters form.
   *
   * @param string $default_value_name_prefix
   *   The prefix of the preset filter element names.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function assertListPagePresetFilters(string $default_value_name_prefix): void {
    // Set tabs.
    $this->drupalPlaceBlock('local_tasks_block', ['primary' => TRUE]);

    // Create some test nodes of content type Two.
    $date = new DrupalDateTime('30-10-2020');
    $values = [
      'title' => 'Red',
      'type' => 'content_type_two',
      'body' => 'red color',
      'field_select_one' => 'test2',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $red_node = Node::create($values);
    $red_node->save();

    $values = [
      'title' => 'Yellow',
      'type' => 'content_type_two',
      'body' => 'yellow color',
      'field_select_one' => 'test2',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $yellow_node = Node::create($values);
    $yellow_node->save();

    $values = [
      'title' => 'Green',
      'type' => 'content_type_two',
      'body' => 'green color',
      'field_select_one' => 'test2',
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $green_node = Node::create($values);
    $green_node->save();

    // Create some test nodes of content type One.
    $date = new DrupalDateTime('20-10-2020');
    $values = [
      'title' => 'Banana title',
      'type' => 'content_type_one',
      'body' => 'This is a banana',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'field_reference' => [$yellow_node->id(), $green_node->id()],
      'created' => $date->getTimestamp(),
      'field_link' => 'http://banana.com',
    ];
    $node = Node::create($values);
    $node->save();

    $date = new DrupalDateTime('20-10-2020');
    $values = [
      'title' => 'Sun title',
      'type' => 'content_type_one',
      'body' => 'This is the sun',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'field_reference' => $yellow_node->id(),
      'created' => $date->getTimestamp(),
      'field_link' => 'http://sun.com',
    ];
    $node = Node::create($values);
    $node->save();

    $date = new DrupalDateTime('20-10-2020');
    $values = [
      'title' => 'Grass title',
      'type' => 'content_type_one',
      'body' => 'this is the grass',
      'status' => NodeInterface::PUBLISHED,
      'field_select_one' => 'test1',
      'field_reference' => $green_node->id(),
      'created' => $date->getTimestamp(),
      // Sun title.
      'field_link' => 'entity:node/' . $node->id(),
    ];
    $node = Node::create($values);
    $node->save();

    $date = new DrupalDateTime('30-10-2020');
    $values = [
      'title' => 'Cherry title',
      'type' => 'content_type_one',
      'body' => 'This is a cherry',
      'field_select_one' => 'test2',
      'field_reference' => $red_node->id(),
      'status' => NodeInterface::PUBLISHED,
      'created' => $date->getTimestamp(),
    ];
    $node = Node::create($values);
    $node->save();

    /** @var \Drupal\search_api\Entity\Index $index */
    $index = Index::load('node');
    // Index the nodes.
    $index->indexItems();

    // Filter ids.
    $body_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('body');
    $created_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('created');
    $published_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('list_facet_source_node_content_type_onestatus');
    $reference_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('reference');
    $link_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('link');

    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $page->selectFieldOption('Source entity type', 'Content');
    $assert->assertWaitOnAjaxRequest();
    $page->selectFieldOption('Source bundle', 'Content type one');
    $assert->assertWaitOnAjaxRequest();

    $expected_set_filters = [];

    // Set preset filter for Body.
    $page->selectFieldOption('Add default value for', 'Body');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Body');
    $page = $this->getSession()->getPage();
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]';
    $page->fillField($filter_selector, 'cherry');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['body'] = ['key' => 'Body', 'value' => 'cherry'];
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Assert the Edit and Remove buttons are in the right place.
    $edit = $this->getSession()->getPage()->find('css', '.default-filters-table td input[name="default-edit-' . $body_filter_id . '"]');
    $this->assertEquals('Edit', $edit->getValue());
    $delete = $this->getSession()->getPage()->find('css', '.default-filters-table td input[name="default-delete-' . $body_filter_id . '"]');
    $this->assertEquals('Delete', $delete->getValue());

    // Set preset filter for Created.
    $page->selectFieldOption('Add default value for', 'Created');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Created');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $created_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[created_op]', 'After');
    $this->getSession()->getPage()->fillField($filter_selector . '[created_first_date_wrapper][created_first_date][date]', '10/19/2019');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['created'] = [
      'key' => 'Created',
      'value' => 'After 19 October 2019',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Switch content type and assert we can add values for that and come back.
    $page->selectFieldOption('Source bundle', 'Content type two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // We have no preset filters for this content type yet.
    $this->assertDefaultValueForFilters([
      [
        'key' => '',
        'value' => t('No default values set'),
      ],
    ]);
    // Set a preset filter for Select two.
    $page->selectFieldOption('Add default value for', 'Select two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Select two');
    $select_two_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('list_facet_source_node_content_type_twofield_select_two');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $select_two_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[list_facet_source_node_content_type_twofield_select_two][0][list]', 'test1');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters([
      [
        'key' => 'Select two',
        'value' => 'test1',
      ],
    ]);
    // Switch back to content type one and resume where we left off.
    $page->selectFieldOption('Source bundle', 'Content type one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Switch content type again, but this time while on the edit form of a
    // default filter.
    $page->pressButton('default-edit-' . $created_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Created');
    $page->selectFieldOption('Source bundle', 'Content type two');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // We still have the preset filter values we set earlier as they are kept
    // in form state.
    $this->assertDefaultValueForFilters([
      [
        'key' => 'Select two',
        'value' => 'test1',
      ],
    ]);
    // Try to edit and make sure that works.
    $page->pressButton('default-edit-' . $select_two_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Select two');
    // Switch back to content type one.
    $page->selectFieldOption('Source bundle', 'Content type one');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Set preset filter for Published.
    $page->selectFieldOption('Add default value for', 'Published');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Published');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $published_filter_id . ']';

    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[list_facet_source_node_content_type_onestatus][0][boolean]', '1');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['published'] = [
      'key' => 'Published',
      'value' => 'Yes',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Set preset filter for Reference.
    $page->selectFieldOption('Add default value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $reference_filter_id . ']';

    $this->getSession()->getPage()->fillField($filter_selector . '[reference][0][entity_reference]', 'red (1)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['reference'] = [
      'key' => 'Reference',
      'value' => 'red',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Set additional value for reference.
    $page->pressButton('default-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $reference_filter_id . ']';
    $this->assertSession()->fieldValueEquals($filter_selector . '[reference][0][entity_reference]', 'red (1)');
    $this->getSession()->getPage()->fillField($filter_selector . '[reference][1][entity_reference]', 'yellow (2)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['reference'] = [
      'key' => 'Reference',
      'value' => 'red, yellow',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Remove the yellow animal.
    $page->pressButton('default-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Reference');
    $this->assertSession()->fieldValueEquals($filter_selector . '[reference][0][entity_reference]', 'red (1)');
    $this->assertSession()->fieldValueEquals($filter_selector . '[reference][1][entity_reference]', 'yellow (2)');
    $this->getSession()->getPage()->fillField($filter_selector . '[reference][1][entity_reference]', '');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['reference'] = [
      'key' => 'Reference',
      'value' => 'red',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Set preset filter for Select one.
    $page->selectFieldOption('Add default value for', 'Select one');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Select one');

    $select_one_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('select_one');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $select_one_filter_id . ']';
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[select_one][0][list]', 'test2');
    $page->pressButton('Add another item');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption($filter_selector . '[select_one][1][list]', 'test3');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['select_one'] = [
      'key' => 'Select one',
      'value' => 'Test2, Test3',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Remove preset filter for Published.
    $this->assertSession()->elementTextContains('css', 'table.default-filters-table', 'Published');
    $page->pressButton('default-delete-' . $published_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    unset($expected_set_filters['published']);
    $this->assertDefaultValueForFilters($expected_set_filters);
    $this->assertSession()->elementTextNotContains('css', 'table.default-filters-table', 'Published');

    // Remove preset filter for Reference.
    $this->assertSession()->elementTextContains('css', 'table.default-filters-table', 'Reference');
    $page->pressButton('default-delete-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    unset($expected_set_filters['reference']);
    $this->assertDefaultValueForFilters($expected_set_filters);
    $this->assertSession()->elementTextNotContains('css', 'table.default-filters-table', 'Reference');

    // Remove preset filter for select one.
    $this->assertSession()->elementTextContains('css', 'table.default-filters-table', 'Select one');
    $page->pressButton('default-delete-' . $select_one_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    unset($expected_set_filters['select_one']);
    $this->assertDefaultValueForFilters($expected_set_filters);
    $this->assertSession()->elementTextNotContains('css', 'table.default-filters-table', 'Select one');

    // Edit preset filter for Body and cancel.
    $page->pressButton('default-edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals($default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]', 'cherry');
    $page->fillField($default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]', 'banana');
    $page->pressButton('Cancel');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Nothing was changed, we just pressed the cancel button.
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Edit preset filter for Body.
    $page->pressButton('default-edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals($default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]', 'cherry');
    $page->fillField($default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]', 'banana');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['body'] = [
      'key' => 'Body',
      'value' => 'banana',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Edit preset filter for Created.
    $page->pressButton('default-edit-' . $created_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Created');
    $this->assertSession()->fieldValueEquals($default_value_name_prefix . '[wrapper][edit][' . $created_filter_id . '][created_op]', 'gt');
    $this->assertSession()->fieldValueEquals($default_value_name_prefix . '[wrapper][edit][' . $created_filter_id . '][created_first_date_wrapper][created_first_date][date]', '2019-10-19');
    $this->getSession()->getPage()->selectFieldOption($default_value_name_prefix . '[wrapper][edit][' . $created_filter_id . '][created_op]', 'Before');
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $created_filter_id . '][created_first_date_wrapper][created_first_date][date]', '10/31/2020');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['created'] = [
      'key' => 'Created',
      'value' => 'Before 31 October 2020',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);

    // Save.
    $page->pressButton('Save');

    // Check results.
    $assert->pageTextNotContains('Cherry title');
    $assert->pageTextContains('Banana title');
    // Assert the preset filters are not shown as selected filter values.
    $this->assertSession()->linkNotExistsExact('banana');
    $this->assertSession()->linkNotExistsExact('Before 31 October 2020');

    // Edit again, change preset filter, expose filter and save.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-edit-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains(' Set default value for Body');
    $page = $this->getSession()->getPage();
    $this->assertSession()->fieldValueEquals($default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]', 'banana');
    $page->fillField($default_value_name_prefix . '[wrapper][edit][' . $body_filter_id . '][body]', 'cherry');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['body'] = [
      'key' => 'Body',
      'value' => 'cherry',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');
    $assert->pageTextNotContains('Banana title');
    $assert->pageTextContains('Cherry title');
    $this->assertSession()->linkNotExistsExact('banana');
    $this->assertSession()->linkNotExistsExact('Before 31 October 2020');

    // Edit again to remove the Body filter and save.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-delete-' . $body_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    unset($expected_set_filters['body']);
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');

    $assert->pageTextContains('Banana title');
    $assert->pageTextContains('Cherry title');
    $this->assertSession()->linkNotExistsExact('Before 31 October 2020');

    // Change preset filter for Created.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-edit-' . $created_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert = $this->assertSession();
    $assert->pageTextContains('Set default value for Created');
    $page = $this->getSession()->getPage();
    $this->getSession()->getPage()->selectFieldOption($default_value_name_prefix . '[wrapper][edit][' . $created_filter_id . '][created_op]', 'Before');
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $created_filter_id . '][created_first_date_wrapper][created_first_date][date]', '10/30/2020');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['created'] = [
      'key' => 'Created',
      'value' => 'Before 30 October 2020',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');

    $assert->pageTextContains('Banana title');
    $assert->pageTextNotContains('Cherry title');
    $this->assertSession()->linkNotExistsExact('Before 30 October 2020');

    // Set preset for Reference for yellow animal.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->selectFieldOption('Add default value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $assert->pageTextContains('Set default value for Reference');
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $reference_filter_id . '][reference][0][entity_reference]', 'yellow (2)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['reference'] = [
      'key' => 'Reference',
      'value' => 'yellow',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');
    $assert->pageTextContains('Banana title');
    $assert->pageTextNotContains('Cherry title');
    $this->assertSession()->linkNotExistsExact('2');

    // Change the preset of Reference to the red fruit.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $assert->pageTextContains('Set default value for Reference');
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $reference_filter_id . '][reference][0][entity_reference]', 'Red (1)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters['reference'] = [
      'key' => 'Reference',
      'value' => 'Red',
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');
    $assert->pageTextNotContains('Banana title');
    $assert->pageTextNotContains('Cherry title');
    $this->assertSession()->linkNotExistsExact('1');
    $this->assertSession()->linkNotExistsExact('2');
    $this->assertSession()->linkNotExistsExact('3');

    // Remove filters.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-delete-' . $created_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('default-delete-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Add a Link filter.
    $page->selectFieldOption('Add default value for', 'Link');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $link_filter_id . '][link][0][link]', 'http://banana.com');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      [
        'key' => 'Link',
        'value' => 'Any of: http://banana.com',
      ],
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');
    $this->assertSession()->pageTextNotContains('Cherry title');
    $this->assertSession()->pageTextNotContains('Sun title');
    $this->assertSession()->pageTextNotContains('Grass title');
    $this->assertSession()->pageTextContains('Banana title');
    // Change the link to an internal one.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-edit-' . $link_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['title' => 'Sun title']);
    $sun_title = reset($nodes);
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $link_filter_id . '][link][0][link]', sprintf('%s (%s)', $sun_title->label(), $sun_title->id()));
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save');
    $this->assertSession()->pageTextNotContains('Cherry title');
    $this->assertSession()->pageTextNotContains('Sun title');
    $this->assertSession()->pageTextContains('Grass title');
    $this->assertSession()->pageTextNotContains('Banana title');

    // Remove filters.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-delete-' . $link_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Test an OR filter.
    $page->selectFieldOption('Add default value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $reference_filter_id . '][reference][0][entity_reference]', 'Green (3)');
    $page->pressButton('Add another item');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $reference_filter_id . '][reference][1][entity_reference]', 'Yellow (2)');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      [
        'key' => 'Reference',
        'value' => 'Any of: Green, Yellow',
      ],
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');
    $assert->pageTextContains('Banana title');
    $assert->pageTextContains('Grass title');
    $assert->pageTextContains('Sun title');
    $assert->pageTextNotContains('Cherry title');
    $this->assertSession()->linkNotExistsExact('3');
    $this->assertSession()->linkNotExistsExact('2');
    $this->assertSession()->linkNotExistsExact('1');

    // Test an AND filter.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->selectFieldOption($default_value_name_prefix . '[wrapper][edit][' . $reference_filter_id . '][oe_list_pages_filter_operator]', 'All of');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      [
        'key' => 'Reference',
        'value' => 'All of: Green, Yellow',
      ],
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');

    $assert->pageTextContains('Banana title');
    $assert->pageTextNotContains('Grass title');
    $assert->pageTextNotContains('Sun title');
    $assert->pageTextNotContains('Cherry title');

    // None filter.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->selectFieldOption($default_value_name_prefix . '[wrapper][edit][' . $reference_filter_id . '][oe_list_pages_filter_operator]', 'None of');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      [
        'key' => 'Reference',
        'value' => 'None of: Green, Yellow',
      ],
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');
    $assert->pageTextNotContains('Banana title');
    $assert->pageTextNotContains('Grass title');
    $assert->pageTextNotContains('Sun title');
    $assert->pageTextContains('Cherry title');

    // Filters combined.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-edit-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $reference_filter_id . '][reference][1][entity_reference]', '');
    $page->selectFieldOption($default_value_name_prefix . '[wrapper][edit][' . $reference_filter_id . '][oe_list_pages_filter_operator]', 'Any of');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      [
        'key' => 'Reference',
        'value' => 'Any of: Green',
      ],
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->selectFieldOption('Add default value for', 'Reference');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $second_reference_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('reference', [$reference_filter_id]);
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $second_reference_filter_id . '][reference][0][entity_reference]', 'Yellow (2)');
    $page->selectFieldOption($default_value_name_prefix . '[wrapper][edit][' . $second_reference_filter_id . '][oe_list_pages_filter_operator]', 'None of');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      [
        'key' => 'Reference',
        'value' => 'Any of: Green',
      ],
      [
        'key' => 'Reference',
        'value' => 'None of: Yellow',
      ],
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');
    $assert->pageTextNotContains('Banana title');
    $assert->pageTextContains('Grass title');
    $assert->pageTextNotContains('Sun title');
    $assert->pageTextNotContains('Cherry title');

    // Several filters for same field.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $page->pressButton('default-edit-' . $second_reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField($default_value_name_prefix . '[wrapper][edit][' . $second_reference_filter_id . '][reference][0][entity_reference]', 'Yellow (2)');
    $page->selectFieldOption($default_value_name_prefix . '[wrapper][edit][' . $second_reference_filter_id . '][oe_list_pages_filter_operator]', 'Any of');
    $page->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [
      [
        'key' => 'Reference',
        'value' => 'Any of: Green',
      ],
      [
        'key' => 'Reference',
        'value' => 'Any of: Yellow',
      ],
    ];
    $this->assertDefaultValueForFilters($expected_set_filters);
    $page->pressButton('Save');
    $assert->pageTextContains('Banana title');
    $assert->pageTextNotContains('Grass title');
    $assert->pageTextNotContains('Sun title');
    $assert->pageTextNotContains('Cherry title');
    $this->assertSession()->linkNotExistsExact('1');
    $this->assertSession()->linkNotExistsExact('2');
    $this->assertSession()->linkNotExistsExact('3');

    // Clear all default filters.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }

    $page->pressButton('default-delete-' . $reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('default-delete-' . $second_reference_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save');

    // Test the default string multiselect filter field plugin by creating a
    // node with a country code, a facet for country code which uses
    // a processor to turn country codes into country names.
    $values = [
      'title' => 'Node with country',
      'type' => 'content_type_one',
      'field_country_code' => 'BE',
    ];
    $node = Node::create($values);
    $node->save();
    Index::load('node')->indexItems();

    $facet = Facet::create([
      'id' => 'country',
      'name' => 'Country',
    ]);

    $facet->setUrlAlias('country');
    $facet->setFieldIdentifier('field_country_code');
    $facet->setEmptyBehavior(['behavior' => 'none']);
    $facet->setFacetSourceId('list_facet_source:node:content_type_one');
    $facet->setWidget('oe_list_pages_multiselect', []);
    $facet->addProcessor([
      'processor_id' => 'url_processor_handler',
      'weights' => ['pre_query' => -10, 'build' => -10],
      'settings' => [],
    ]);
    $facet->addProcessor([
      'processor_id' => 'oe_list_pages_address_format_country_code',
      'weights' => ['pre_query' => 60, 'build' => 35],
      'settings' => [],
    ]);
    $facet->save();

    // Edit the node and add the country code default value.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $country_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('country');

    $this->getSession()->getPage()->selectFieldOption('Add default value for', 'Country');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $country_filter_id . '][country][0][string]';
    $this->getSession()->getPage()->fillField($filter_selector, 'no country');
    $this->getSession()->getPage()->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [];
    $expected_set_filters['country'] = [
      'key' => 'Country',
      'value' => 'no country',
    ];

    $this->assertDefaultValueForFilters($expected_set_filters);
    $this->getSession()->getPage()->pressButton('Save');
    // We have no results with a dummy country.
    $this->assertSession()->pageTextNotContains('Node with country');
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }

    // Fill in the country code.
    $this->getSession()->getPage()->pressButton('default-edit-' . $country_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->fillField($filter_selector, 'BE');
    $this->getSession()->getPage()->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // The processor should change from country code to country name because the
    // default StringField multiselect filter field plugin doesn't know how to
    // do it.
    $expected_set_filters['country'] = [
      'key' => 'Country',
      'value' => 'Belgium',
    ];
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Node with country');

    // Now test an example in which we don't have a facet processor but we have
    // a facet-specific multiselect filter field plugin that creates a default
    // value form and default label.
    $this->clickLink('Edit');
    $link = $this->getSession()->getPage()->findLink('List Page');
    if ($link) {
      $link->click();
    }
    $this->getSession()->getPage()->pressButton('default-delete-' . $country_filter_id);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->selectFieldOption('Add default value for', 'Foo');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $foo_filter_id = DefaultFilterConfigurationBuilder::generateFilterId('oe_list_pages_filters_test_test_field');
    $filter_selector = $default_value_name_prefix . '[wrapper][edit][' . $foo_filter_id . '][oe_list_pages_filters_test_test_field][0][foo]';
    $this->getSession()->getPage()->selectFieldOption($filter_selector, 'Two');
    $this->getSession()->getPage()->pressButton('Set default value');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $expected_set_filters = [];
    $expected_set_filters['foo'] = [
      'key' => 'Foo',
      'value' => 'Two',
    ];
    $this->getSession()->getPage()->pressButton('Save');
  }

  /**
   * Get available options of select box.
   *
   * @param string $field
   *   The label, id or name of select box.
   *
   * @return array
   *   Select box options.
   */
  protected function getSelectOptions(string $field): array {
    $page = $this->getSession()->getPage();
    $options = $page->findField($field)->findAll('css', 'option');
    $actual_options = [];
    foreach ($options as $option) {
      $actual_options[$option->getValue()] = $option->getText();
    }
    return $actual_options;
  }

  /**
   * Asserts the default value is set for the filter.
   *
   * @param array $default_filters
   *   The default filters.
   */
  protected function assertDefaultValueForFilters(array $default_filters = []): void {
    $assert = $this->assertSession();
    $assert->elementsCount('css', 'table.default-filters-table tr', count($default_filters) + 1);
    foreach ($default_filters as $filter) {
      $key = $filter['key'];
      $default_value = $filter['value'];

      $assert->elementTextContains('css', 'table.default-filters-table', $key);
      $assert->elementTextContains('css', 'table.default-filters-table', $default_value);
    }
  }

  /**
   * Loads a single entity by its label.
   *
   * @param string $type
   *   The type of entity to load.
   * @param string $label
   *   The label of the entity to load.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function getEntityByLabel($type, $label): EntityInterface {
    $entity_type_manager = \Drupal::entityTypeManager();
    $property = $entity_type_manager->getDefinition($type)->getKey('label');

    $entity_list = $entity_type_manager->getStorage($type)->loadByProperties([$property => $label]);
    $entity_type_manager->getStorage($type)->resetCache(array_keys($entity_list));

    $entity = current($entity_list);
    if (!$entity) {
      $this->fail("No {$type} entity named {$label} found.");
    }

    return $entity;
  }

  /**
   * Asserts the contextual value is set for the filter.
   *
   * @param array $contextual_values
   *   The default filters.
   */
  protected function assertContextualValueForFilters(array $contextual_values = []): void {
    $assert = $this->assertSession();
    $assert->elementsCount('css', 'table.contextual-filters-table tr', count($contextual_values) + 1);
    $delta = 0;
    foreach ($contextual_values as $filter) {
      $key = $filter['key'];
      $operator = $filter['value'];
      $filter_source = $filter['filter_source'] ?? 'Field values';

      $row = $this->getSession()->getPage()->findAll('css', 'table.contextual-filters-table tbody tr')[$delta];
      $this->assertEquals($key, $row->findAll('css', 'td')[0]->getText());
      $this->assertEquals($operator, $row->findAll('css', 'td')[1]->getText());
      $this->assertEquals($filter_source, $row->findAll('css', 'td')[2]->getText());
      $delta++;
    }
  }

  /**
   * Runs specific steps needed to reach a preset filter form.
   */
  abstract protected function goToListPageConfiguration(): void;

}
