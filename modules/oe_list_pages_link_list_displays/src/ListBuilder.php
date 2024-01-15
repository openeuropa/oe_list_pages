<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_link_list_displays;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\facets\Processor\ProcessorPluginManager;
use Drupal\facets\UrlProcessor\UrlProcessorPluginManager;
use Drupal\facets\Utility\FacetsUrlGenerator;
use Drupal\oe_link_lists\Event\EntityValueResolverEvent;
use Drupal\oe_link_lists\LinkCollection;
use Drupal\oe_link_lists\LinkDisplayPluginManagerInterface;
use Drupal\oe_list_pages\ListBuilder as DefaultListBuilder;
use Drupal\oe_list_pages\ListExecutionManagerInterface;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListSourceFactory;
use Drupal\oe_list_pages\MultiselectFilterFieldPluginManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Link List display-based list builder.
 *
 * We take over the original list builder and use the chosen display plugins
 * to render the list.
 */
class ListBuilder extends DefaultListBuilder {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The link display manager.
   *
   * @var \Drupal\oe_link_lists\LinkDisplayPluginManagerInterface
   */
  protected $linkDisplayPluginManager;

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(ListExecutionManagerInterface $listExecutionManager, EntityTypeManager $entityTypeManager, PagerManagerInterface $pager, EntityRepositoryInterface $entityRepository, FormBuilderInterface $formBuilder, FacetsUrlGenerator $facetsUrlGenerator, ProcessorPluginManager $processorManager, RequestStack $requestStack, UrlProcessorPluginManager $urlProcessorManager, MultiselectFilterFieldPluginManager $multiselectFilterManager, ListSourceFactory $listSourceFactory, EventDispatcherInterface $eventDispatcher, LinkDisplayPluginManagerInterface $linkDisplayPluginManager) {
    parent::__construct($listExecutionManager, $entityTypeManager, $pager, $entityRepository, $formBuilder, $facetsUrlGenerator, $processorManager, $requestStack, $urlProcessorManager, $multiselectFilterManager, $listSourceFactory);
    $this->eventDispatcher = $eventDispatcher;
    $this->linkDisplayPluginManager = $linkDisplayPluginManager;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildList(ListPageConfiguration $configuration): array {
    $extra = $configuration->getExtra();
    if (!isset($extra['oe_list_pages_link_list_displays'])) {
      // If we don't have the displays configured, we simply defer back to the
      // parent like nothing happened.
      return parent::buildList($configuration);
    }

    // Verify that the chosen display plugin exists.
    $display_configuration = $extra['oe_list_pages_link_list_displays'];
    $display_plugin = $display_configuration['display']['plugin'];
    $display_plugin_configuration = $display_configuration['display']['plugin_configuration'] ?? [];
    if (!$this->linkDisplayPluginManager->hasDefinition($display_plugin)) {
      return parent::buildList($configuration);
    }

    $build = [
      'list' => [],
    ];

    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['url.query_args']);

    $sort = $this->getSortFromUrl($configuration);
    // The sort could potentially be overridden from the URL.
    if ($sort) {
      $configuration->setSort($sort);
    }

    $list_execution = $this->listExecutionManager->executeList($configuration);
    if (empty($list_execution)) {
      $cache->applyTo($build);
      return $build;
    }

    $list_source = $list_execution->getListSource();
    if (!$list_source) {
      $cache->applyTo($build);
      return $build;
    }

    $query = $list_execution->getQuery();
    $cache->addCacheableDependency($query);
    $cache->addCacheTags($query->getCacheTags());
    $result = $list_execution->getResults();
    $configuration = $list_execution->getConfiguration();
    $cache->addCacheTags([$configuration->getEntityType() . '_list:' . $configuration->getBundle()]);

    $this->pager->createPager($result->getResultCount(), $query->getOption('limit'));
    $build['pager'] = [
      '#type' => 'pager',
    ];

    if (!$result->getResultCount()) {
      $cache->applyTo($build);
      return $build;
    }

    $links = new LinkCollection();
    foreach ($result->getResultItems() as $item) {
      try {
        // Do not crash the application in case the index still has an item in
        // it pointing to an entity that got deleted.
        $entity = $item->getOriginalObject()->getEntity();
      }
      catch (\Exception $exception) {
        continue;
      }

      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $this->entityRepository->getTranslationFromContext($entity);

      // Turn the entity into a LinkInterface.
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $event = new EntityValueResolverEvent($entity);
      $this->eventDispatcher->dispatch($event, EntityValueResolverEvent::NAME);
      $cache->addCacheableDependency($entity);
      $links->add($event->getLink());
    }

    $links->addCacheableDependency($cache);

    // Check each link for access before rendering.
    foreach ($links as $key => $link) {
      /** @var \Drupal\oe_link_lists\LinkInterface $link */
      $access = $link->access('view', NULL, TRUE);
      $cache->addCacheableDependency($access);

      if (!$access->isAllowed()) {
        unset($links[$key]);
      }
    }

    if ($links->isEmpty()) {
      $build = [];
      $cache->addCacheableDependency($links);
      $cache->applyTo($build);
      return $build;
    }

    // Prepare the links for rendering with the chosen display plugin.
    /** @var \Drupal\oe_link_lists\LinkDisplayInterface $plugin */
    $plugin = $this->linkDisplayPluginManager->createInstance($display_plugin, $display_plugin_configuration);
    $build['list'] = $plugin->build($links);
    $cache->addCacheableDependency($links);
    $cache->applyTo($build);

    return $build;
  }

}
