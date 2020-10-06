<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\oe_list_pages\ListBuilderInterface;
use Drupal\oe_list_pages\ListExecutionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extra field displaying the list page total count.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_list_page_total_count",
 *   label = @Translation("List page total count"),
 *   deriver = "Drupal\oe_list_pages\Plugin\ExtraField\Derivative\ListPageDeriver",
 * )
 */
class ListPageTotalCount extends ListPageExtraFieldBase {

  /**
   * The list execution manager.
   *
   * @var \Drupal\oe_list_pages\ListExecutionManagerInterface
   */
  protected $listExecutionManager;

  /**
   * ListPageTotalCount constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\oe_list_pages\ListBuilderInterface $listBuilder
   *   The list builder.
   * @param \Drupal\oe_list_pages\ListExecutionManagerInterface $listExecutionManager
   *   The list execution manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ListBuilderInterface $listBuilder, ListExecutionManagerInterface $listExecutionManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $listBuilder);
    $this->listExecutionManager = $listExecutionManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oe_list_pages.builder'),
      $container->get('oe_list_pages.execution_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('List page total count');
  }

  /**
   * {@inheritdoc}
   */
  public function view(ContentEntityInterface $entity) {
    $execution = $this->listExecutionManager->executeList($entity);
    $results = $execution->getResults();
    return [
      '#markup' => $results->getResultCount(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    // We take over the main ::view() method so we don't need this.
  }

}
