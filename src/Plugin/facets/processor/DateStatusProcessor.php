<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\PreQueryProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\Result\Result;
use Drupal\oe_list_pages\Plugin\facets\query_type\DateStatusQueryType;

/**
 * Provides a processor to handle ongoing/past status.
 *
 * @FacetsProcessor(
 *   id = "oe_list_pages_date_status_processor",
 *   label = @Translation("Ongoing/Past status"),
 *   description = @Translation("Assign correct query type for ongoing/past status"),
 *   stages = {
 *     "pre_query" =60,
 *     "build" = 35
 *   }
 * )
 */
class DateStatusProcessor extends ProcessorPluginBase implements PreQueryProcessorInterface, BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function preQuery(FacetInterface $facet) {
    $active_items = $facet->getActiveItems();
    if (empty($active_items)) {
      $facet->setActiveItems([DateStatusQueryType::UPCOMING]);
    }
  }

  /**
   * Provides default options.
   *
   * @return array
   *   The default options.
   */
  public static function defaultOptions(): array {
    return [
      DateStatusQueryType::PAST => t('Past'),
      DateStatusQueryType::UPCOMING => t('Upcoming'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results) {
    // Get default options for status.
    $default_options = self::defaultOptions();
    $facet_results = [];
    foreach ($default_options as $raw => $display) {
      $result = new Result($facet, $raw, $display, 0);
      $facet_results[] = $result;
    }

    return $facet_results;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'oe_list_pages_date_status_comparison';
  }

}
