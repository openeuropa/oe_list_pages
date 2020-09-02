<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\PreQueryProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
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
 *   }
 * )
 */
class DateStatusProcessor extends ProcessorPluginBase implements PreQueryProcessorInterface {

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
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'oe_list_pages_date_status_comparison';
  }

}
