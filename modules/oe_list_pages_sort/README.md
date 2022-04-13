# OpenEuropa List Pages Sort

This module provides a form in the list pages to sort the results.

/**
* Implements hook_form_BASE_FORM_ID_alter() for oe_list_page nodes form.
  */
  function oe_list_pages_sort_form_node_form_alter(&$form, FormStateInterface $form_state, $form_id) {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $form_state->getFormObject()->getEntity();

$configuration = ListPageConfiguration::fromEntity($node);
dpm($configuration->getBundle());
dpm($configuration->getEntityType());
dpm($configuration->toArray());

$list_source_factory = \Drupal::service('oe_list_pages.list_source.factory');
$list_source = $list_source_factory->get('node', $configuration->getBundle());
dpm($list_source->getAvailableFilters());
}
