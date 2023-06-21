<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\GeneratedUrl;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\emr\Entity\EntityMetaInterface;
use Drupal\facets\UrlProcessor\UrlProcessorPluginManager;
use Drupal\node\NodeInterface;
use Drupal\oe_list_pages\FacetManipulationTrait;
use Drupal\oe_list_pages\ListExecutionManagerInterface;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages\ListPageEvents;
use Drupal\oe_list_pages\ListPageRssAlterEvent;
use Drupal\oe_list_pages\ListPageRssItemAlterEvent;
use Drupal\oe_list_pages\ListPresetFilter;
use Drupal\oe_list_pages\ListSourceFactoryInterface;
use Drupal\oe_list_pages\MultiselectFilterFieldPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the List pages RSS feed routes.
 */
class ListPageRssController extends ControllerBase {

  use FacetManipulationTrait;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The list execution manager.
   *
   * @var \Drupal\oe_list_pages\ListExecutionManagerInterface
   */
  protected $listExecutionManager;

  /**
   * The list source factory manager.
   *
   * @var \Drupal\oe_list_pages\ListSourceFactoryInterface
   */
  protected $listSourceFactory;

  /**
   * The multiselect filter field plugin manager.
   *
   * @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginManager
   */
  protected $multiselectPluginManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The plugin manager for URL Processors.
   *
   * @var \Drupal\facets\UrlProcessor\UrlProcessorPluginManager
   */
  protected $urlProcessorPluginManager;

