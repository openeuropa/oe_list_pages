<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\oe_list_pages\ListPageRssBuildAlterEvent;
use Drupal\oe_list_pages\ListPageEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the List pages RSS feed routes.
 */
class ListPageRssController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * ListPageRssController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EventDispatcherInterface $event_dispatcher, ThemeManagerInterface $theme_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->eventDispatcher = $event_dispatcher;
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): ListPageRssController {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('theme.manager')
    );
  }

  /**
   * Render the RSS feed of a list page result.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The list page node.
   *
   * @return string
   *   The rendered RSS feed.
   */
  public function build(NodeInterface $node): Response {

    $items = $this->getItemList($node);

    // Get the favicon location.
    $theme = $this->themeManager->getActiveTheme();
    if (file_exists($favicon = $theme->getPath() . '/favicon.ico')) {
      $image_url = file_create_url($favicon);
    }
    else {
      $image_url = file_create_url('core/misc/favicon.ico');
    }

    // Build the render array for the RSS feed.
    $default_title = $node->getTitle() . ' - RSS';
    $default_link = $node->toUrl('canonical', ['absolute' => TRUE])->toString();
    $build = [
      '#theme' => 'oe_list_pages_rss',
      '#title' => $default_title,
      '#link' => $default_link,
      '#description' => '',
      '#langcode' => $node->language()->getId(),
      '#image_url' => $image_url,
      '#image_title' => $default_title,
      '#image_link' => $default_link,
      '#channel_elements' => [],
      '#items' => $items,
    ];

    $cache_metadata = CacheableMetadata::createFromRenderArray($build);
    $cache_metadata->addCacheableDependency($node);
    $cache_metadata->addCacheableDependency($theme);
    $cache_metadata->applyTo($build);

    // Dispatch event to allow modules to alter the build before being rendered.
    $event = new ListPageRssBuildAlterEvent($build, $node);
    $this->eventDispatcher->dispatch(ListPageEvents::ALTER_RSS_BUILD, $event);
    $build = $event->getBuild();

    // Create the response and add the xml type header.
    $response = new Response('', 200);
    $response->headers->set('Content-Type', 'application/rss+xml; charset=utf-8');

    // Render the list and add it to the response.
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $output = (string) $renderer->renderRoot($build);
    if (empty($output)) {
      throw new NotFoundHttpException();
    }
    $response->setContent($output);

    return $response;
  }

  /**
   * Returns a list of items to be rendered.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node containing the list.
   *
   * @return array
   *   Array of items to be rendered.
   */
  protected function getItemList(NodeInterface $node): array {
    return [];
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
    $bundle_entity_storage = $this->entityTypeManager->getStorage('node_type');
    $bundle = $bundle_entity_storage->load($node->bundle());
    $configured_meta_bundles = $bundle->getThirdPartySetting('emr', 'entity_meta_bundles');
    if (!$configured_meta_bundles || !in_array('oe_list_page', $configured_meta_bundles)) {
      return AccessResult::forbidden($this->t('Node type does not have List Page meta configured.'));
    }
    return AccessResult::allowed();
  }

}
