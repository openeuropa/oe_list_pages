<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\oe_list_pages\Form\ListFacetsForm;
use Drupal\oe_list_pages\ListSourceFactory;

/**
 * Tests the list facet form.
 */
class ListFacetsFormTest extends ListsSourceTestBase {

  /**
   * Tests the facet cache tags are correctly applied on the form.
   */
  public function testFacetCacheTags(): void {
    $default_list_id = ListSourceFactory::generateFacetSourcePluginId('entity_test_mulrev_changed', 'item');
    $this->facet = $this->createFacet('created', $default_list_id, '', 'oe_list_pages_multiselect', []);
    $this->facet->addProcessor([
      'processor_id' => 'oe_list_pages_date_status_processor',
      'weights' => [],
      'settings' => [],
    ]);

    $this->facet->save();

    $list = $this->listFactory->get('entity_test_mulrev_changed', 'item');

    $form_state = new FormState();
    $form_state->setRequestMethod('POST');
    $form_state->setCached();
    $form = ListFacetsForm::create($this->container);
    $form_array = $form->buildForm([], $form_state, $list, []);
    $expected_cache_tags = [
      'config:facets_facet_list',
      'config:facets.facet.list_facet_source_entity_test_mulrev_changed_itemcreated',
    ];
    $this->assertEquals($expected_cache_tags, $form_array['#cache']['tags']);
  }

}
