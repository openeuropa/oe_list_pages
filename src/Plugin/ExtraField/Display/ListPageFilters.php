<?php

declare(strict_types = 1);

namespace Drupal\oe_List_pages\Plugin\ExtraField\Display;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\extra_field\Plugin\ExtraFieldDisplayFormattedBase;
use Drupal\oe_list_pages\ListBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extra field displaying the list page filters.
 *
 * @ExtraFieldDisplay(
 *   id = "oe_list_page_filters",
 *   label = @Translation("List page filters"),
 *   deriver = "Drupal\oe_list_pages\Plugin\ExtraField\Derivative\ListPageDeriver",
 *   visible = true
 * )
 */
class ListPageFilters extends ExtraFieldDisplayFormattedBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The list builder.
   *
   * @var \Drupal\oe_list_pages\ListBuilderInterface
   */
  protected $listBuilder;

  /**
   * ListPageFilters constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\oe_list_pages\ListBuilderInterface $listBuilder
   *   The list builder.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ListBuilderInterface $listBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->listBuilder = $listBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oe_list_pages.builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->t('List page filters');
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(ContentEntityInterface $entity) {
    return [$this->listBuilder->buildFiltersForm($entity)];
  }

}
