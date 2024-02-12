<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Plugin\facets\processor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\UrlProcessor\UrlProcessorPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for facets processors that have a default value.
 */
abstract class DefaultStatusProcessorBase extends ProcessorPluginBase implements DefaultStatusProcessorInterface, ContainerFactoryPluginInterface {

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
      // Keep the active items in the facet until the last moment when we
      // subscribe to the query and apply them if needed.
      // @see \Drupal\oe_list_pages\EventSubscriber\QuerySubscriber
      $facet->set('default_status_active_items', [$default_status]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
    $build = parent::buildConfigurationForm($form, $form_state, $facet);

    $build['default_status'] = [
      '#type' => 'select',
      '#title' => $this->t('Default status'),
      '#default_value' => $this->getConfiguration()['default_status'],
      '#options' => $this->defaultOptions(),
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'default_status' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * Returns the default options available for the status.
   *
   * @return array
   *   The options.
   */
  abstract protected function defaultOptions(): array;

}
