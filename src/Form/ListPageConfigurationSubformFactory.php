<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListPageConfigurationFactoryInterface;
use Drupal\oe_list_pages\ListPresetFiltersBuilder;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Factory for instantiating the list page configuration subform.
 */
class ListPageConfigurationSubformFactory implements ListPageConfigurationFactoryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The list source factory.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactoryInterface
   */
  protected $listSourceFactory;

  /**
   * The preset filters builder.
   *
   * @var \Drupal\oe_list_pages\ListPresetFiltersBuilder
   */
  protected $presetFiltersBuilder;

  /**
   * ListPageConfigurationSubformFactory constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\oe_list_pages\ListSourceFactoryInterface $listSourceFactory
   *   The list source factory.
   * @param \Drupal\oe_list_pages\ListPresetFiltersBuilder $preset_filters_builder
   *   The preset list builder.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, EventDispatcherInterface $eventDispatcher, ListSourceFactoryInterface $listSourceFactory, ListPresetFiltersBuilder $preset_filters_builder) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityTypeBundleInfo = $entityTypeBundleInfo;
    $this->eventDispatcher = $eventDispatcher;
    $this->listSourceFactory = $listSourceFactory;
    $this->presetFiltersBuilder = $preset_filters_builder;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(ListPageConfiguration $configuration): ListPageConfigurationSubForm {
    return new ListPageConfigurationSubForm($configuration, $this->entityTypeManager, $this->entityTypeBundleInfo, $this->eventDispatcher, $this->listSourceFactory, $this->presetFiltersBuilder);
  }

}
