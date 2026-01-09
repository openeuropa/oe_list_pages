<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_displays;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\emr\Entity\EntityMetaInterface;
use Drupal\oe_link_lists\LinkDisplayPluginManagerInterface;

/**
 * Builds the link display selection on the list pages form.
 */
class ListDisplaySelectionBuilder {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * The link display plugin manager.
   *
   * @var \Drupal\oe_link_lists\LinkDisplayPluginManagerInterface
   */
  protected $linkDisplayPluginManager;

  /**
   * Constructs a ListDisplaySelectionBuilder.
   *
   * @param \Drupal\oe_link_lists\LinkDisplayPluginManagerInterface $linkDisplayPluginManager
   *   The link display plugin manager.
   */
  public function __construct(LinkDisplayPluginManagerInterface $linkDisplayPluginManager) {
    $this->linkDisplayPluginManager = $linkDisplayPluginManager;
  }

  /**
   * Builds the form elements for selecting a link display plugin.
   *
   * @param array $form
   *   The list pages form section.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\emr\Entity\EntityMetaInterface $entity_meta
   *   The list pages entity meta.
   */
  public function form(array &$form, FormStateInterface $form_state, EntityMetaInterface $entity_meta) {
    /** @var \Drupal\oe_list_pages\ListPageWrapper $entity_meta_wrapper */
    $entity_meta_wrapper = $entity_meta->getWrapper();
    $configuration = $entity_meta_wrapper->getConfiguration();

    $plugin_id = self::getSelectedPlugin('display', $form_state);
    if (!$plugin_id) {
      $plugin_id = $configuration['extra']['oe_list_pages_link_list_displays']['display']['plugin'] ?? NULL;
    }

    $parents = $form['#parents'] ?? [];
    $parents = array_merge($parents, [
      'display',
    ]);

    $display_plugin_options = $this->linkDisplayPluginManager->getPluginsAsOptions('dynamic', 'list_pages');

    $form['display'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display options'),
    ];

    $form['display']['plugin'] = [
      '#type' => 'select',
      '#title' => $this->t('Display'),
      '#empty_option' => $this->t('None'),
      '#empty_value' => '',
      '#required' => TRUE,
      '#options' => $display_plugin_options,
      '#ajax' => [
        'callback' => [$this, 'pluginConfigurationAjaxCallback'],
        'wrapper' => $form['#attributes']['id'],
      ],
      '#submit' => [
        [get_class($this), 'selectPlugin'],
      ],
      '#default_value' => $plugin_id,
      '#executes_submit_callback' => TRUE,
      '#plugin_select' => 'display',
      '#limit_validation_errors' => [
        array_merge($parents, ['plugin']),
      ],
      '#access' => !empty($display_plugin_options),
    ];

    // A wrapper that the Ajax callback will replace.
    $form['display']['plugin_configuration_wrapper'] = [
      '#type' => 'container',
      '#weight' => 10,
      '#tree' => TRUE,
    ];

    $triggering_element = $form_state->getTriggeringElement();
    $ajax_plugin_select = $triggering_element && isset($triggering_element['#plugin_select']) && $triggering_element['#plugin_select'] === 'display';
    if ($plugin_id) {
      $existing_config = !$ajax_plugin_select ? ($configuration['extra']['oe_list_pages_link_list_displays']['display']['plugin_configuration'] ?? []) : [];
      /** @var \Drupal\Core\Plugin\PluginFormInterface $plugin */
      $plugin = $this->linkDisplayPluginManager->createInstance($plugin_id, $existing_config);

      $form['display']['plugin_configuration_wrapper'][$plugin_id] = [
        '#process' => [[get_class($this), 'processPluginConfiguration']],
        '#plugin' => $plugin,
      ];
    }
  }

  /**
   * Validates the plugin configuration.
   *
   * @param string $plugin_type
   *   The plugin type.
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validatePluginConfiguration(string $plugin_type, array $element, FormStateInterface $form_state): void {
    $parents = $element['#parents'];

    $plugin_id = $form_state->getValue(array_merge($parents, [
      $plugin_type,
      'plugin',
    ]));

    if ($plugin_id) {
      /** @var \Drupal\Core\Plugin\PluginFormInterface $plugin */
      $plugin = $this->linkDisplayPluginManager->createInstance($plugin_id);

