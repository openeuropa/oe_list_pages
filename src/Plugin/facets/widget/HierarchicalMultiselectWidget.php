<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceInterface;

/**
 * The hierarchical multiselect list widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_hierarchical_multiselect_widget",
 *   label = @Translation("Hierarchical multiselect widget"),
 *   description = @Translation("A Hierarchical multiselect widget."),
 * )
 */
class HierarchicalMultiselectWidget extends MultiselectWidget implements ContainerFactoryPluginInterface {

  const AND_WITH_HIERARCHY_OPERATOR = 'and_with_hierarchy';
  const OR_WITH_HIERARCHY_OPERATOR = 'or_with_hierarchy';
  const NONE_WITH_HIERARCHY_OPERATOR = 'not_with_hierarchy';

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildDefaultValueForm(array $form, FormStateInterface $form_state, FacetInterface $facet, ListPresetFilter $preset_filter = NULL): array {
    $form = parent::buildDefaultValueForm($form, $form_state, $facet, $preset_filter);
    $form['oe_list_pages_filter_operator']['#options'] = $this->getOperators();
    $form['oe_list_pages_filter_operator']['#default_value'] = $preset_filter ? $preset_filter->getOperator() : self::OR_WITH_HIERARCHY_OPERATOR;
    return $form;
  }

  /**
   * Get available operators.
   *
   * @return array
   *   The operators.
   */
  protected function getOperators() {
    $new_operators = [
      self::AND_WITH_HIERARCHY_OPERATOR => $this->t('All of (with hierarchy)'),
      self::OR_WITH_HIERARCHY_OPERATOR => $this->t('Any of (with hierarchy)'),
      self::NONE_WITH_HIERARCHY_OPERATOR => $this->t('None of (with hierarchy)'),
    ];

    return ListPresetFilter::getOperators() + $new_operators;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(FacetInterface $facet, ListSourceInterface $list_source, ListPresetFilter $filter): string {
    $filter_operators = $this->getOperators();

    $id = $this->multiselectPluginManager->getPluginIdForFacet($facet, $list_source);
    if (!$id) {
      return $filter_operators[$filter->getOperator()] . ': ' . parent::getDefaultValuesLabel($facet, $list_source, $filter);
    }

    $config = [
      'facet' => $facet,
      'preset_filter' => $filter,
      'list_source' => $list_source,
    ];
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->multiselectPluginManager->createInstance($id, $config);

    return $filter_operators[$filter->getOperator()] . ': ' . $plugin->getDefaultValuesLabel();
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'oe_list_pages_hierarchy_comparison';
  }

}
