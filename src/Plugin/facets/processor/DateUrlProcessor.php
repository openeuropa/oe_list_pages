<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\processor;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Plugin\facets\processor\UrlProcessorHandler;
use Drupal\facets\Result\Result;
use Drupal\oe_list_pages\Plugin\facets\query_type\Date;

/**
 * The URL processor handler for adapting active filter for date fields.
 *
 * @FacetsProcessor(
 *   id = "date_processor_handler",
 *   label = @Translation("Date URL processor"),
 *   description = @Translation("Required processor to interpret the date active filters."),
 *   stages = {
 *     "pre_query" = 50,
 *     "build" = 15,
 *   },
 *   locked = false
 * )
 */
class DateUrlProcessor extends UrlProcessorHandler {

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    $facet_results = [];

    if (!empty($results)) {
      return $results;
    }

    $active_filters = Date::getActiveItems($facet);
    if (!$active_filters) {
      return $facet_results;
    }

    $operator = $active_filters['operator'];
    $first_date = $active_filters['first'];
    $second_date = isset($active_filters['second']) ? $active_filters['second'] : NULL;

    $operators = [
      'gt' => $this->t('After'),
      'lt' => $this->t('Before'),
      'bt' => $this->t('Between'),
    ];

    if (!isset($operators[$operator])) {
      return $facet_results;
    }

    if ($operator === 'bt') {
      $display = new FormattableMarkup('@operator @first and @second', [
        '@operator' => $operators[$operator],
        '@first' => $first_date->format('j F Y'),
        '@second' => $second_date->format('j F Y'),
      ]);
      $result = new Result($facet, $active_filters['_raw'], $display, 0);
      $facet_results[] = $result;
      return $facet_results;
    }

    $display = new FormattableMarkup('@operator @first', [
      '@operator' => $operators[$operator],
      '@first' => $first_date->format('j F Y'),
    ]);
    $result = new Result($facet, $active_filters['_raw'], $display, 0);
    $facet_results[] = $result;
    return $facet_results;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsFacet(FacetInterface $facet) {
    $supported_types = [
      "field_item:daterange",
      "field_item:datetime",
      "datetime_iso8601",
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
