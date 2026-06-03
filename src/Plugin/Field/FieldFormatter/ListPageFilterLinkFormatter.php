<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Drupal\oe_list_pages\ParentBundleListPageLookup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders each referenced entity as a link to the parent bundle's list page,
 * pre-filtered with the oe_list_pages "f[]" query syntax.
 *
 * The target list page is, by default, the published "oe_list_page" node whose
 * source meta is "<entity_type>:<bundle>" of the parent entity. This can be
 * overridden with an explicit path via the "list_page_url" setting (useful
 * when the listing is not an oe_list_page node — for example a dedicated
 * Drupal route or a page rendering a list page block).
 *
 * The filter ID defaults to the field name minus the "field_" prefix, but can
 * be overridden in the formatter settings.
 *
 * When the user is already on the target list page, query parameters are
 * preserved (other facets, sort, …); pagination is reset and any value of the
 * same filter is replaced. Otherwise a fresh URL is generated.
 *
 * @FieldFormatter(
 *   id = "oe_list_pages_filter_link",
 *   label = @Translation("List page filter link"),
 *   description = @Translation("Link each referenced entity to the parent bundle's list page, pre-filtered by the referenced entity ID."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ListPageFilterLinkFormatter extends EntityReferenceLabelFormatter implements ContainerFactoryPluginInterface {

  /**
   * The list page lookup service.
   */
  protected ParentBundleListPageLookup $listPageLookup;

  /**
   * The current request stack.
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, ParentBundleListPageLookup $list_page_lookup, RequestStack $request_stack) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->listPageLookup = $list_page_lookup;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('oe_list_pages.parent_bundle_list_page_lookup'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'filter_id' => '',
      'link_anywhere' => TRUE,
      'list_page_url' => '',
      'link_classes' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    // The parent "link to entity" toggle is irrelevant: the link target is
    // always the parent bundle's list page.
    $form = parent::settingsForm($form, $form_state);
    unset($form['link']);
    $form['filter_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filter ID'),
      '#default_value' => $this->getSetting('filter_id'),
      '#description' => $this->t('Machine name of the oe_list_pages filter to apply. Leave empty to derive it from the field name (drops the leading "field_").'),
    ];
    $form['link_anywhere'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link anywhere'),
      '#default_value' => $this->getSetting('link_anywhere'),
      '#description' => $this->t('When unchecked, the value is rendered as a link only if the user is already on the target list page; elsewhere only the label is rendered.'),
    ];
    $form['list_page_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('List page URL'),
      '#default_value' => $this->getSetting('list_page_url'),
      '#description' => $this->t('Optional. Internal path of the listing to link to (must start with "/"). Leave empty to auto-detect the published oe_list_page node whose source matches the parent bundle.'),
    ];
    $form['link_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link CSS classes'),
      '#default_value' => $this->getSetting('link_classes'),
      '#description' => $this->t('Optional. Space-separated CSS classes added to each generated link, for example to render it as a badge.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [
      $this->t('Filter ID: @id', ['@id' => $this->resolveFilterId()]),
      $this->getSetting('link_anywhere')
        ? $this->t('Linked from any page')
        : $this->t('Linked only on the list page'),
    ];
    if (($override = trim((string) $this->getSetting('list_page_url'))) !== '') {
      $summary[] = $this->t('Target URL: @url', ['@url' => $override]);
    }
    if (($classes = trim((string) $this->getSetting('link_classes'))) !== '') {
      $summary[] = $this->t('Link classes: @classes', ['@classes' => $classes]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $entity = $items->getEntity();
    $target_path = $this->resolveTargetPath($entity->bundle(), $entity->getEntityTypeId());
    $filter_id = $this->resolveFilterId();
    $on_list_page = $this->isCurrentRequestOn($target_path);
    $should_link = $target_path !== NULL && ($this->getSetting('link_anywhere') || $on_list_page);
    $base_query = $should_link ? $this->buildBaseQuery($on_list_page, $filter_id) : NULL;
    $link_classes = preg_split('/\s+/', trim((string) $this->getSetting('link_classes')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    foreach ($this->getEntitiesToView($items, $langcode) as $delta => $referenced) {
      $label = $referenced->label();

      if ($should_link && !$referenced->isNew()) {
        $query = $base_query;
        $query['f'][] = $filter_id . ':' . $referenced->id();
        $options = ['query' => $query];
        if ($link_classes) {
          $options['attributes']['class'] = $link_classes;
        }
        $elements[$delta] = [
          '#type' => 'link',
          '#title' => $label,
          '#url' => Url::fromUserInput($target_path, $options),
        ];
      }
      else {
        $elements[$delta] = ['#plain_text' => $label];
      }

      $elements[$delta]['#cache']['tags'] = $referenced->getCacheTags();
    }

    // Invalidate when list-page nodes are added/removed/updated. (No-op when
    // the path is overridden, but cheap enough to leave as-is.)
    $elements['#cache']['tags'][] = 'node_list:oe_list_page';
    // The link target depends on the current request path and query string.
    $elements['#cache']['contexts'][] = 'url';

    return $elements;
  }

  /**
   * Resolves the absolute path of the listing this formatter links to.
   *
   * Honors the "list_page_url" override; falls back to the auto-detected
   * oe_list_page node for the parent bundle.
   */
  protected function resolveTargetPath(string $bundle, string $entity_type_id): ?string {
    $override = trim((string) $this->getSetting('list_page_url'));
    if ($override !== '') {
      return str_starts_with($override, '/') ? $override : NULL;
    }
    $list_page = $this->listPageLookup->find($bundle, $entity_type_id);
    if (!$list_page) {
      return NULL;
    }
    return parse_url($list_page->toUrl()->toString(), PHP_URL_PATH);
  }

  /**
   * Whether the current request is on the given target path.
   */
  protected function isCurrentRequestOn(?string $target_path): bool {
    if ($target_path === NULL) {
      return FALSE;
    }
    $request = $this->requestStack->getCurrentRequest();
    return $request && $request->getPathInfo() === $target_path;
  }

  /**
   * Builds the base query string preserved into each generated link.
   *
   * On the target list page: keep current params, drop "page", drop existing
   * values for the same filter ID. Anywhere else: start fresh.
   */
  protected function buildBaseQuery(bool $on_list_page, string $filter_id): array {
    if (!$on_list_page) {
      return ['f' => []];
    }
    $request = $this->requestStack->getCurrentRequest();
    $query = $request->query->all();
    unset($query['page']);
    $facets = (array) ($query['f'] ?? []);
    $prefix = $filter_id . ':';
    $facets = array_values(array_filter(
      $facets,
      static fn ($value) => !str_starts_with((string) $value, $prefix),
    ));
    $query['f'] = $facets;
    return $query;
  }

  /**
   * Returns the configured filter ID, falling back to the field name.
   */
  protected function resolveFilterId(): string {
    $configured = (string) $this->getSetting('filter_id');
    if ($configured !== '') {
      return $configured;
    }
    return preg_replace('/^field_/', '', $this->fieldDefinition->getName());
  }

}