  /**
   * ListPageRssController constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\oe_list_pages\ListExecutionManagerInterface $list_execution_manager
   *   The list execution manager.
   * @param \Drupal\oe_list_pages\ListSourceFactoryInterface $list_source_factory
   *   The list source factory.
   * @param \Drupal\oe_list_pages\MultiselectFilterFieldPluginManager $multiselect_plugin_manager
   *   The multiselect filter field plugin manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request
   *   The request stack.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\facets\UrlProcessor\UrlProcessorPluginManager $url_processor_plugin_manager
   *   The url processor plugin manager.
   *
   * @SuppressWarnings(PHPMD.ExcessiveParameterList)
   */
  public function __construct(ConfigFactoryInterface $config_factory, DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, LanguageManagerInterface $language_manager, ListExecutionManagerInterface $list_execution_manager, ListSourceFactoryInterface $list_source_factory, MultiselectFilterFieldPluginManager $multiselect_plugin_manager, RendererInterface $renderer, RequestStack $request, ThemeManagerInterface $theme_manager, UrlProcessorPluginManager $url_processor_plugin_manager) {
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->languageManager = $language_manager;
    $this->listExecutionManager = $list_execution_manager;
    $this->listSourceFactory = $list_source_factory;
    $this->multiselectPluginManager = $multiselect_plugin_manager;
    $this->renderer = $renderer;
    $this->request = $request;
    $this->themeManager = $theme_manager;
    $this->urlProcessorPluginManager = $url_processor_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ListPageRssController {
    return new static(
      $container->get('config.factory'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('language_manager'),
      $container->get('oe_list_pages.execution_manager'),
      $container->get('oe_list_pages.list_source.factory'),
      $container->get('plugin.manager.multiselect_filter_field'),
      $container->get('renderer'),
      $container->get('request_stack'),
      $container->get('theme.manager'),
      $container->get('plugin.manager.facets.url_processor')
    );
  }

  /**
   * Render the RSS feed of a list page result.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The list page node.
   *
   * @return \Drupal\Core\Cache\CacheableResponse
   *   The RSS feed response.
   */
  public function build(NodeInterface $node): CacheableResponse {
    $language = $this->languageManager->getCurrentLanguage();
    if ($node->hasTranslation($language->getId())) {
      $node = $node->getTranslation($language->getId());
    }
    // Build the render array for the RSS feed.
    $cache_metadata = CacheableMetadata::createFromObject($node);
    $default_title = $this->getChannelTitle($node, $cache_metadata);
    $query = $this->request->getCurrentRequest()->query->all();
    $default_link = $node->toUrl('canonical', [
      'absolute' => TRUE,
      'query' => $query,
      'language' => $language,
    ])->toString(TRUE);
    $atom_link = $this->request->getCurrentRequest()->getSchemeAndHttpHost() . $this->request->getCurrentRequest()->getRequestUri();
    $cache_metadata->addCacheableDependency($default_link);
    $build = [
      '#theme' => 'oe_list_pages_rss',
      '#title' => $default_title,
      '#link' => $default_link->getGeneratedUrl(),
      '#atom_link' => $atom_link,
      '#language' => $language->getId(),
      '#copyright' => $this->getChannelCopyright(),
      '#image' => $this->getChannelImage($default_link, $cache_metadata),
      '#channel_elements' => [],
      '#items' => $this->getItemList($node, $cache_metadata),
    ];
    $default_description = $this->getChannelDescription($node, $cache_metadata);
    $build['#channel_description'] = empty($default_description) ? $default_title : $default_description;
    $cache_metadata->applyTo($build);

    // Dispatch event to allow modules to alter the build before being rendered.
    $event = new ListPageRssAlterEvent($build, $node);
    $this->eventDispatcher->dispatch($event, ListPageEvents::ALTER_RSS_BUILD);
    $build = $event->getBuild();
    $cache_metadata = CacheableMetadata::createFromRenderArray($build);
    // Create the response and add the xml type header.
    $response = new CacheableResponse('', 200);
    $response->addCacheableDependency($cache_metadata);
    $response->headers->set('Content-Type', 'application/rss+xml; charset=utf-8');

    // Render the list and add it to the response.
    $output = (string) $this->renderer->renderRoot($build);
    if (empty($output)) {
      throw new NotFoundHttpException();
    }
    $response->setContent($output);
    $response->addCacheableDependency($cache_metadata);

    return $response;
  }

  /**
   * Returns a list of items to be rendered.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node containing the list.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The current cache metadata.
   *
   * @return array
   *   Array of items to be rendered.
   */
  protected function getItemList(NodeInterface $node, CacheableMetadata $cache_metadata): array {
    $configuration = ListPageConfiguration::fromEntity($node);
    // Always sort by the updated date descending.
    $configuration->setSort([
      'name' => 'changed',
      'direction' => 'DESC',
    ]);
    // Always limit the rss to the last 25 results.
    $configuration->setLimit(25);
    // Always use the first page to calculate the offset.
    $configuration->setPage(0);
    $execution_result = $this->listExecutionManager->executeList($configuration);
    $query = $execution_result->getQuery();
    $results = $execution_result->getResults();
    $cache_metadata->addCacheableDependency($query);
    $cache_metadata->addCacheTags([$configuration->getEntityType() . '_list:' . $configuration->getBundle()]);

    $result_items = [];
    foreach ($results->getResultItems() as $item) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $item->getOriginalObject()->getEntity();
      $cache_metadata->addCacheableDependency($entity);
      $description = '';
      // Add a default description if the body is available.
      if ($entity->hasField('body')) {
        $description = $entity->get('body')->view([
          'type' => 'text_default',
          'label' => 'hidden',
          'settings' => [],
        ]);
        // We need to escape the results of renderPlain in order to maintain
        // all tags added by the renderer. If we don't do it, things like p tags
        // and linebreaks will be lost after they go through the twig template.
        $description = $this->renderer->renderPlain($description[0]);
      }
      $result_item = [
        '#theme' => 'oe_list_pages_rss_item',
        '#title' => $entity->label(),
        '#link' => $entity->toUrl('canonical', ['absolute' => TRUE]),
        '#guid' => $entity->toUrl('canonical', ['absolute' => TRUE]),
        '#item_description' => (string) $description,
        '#item_elements' => [],
      ];

      // Add the latest update date if available.
      if ($entity->hasField('changed')) {
        $creation_date = $entity->get('changed')->value;
        $result_item['#item_elements']['pubDate'] = [
          '#type' => 'html_tag',
          '#tag' => 'pubDate',
          '#value' => $this->dateFormatter->format($creation_date, 'custom', \DateTimeInterface::RFC2822, NULL, 'en'),
        ];
      }

      // Dispatch event to allow to alter the item build before being rendered.
      $event = new ListPageRssItemAlterEvent($result_item, $entity);
      $this->eventDispatcher->dispatch($event, ListPageEvents::ALTER_RSS_ITEM_BUILD);

      $result_items[] = $event->getBuild();
    }
    return $result_items;
  }

  /**
   * Asserts whether we can access the RSS route for a given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(NodeInterface $node): AccessResultInterface {
    $entity_meta = $node->get('emr_entity_metas')->getEntityMeta('oe_list_page');
    if ($entity_meta instanceof EntityMetaInterface && !$entity_meta->isNew()) {
      return AccessResult::allowed()->addCacheableDependency($node);
    }

    return AccessResult::forbidden('Node type does not have List Page meta configured.')->addCacheableDependency($node);
  }

  /**
   * Get the channel title.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being rendered.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The current cache metadata.
   *
   * @return string
   *   The channel title.
   */
  protected function getChannelTitle(NodeInterface $node, CacheableMetadata $cache_metadata): string {
    $site_config = $this->configFactory->get('system.site');
    $cache_metadata->addCacheableDependency($site_config);
    $site_name = $site_config->get('name');
    return $site_name . ' | ' . $node->label();
  }

  /**
   * Get the channel image array.
   *
   * @param \Drupal\Core\GeneratedUrl $url
   *   The url to use.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The current cache metadata.
   *
   * @return array
   *   The image information array.
   */
  protected function getChannelImage(GeneratedUrl $url, CacheableMetadata $cache_metadata): array {
    $site_config = $this->configFactory->get('system.site');
    $cache_metadata->addCacheableDependency($site_config);
    $site_name = $site_config->get('name');
    $title = $this->t('@site_name logo', ['@site_name' => $site_name]);
    // Get the logo location.
    $theme = $this->themeManager->getActiveTheme();
    $cache_metadata->addCacheContexts(['theme']);
    return [
      'url' => \Drupal::service('file_url_generator')->generateAbsoluteString($theme->getLogo()),
      'title' => $title,
      'link' => $url->getGeneratedUrl(),
    ];
  }

  /**
   * Get the channel description.
   *
   * The default channel description shows a complete list of selected
   * filter labels and values.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being rendered.
   * @param \Drupal\Core\Cache\CacheableMetadata $cache_metadata
   *   The current cache metadata.
   *
   * @return string
   *   The default description.
   */
  protected function getChannelDescription(NodeInterface $node, CacheableMetadata $cache_metadata): string {
    $cache_metadata->addCacheContexts(['url']);
    $categories = [];
    $keyed_facets = [];
    $active_filters_values = [];

    $configuration = ListPageConfiguration::fromEntity($node);
    $list_source = $this->listSourceFactory->get($configuration->getEntityType(), $configuration->getBundle());
    $available_filters = array_keys($list_source->getAvailableFilters());
    if (!$available_filters) {
      return '';
    }

    $facets = $this->getKeyedFacetsFromSource($list_source);
    // Load one of the source facets because we need to use it to determine the
    // current active filters using the query_string plugin.
    $facet = reset($facets);
    $query_string = $this->urlProcessorPluginManager->createInstance('query_string', ['facet' => $facet]);
    $active_filters = $query_string->getActiveFilters();
    // Determine the filter values for each of the active facets.
    foreach ($facets as $facet) {
      $keyed_facets[$facet->id()] = $facet;
      $active_filters_values[$facet->id()] = $active_filters[$facet->id()] ?? [];
    }

    $active_filters_values = array_filter($active_filters_values);

    // Run through each of the active filter values and prepare their displays.
    foreach ($active_filters_values as $facet_id => $filters) {
      $facet = $keyed_facets[$facet_id];
      $id = $this->multiselectPluginManager->getPluginIdForFacet($facet, $list_source);
      $preset_filter = new ListPresetFilter($facet_id, $filters);
      if (!$id) {
        $display_value = $this->getDefaultFilterValuesLabel($facet, $preset_filter);
      }
      else {
        $config = [
          'facet' => $keyed_facets[$facet_id],
          'list_source' => $list_source,
          'preset_filter' => $preset_filter,
        ];
        /** @var \Drupal\oe_list_pages\MultiselectFilterFieldPluginInterface $plugin */
        $plugin = $this->multiselectPluginManager->createInstance($id, $config);
        $display_value = $plugin->getDefaultValuesLabel();
      }

      $categories[] = $keyed_facets[$facet_id]->label() . ': ' . $display_value;
    }

    return implode(' | ', $categories);
  }

  /**
   * Get the channel copyright value.
   *
   * @return \Drupal\Component\Render\FormattableMarkup
   *   The default copyright value.
   */
  protected function getChannelCopyright(): FormattableMarkup {
    return new FormattableMarkup('© @copyright_name, 1995-@enddate', [
      '@copyright_name' => $this->t('European Union'),
      '@enddate' => date('Y'),
    ]);
  }

}
