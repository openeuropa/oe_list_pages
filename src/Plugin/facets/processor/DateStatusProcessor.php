<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\PreQueryProcessorInterface;
use Drupal\facets\Result\Result;
use Drupal\oe_list_pages\Plugin\facets\query_type\DateStatus;

/**
 * Provides a processor to handle upcoming/past status.
 *
 * @FacetsProcessor(
 *   id = "oe_list_pages_date_status_processor",
 *   label = @Translation("Ongoing/Past status"),
 *   description = @Translation("Assign correct query type for upcoming/past status"),
 *   stages = {
 *     "pre_query" = 60,
 *     "build" = 35
 *   }
 * )
 */
class DateStatusProcessor extends DefaultStatusProcessorBase implements PreQueryProcessorInterface, BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  protected function defaultOptions(): array {
    return [
      '' => $this->t('- None -'),
      DateStatus::UPCOMING => $this->getConfiguration()['upcoming_label'],
      DateStatus::PAST => $this->getConfiguration()['past_label'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $facet_results = [];
    $default_options = $this->defaultOptions();

    // If we already have results we just need to add the configured labels.
    if (!empty($results)) {
      /** @var \Drupal\facets\Result\Result $result */
      foreach ($results as $result) {
        $result->setDisplayValue($default_options[$result->getRawValue()]);
        $facet_results[] = $result;
      }

      return $facet_results;
    }

    // Otherwise provide default labels.
    foreach ($default_options as $raw => $display) {
      if ($raw === '') {
        continue;
      }
      $result = new Result($facet, $raw, $display, 0);
      $facet_results[] = $result;
    }

    return $facet_results;
  }

  /**
   * {@inheritdoc}
   */
  public function preQuery(FacetInterface $facet) {
    parent::preQuery($facet);

    $sort_alter_field_identifier = $this->getConfiguration()['sort_alter_field_identifier'];
    if ($sort_alter_field_identifier) {
      // Store the value onto the facet so that we can use it at the query
      // stage.
      $facet->set('default_status_sort_alter_field_identifier', $sort_alter_field_identifier);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $build = parent::buildConfigurationForm($form, $form_state, $facet);

    $build['upcoming_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upcoming label'),
      '#description' => $this->t('Label for upcoming state'),
      '#default_value' => $this->getConfiguration()['upcoming_label'],
    ];

    $build['past_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Past label'),
      '#description' => $this->t('Label for past state'),
      '#default_value' => $this->getConfiguration()['past_label'],
    ];

    $build['sort_alter_field_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sort alter field identifier'),
      '#description' => $this->t('The field to be used for altering the sort. This is if the default sort for the query is different than the one used in this facet. Leave empty to use the one in the facet.'),
      '#default_value' => $this->getConfiguration()['sort_alter_field_identifier'],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'upcoming_label' => $this->t('Upcoming'),
      'past_label' => $this->t('Past'),
      'sort_alter_field_identifier' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'oe_list_pages_date_status_comparison';
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    $supported_types = [
      'field_item:daterange',
      'field_item:datetime',
      'field_item:created',
      'datetime_iso8601',
      'date',
    ];

    $data_definition = $facet->getDataDefinition();
    if (in_array($data_definition->getDataType(), $supported_types)) {
      return TRUE;
    }

    if (!($data_definition instanceof ComplexDataDefinitionInterface)) {
      return FALSE;
    }

    $property_definitions = $data_definition->getPropertyDefinitions();
    foreach ($property_definitions as $definition) {
      if (in_array($definition->getDataType(), $supported_types)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