      $plugin_configuration_parents = [
        $plugin_type,
        'plugin_configuration_wrapper',
        $plugin_id,
      ];
      $plugin_configuration_element = NestedArray::getValue($element, $plugin_configuration_parents, $exists);
      if ($exists) {
        $subform_state = SubformState::createForSubform($plugin_configuration_element, $form_state->getCompleteForm(), $form_state);
        $plugin->validateConfigurationForm($plugin_configuration_element, $subform_state);
      }
    }
  }

  /**
   * Extracts plugin configuration values.
   *
   * It instantiates the selected plugin, calls its submit method and returns
   * the configuration values for this plugin type.
   *
   * @param string $plugin_type
   *   The plugin type: link_source or link_display.
   * @param array $element
   *   The single widget form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The configuration for the plugin type.
   */
  public function extractPluginConfiguration(string $plugin_type, array $element, FormStateInterface $form_state): array {
    $configuration = [];

    $parents = $element['#parents'];

    $plugin_id = $form_state->getValue(array_merge($parents, [
      $plugin_type,
      'plugin',
    ]));

    if ($plugin_id) {
      /** @var \Drupal\Core\Plugin\PluginFormInterface $plugin */
      $plugin = $this->linkDisplayPluginManager->createInstance($plugin_id);

      $plugin_configuration_parents = [
        $plugin_type,
        'plugin_configuration_wrapper',
        $plugin_id,
      ];
      $plugin_configuration_element = NestedArray::getValue($element, $plugin_configuration_parents, $exists);
      if ($exists) {
        $subform_state = SubformState::createForSubform($plugin_configuration_element, $form_state->getCompleteForm(), $form_state);
        $plugin->submitConfigurationForm($plugin_configuration_element, $subform_state);
      }

      // Add the link display plugin configuration.
      $configuration['plugin'] = $plugin_id;
      $configuration['plugin_configuration'] = $plugin->getConfiguration();
    }

    return $configuration;
  }

  /**
   * Submit callback for storing the selected plugin ID.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function selectPlugin(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    self::setSelectedPlugin($triggering_element['#plugin_select'], $triggering_element['#value'], $form_state);
    $form_state->setRebuild(TRUE);
  }

  /**
   * For processor to build the plugin configuration form.
   *
   * @param array $element
   *   The element onto which to build the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The full form state.
   *
   * @return array
   *   The processed form.
   */
  public static function processPluginConfiguration(array &$element, FormStateInterface $form_state): array {
    /** @var \Drupal\oe_link_lists\LinkSourceInterface $plugin */
    $plugin = $element['#plugin'];
    $subform_state = SubformState::createForSubform($element, $form_state->getCompleteForm(), $form_state);
    return $plugin->buildConfigurationForm($element, $subform_state);
  }

  /**
   * The Ajax callback for configuring the plugin.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form element.
   */
  public function pluginConfigurationAjaxCallback(array &$form, FormStateInterface $form_state): array {
    $triggering_element = $form_state->getTriggeringElement();
    $parent_slice = -2;
    return NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, $parent_slice));
  }

  /**
   * Stores the selected plugin ID for a plugin type.
   *
   * @param string $type
   *   The plugin type.
   * @param string $value
   *   The plugin ID.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function setSelectedPlugin(string $type, string $value, FormStateInterface $form_state): void {
    NestedArray::setValue($form_state->getStorage(), [
      'oe_list_pages_link_list_displays',
      'plugin_select',
      $type,
    ], $value);
  }

  /**
   * Retrieves the selected plugin for a plugin type.
   *
   * @param string $type
   *   The plugin type.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string|null
   *   The plugin ID if found, NULL otherwise.
   */
  public static function getSelectedPlugin(string $type, FormStateInterface $form_state): ?string {
    return NestedArray::getValue($form_state->getStorage(), [
      'oe_list_pages_link_list_displays',
      'plugin_select',
      $type,
    ]);
  }

}
