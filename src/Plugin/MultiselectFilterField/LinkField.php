<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages\Plugin\MultiselectFilterField;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\oe_list_pages\MultiSelectFilterFieldPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the link field type multiselect filter plugin.
 *
 * @MultiselectFieldFilter(
 *   id = "link",
 *   label = @Translation("Link field"),
 *   field_types = {
 *     "link",
 *   },
 *   weight = 100
 * )
 */
class LinkField extends MultiSelectFilterFieldPluginBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityFieldManagerInterface $entity_field_manager, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValues(): array {
    $default_values = [];
    foreach (parent::getDefaultValues() as $default_value) {
      $default_values[] = $this->getUriAsDisplayableString($default_value);
    }

    return $default_values;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDefaultValueForm(): array {
    $field_definition = $this->getFacetFieldDefinition($this->configuration['facet'], $this->configuration['list_source']);
    if (empty($field_definition)) {
      return [];
    }

    $link_type = $field_definition->getSetting('link_type');
    $form = [
      '#type' => 'url',
      '#element_validate' => [[LinkWidget::class, 'validateUriElement']],
      '#maxlength' => 2048,
      '#link_type' => $link_type,
    ];

    if ($link_type == LinkItemInterface::LINK_INTERNAL) {
      $form['#type'] = 'entity_autocomplete';
      $form['#target_type'] = 'node';
      $form['#process_default_value'] = FALSE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultValuesLabel(): string {
    $filter_value = parent::getDefaultValues();
    $values = [];
    foreach ($filter_value as $value) {
      $values[] = $this->getUriAsDisplayableString($value);
    }

    return implode(', ', $values);
  }

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * @param string $uri
   *   The URI.
   *
   * @return string
   *   The URI.
   *
   * @see LinkWidget::getUriAsDisplayableString()
   */
  protected function getUriAsDisplayableString(string $uri): string {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
      if ($entity_type == 'node' && $entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    elseif ($scheme === 'route') {
      $displayable_string = ltrim($displayable_string, 'route:');
    }

    return $displayable_string;
  }

}
