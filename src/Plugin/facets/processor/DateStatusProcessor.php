<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\facets\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\PreQueryProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\Result\Result;
use Drupal\facets\UrlProcessor\UrlProcessorPluginManager;
use Drupal\oe_list_pages\Plugin\facets\query_type\DateStatus;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
class DateStatusProcessor extends ProcessorPluginBase implements PreQueryProcessorInterface, BuildProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * The URL processor plugin manager.
   *
   * @var \Drupal\facets\UrlProcessor\UrlProcessorPluginManager
   */
  protected $urlProcessorManager;

  /**
   * DateStatusProcessor constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Drupal\facets\UrlProcessor\UrlProcessorPluginManager $url_processor_manager
   *   The URL processor plugin manager.
   */
  public function __construct(array $configuration, string $plugin_id, array $plugin_definition, UrlProcessorPluginManager $url_processor_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->urlProcessorManager = $url_processor_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.facets.url_processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preQuery(FacetInterface $facet) {
    if ($facet->getActiveItems()) {
      // If there are active items in the facet, we do nothing.
      return;
    }

    // Check if there are any other active items in the URL because if there
    // are, we also won't do anything.
    /** @var \Drupal\facets\UrlProcessor\UrlProcessorInterface $url_processor */
    $plugin_id = $facet->getFacetSourceConfig()->getUrlProcessorName();
    $url_processor = $this->urlProcessorManager->createInstance($plugin_id, ['facet' => $facet]);
    if ($url_processor->getActiveFilters()) {
      return;
    }

    $default_status = $this->getConfiguration()['default_status'];
    if ($default_status) {
      $facet->setActiveItems([$default_status]);
    }
  }

  /**
   * Provides default options.
   *
   * @return array
   *   The default options.
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
      $result = new Result($facet, $raw, $display, 0);
      $facet_results[] = $result;
    }

    return $facet_results;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $this->getConfiguration();

    $build['default_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Default status'),
      '#default_value' => $this->getConfiguration()['default_status'],
      '#options' => $this->defaultOptions(),
    ];

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

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'default_status' => NULL,
      'upcoming_label' => $this->t('Upcoming'),
      'past_label' => $this->t('Past'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryType() {
    return 'oe_list_pages_date_status_comparison';
  }

}
