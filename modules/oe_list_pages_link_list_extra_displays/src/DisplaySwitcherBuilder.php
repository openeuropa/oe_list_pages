<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_extra_displays;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\oe_list_pages\ListPageConfiguration;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds the tabs used to switch between the list page displays.
 */
class DisplaySwitcherBuilder {

  use StringTranslationTrait;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new DisplaySwitcherBuilder.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(RequestStack $requestStack) {
    $this->requestStack = $requestStack;
  }

  /**
   * Builds the display switcher for a list page configuration.
   *
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The list page configuration.
   *
   * @return array
   *   The render array.
   */
  public function build(ListPageConfiguration $configuration): array {
    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['url.path', 'url.query_args']);

    $extra_displays = ExtraDisplaysHelper::getExtraDisplays($configuration->getExtra());
    if (!$extra_displays) {
      $build = [];
      $cache->applyTo($build);
      return $build;
    }

    $request = $this->requestStack->getCurrentRequest();
    $displays = array_merge([$extra_displays['primary_title'] ?? []], array_values($extra_displays['displays']));
    $active_delta = ExtraDisplaysHelper::getActiveDelta($request, count($displays) - 1);

    $query = $request ? $request->query->all() : [];
    unset($query['page'], $query[ExtraDisplaysHelper::QUERY_PARAMETER]);

    $links = [];
    foreach ($displays as $delta => $display) {
      $link_query = $query;
      if ($delta > 0) {
        $link_query[ExtraDisplaysHelper::QUERY_PARAMETER] = $delta;
      }

      $attributes = [];
      if ($delta === $active_delta) {
        $attributes['class'] = ['is-active'];
        $attributes['aria-current'] = 'page';
      }

      $links['display_' . $delta] = [
        'title' => ExtraDisplaysHelper::getTitle($display),
        'url' => Url::fromRoute('<current>', [], ['query' => $link_query]),
        'attributes' => $attributes,
      ];
    }

    $build = [
      '#type' => 'html_tag',
      '#tag' => 'nav',
      '#attributes' => [
        'class' => ['oe-list-pages-display-switcher'],
        'aria-label' => $this->t('Display options'),
      ],
      'links' => [
        '#theme' => 'links__oe_list_pages_display_switcher',
        '#links' => $links,
      ],
    ];
    $cache->applyTo($build);

    return $build;
  }

}
