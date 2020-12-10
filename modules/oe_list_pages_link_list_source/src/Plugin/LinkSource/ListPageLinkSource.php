<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_source\Plugin\LinkSource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkCollectionInterface;
use Drupal\oe_link_lists\LinkSourcePluginBase;
use Drupal\oe_list_pages\Form\ListPageConfigurationSubformFactory;
use Drupal\oe_list_pages\ListExecutionManagerInterface;
use Drupal\oe_list_pages\ListPageConfiguration;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Link source plugin that integrates with List Pages.
 *
 * @LinkSource(
 *   id = "list_pages",
 *   label = @Translation("List pages"),
 *   description = @Translation("Source plugin that links to internal entities queried using the list pages.")
 * )
 */
class ListPageLinkSource extends LinkSourcePluginBase implements ContainerFactoryPluginInterface {

  use DependencySerializationTrait;

  /**
   * The list page subform configuration factory.
   *
   * @var \Drupal\oe_list_pages\Form\ListPageConfigurationSubformFactory
   */
  protected $configurationSubformFactory;

  /**
   * The list execution manager.
   *
   * @var \Drupal\oe_list_pages\ListExecutionManagerInterface
   */
  protected $listExecutionManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ListPageConfigurationSubformFactory $configurationSubformFactory, ListExecutionManagerInterface $listExecutionManager, EventDispatcherInterface $eventDispatcher, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->configurationSubformFactory = $configurationSubformFactory;
    $this->listExecutionManager = $listExecutionManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('oe_list_pages.list_page_configuration_subform_factory'),
      $container->get('oe_list_pages.execution_manager'),
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'entity_type' => '',
      'bundle' => '',
      'exposed_filters' => [],
      'exposed_filters_overridden' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getLinks(int $limit = NULL, int $offset = 0): LinkCollectionInterface {
    $links = new LinkCollection();
    $configuration = new ListPageConfiguration($this->configuration);
    if ($limit) {
      $configuration->setLimit($limit);
    }
    $cache = new CacheableMetadata();
    $list_execution = $this->listExecutionManager->executeList($configuration);
    if (!$list_execution) {
      $links->addCacheableDependency($cache);
      return $links;
    }
    $results = $list_execution->getResults();
    $query = $list_execution->getQuery();
    $cache->addCacheableDependency($query);
    $cache->addCacheTags(['search_api_list:' . $query->getIndex()->id()]);
    $cache->addCacheTags($this->entityTypeManager->getDefinition($configuration->getEntityType())->getListCacheTags());
    $cache->addCacheContexts($this->entityTypeManager->getDefinition($configuration->getEntityType())->getListCacheContexts());

    if (!$results->getResultCount()) {
      $links->addCacheableDependency($cache);
      return $links;
    }

    foreach ($results->getResultItems() as $item) {
      $entity = $item->getOriginalObject()->getEntity();
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $event = new EntityValueResolverEvent($entity);
      $this->eventDispatcher->dispatch(EntityValueResolverEvent::NAME, $event);
      $links[] = $event->getLink();
    }

    $links->addCacheableDependency($cache);
    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['list_page_configuration'] = [];
    $form['list_page_configuration']['#parents'] = array_merge($form['#parents'], ['list_page_configuration']);
    $form['list_page_configuration']['#parents_limit_validation_errors'] = [
      array_merge(array_slice($form['list_page_configuration']['#parents'], 0, 3), ['plugin']),
    ];
    $form['list_page_configuration']['#tree'] = TRUE;
    $subform_state = SubformState::createForSubform($form['list_page_configuration'], $form, $form_state);
    $configuration = new ListPageConfiguration($this->configuration);
    $configuration->setDefaultFilterValuesAllowed(TRUE);
    $subform = $this->configurationSubformFactory->getForm($configuration);
    $form['list_page_configuration'] = $subform->buildForm($form['list_page_configuration'], $subform_state);

    if (isset($form['list_page_configuration']['wrapper']['exposed_filters'])) {
      // We don't need the form to expose filters because in link lists we don't
      // use exposed facets.
      $form['list_page_configuration']['wrapper']['exposed_filters_override']['#access'] = FALSE;
      $form['list_page_configuration']['wrapper']['exposed_filters']['#access'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $element = $form['list_page_configuration'];
    $subform_state = SubformState::createForSubform($element, $form, $form_state);
    $configuration = new ListPageConfiguration($this->configuration);
    $subform = $this->configurationSubformFactory->getForm($configuration);
    $subform->submitForm($element, $subform_state);
    $configuration = $subform->getConfiguration();
    $this->configuration = $configuration->toArray();
  }

}
