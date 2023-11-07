<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\widget;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\ProcessorPluginManager;
use Drupal\facets\Result\Result;
use Drupal\multivalue_form_element\Element\MultiValue;
use Drupal\oe_list_pages\FacetManipulationTrait;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceInterface;
use Drupal\oe_list_pages\MultiselectFilterFieldPluginManager;
use Drupal\oe_list_pages\Plugin\facets\processor\DefaultStatusProcessorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The multiselect list widget.
 *
 * @FacetsWidget(
 *   id = "oe_list_pages_multiselect",
 *   label = @Translation("List pages multiselect"),
 *   description = @Translation("A multiselect search widget."),
 * )
 */
class MultiselectWidget extends ListPagesWidgetBase implements ContainerFactoryPluginInterface {

  use FacetManipulationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The multiselect filter field plugin manager.
   *
   * @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginManager
   */
  protected $multiselectPluginManager;

  /**
   * The facets processor manager.
   *
   * @var \Drupal\facets\Processor\ProcessorPluginManager
   */
  protected $processorManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.multiselect_filter_field'),
      $container->get('plugin.manager.facets.processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, MultiselectFilterFieldPluginManager $multiselect_plugin_manager, ProcessorPluginManager $processorManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->multiselectPluginManager = $multiselect_plugin_manager;
    $this->processorManager = $processorManager;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildDefaultValueForm(array $form, FormStateInterface $form_state, FacetInterface $facet, ListPresetFilter $preset_filter = NULL): array {
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $filter_values = $preset_filter ? $preset_filter->getValues() : [];

    $form['oe_list_pages_filter_operator'] = [
      '#type' => 'select',
      '#default_value' => $preset_filter ? $preset_filter->getOperator() : ListPresetFilter::OR_OPERATOR,
      '#options' => ListPresetFilter::getOperators(),
      '#title' => $this->t('Operator'),
    ];

    $form[$facet->id()] = [
      '#type' => 'multivalue',
      '#title' => $facet->getName(),
      '#required' => TRUE,
    ];

    // There can be some facets which are configured to have a default status
    // from a list of available options so in this case we need to prepare
    // those options and present them in a select.
    if ($this->facetHasDefaultStatus($facet)) {
      $results = $this->processFacetResults($facet);
      $options = $this->transformResultsToOptions($results);

      $form[$facet->id()]['#default_value'] = $filter_values;
      $form[$facet->id()]['list'] = [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('Select'),
      ];

      $form_state->set('multivalue_child', 'list');
      return $form;
    }

    $id = $this->multiselectPluginManager->getPluginIdForFacet($facet, $list_source);
    if (!$id) {
      return $this->build($facet);
    }

    $config = [
      'facet' => $facet,
      'preset_filter' => $preset_filter,
      'list_source' => $list_source,
    ];
    /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
    $plugin = $this->multiselectPluginManager->createInstance($id, $config);
    $form[$facet->id()]['#default_value'] = $plugin->getDefaultValues();
    $form[$facet->id()][$id] = $plugin->buildDefaultValueForm($form, $form_state, $preset_filter);
    $form_state->set('multivalue_child', $id);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(FacetInterface $facet, ListSourceInterface $list_source, ListPresetFilter $filter): string {
    $filter_operators = ListPresetFilter::getOperators();

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
  public function build(FacetInterface $facet) {
    $results = $this->prepareResults($facet->getResults());

    $options = $this->transformResultsToOptions($results);

    if ($options) {
      $build[$facet->id()] = [
        '#type' => 'select',
        '#title' => $facet->getName(),
        '#options' => $options,
        '#multiple' => TRUE,
        '#default_value' => $facet->getActiveItems(),
      ];
    }

    $build['#cache']['contexts'] = [
      'url.query_args',
      'url.path',
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareDefaultFilterValue(FacetInterface $facet, array $form, FormStateInterface $form_state): array {
    $values = [];
    $child = $form_state->get('multivalue_child');

    // First, get the values from the submitted element.
    $element = NestedArray::getValue($form_state->getCompleteForm(), array_merge($form['#array_parents'], [$facet->id()]));
    if ($element) {
      foreach (Element::children($element) as $delta) {
        $sub_element = $element[$delta];
        if (isset($sub_element[$child]['#value']) && $sub_element[$child]['#value'] !== "") {
          $values[] = $sub_element[$child]['#value'];
        }
      }
    }

    // Allow the individual plugins that were used for building the element
    // to act on these values as there might be specificities that need some
    // extra processing.
    /** @var \Drupal\oe_list_pages\ListSourceInterface $list_source */
    $list_source = $form_state->get('list_source');
    $id = $this->multiselectPluginManager->getPluginIdForFacet($facet, $list_source);
    if ($id) {
      $config = [
        'facet' => $facet,
        'preset_filter' => NULL,
        'list_source' => $list_source,
      ];
      /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
      $plugin = $this->multiselectPluginManager->createInstance($id, $config);
      $values = $plugin->prepareDefaultFilterValues($values, $form, $form_state);
    }

    // Reset the element count in the form element state so that if we reload
    // the form in the same form embed, we don't show a different number of
    // input elements.
    $state_parents = array_merge($form['#parents'], [$facet->id()]);
    $element_state = MultiValue::getElementState($state_parents, $facet->id(), $form_state);
    $element_state['items_count'] = count($values);
    MultiValue::setElementState($state_parents, $facet->id(), $form_state, $element_state);

    $operator = $form_state->getValue('oe_list_pages_filter_operator');
    return [
      'operator' => $operator,
      'values' => $values,
    ];
  }

  /**
   * Checks if the facet uses a default status processor.
   *
   * This processor sets a default active item to the facet if there are no
   * other active items in the URL.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facets.
   *
   * @return bool
   *   Whether the facet uses this type of processor.
   */
  protected function facetHasDefaultStatus(FacetInterface $facet): bool {
    $configs = $facet->getProcessorConfigs();
    if (!$configs) {
      return FALSE;
    }

    $plugin_ids = array_keys($configs);
    foreach ($plugin_ids as $plugin_id) {
      $processor = $this->processorManager->createInstance($plugin_id, ['facet' => $facet]);
      if ($processor instanceof DefaultStatusProcessorInterface) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Suffix with number.
   *
   * @param \Drupal\facets\Result\ResultInterface[] $result
   *   The result to extract the values.
   *
   * @return array
   *   The values.
   */
  protected function prepareResults(array $results) {
    $results = array_filter($results, function (Result $result) {
      return $result->getDisplayValue() !== "";
    });

    if (!empty($results) && $this->getConfiguration()['show_numbers']) {
      /** @var \Drupal\facets\Result\ResultInterface $result */
      foreach ($results as $result) {
        if ($result->getCount() !== FALSE ) {
          $result->setDisplayValue($result->getDisplayValue() . ' (' . $result->getCount() . ')');
        }
      }
    }
    return $results;
  }

}
