<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_filters_test\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\PreQueryProcessorInterface;
use Drupal\facets\Result\Result;
use Drupal\oe_list_pages\Plugin\facets\processor\DefaultStatusProcessorBase;

/**
 * Default status processor for the foo fake field.
 *
 * @FacetsProcessor(
 *   id = "oe_list_pages_filters_test_foo_processor",
 *   label = @Translation("Foo default status processor"),
 *   description = @Translation("Test default status processor."),
 *   stages = {
 *     "pre_query" = 60,
 *     "build" = 35
 *   }
 * )
 */
class FooFakeFieldProcessor extends DefaultStatusProcessorBase implements PreQueryProcessorInterface, BuildProcessorInterface {

  /**
   * {@inheritdoc}
   */
  protected function defaultOptions(): array {
    return [
      '' => $this->t('- None -'),
      1 => 1,
      2 => 2,
      3 => 3,
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
  public function getQueryType() {
    return 'string';
  }

}
