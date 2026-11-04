<?php

declare(strict_types=1);

namespace Drupal\oe_list_pages_link_list_extra_displays;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Helper methods for working with the list page extra displays.
 */
class ExtraDisplaysHelper {

  /**
   * The query parameter used to switch between displays.
   */
  const QUERY_PARAMETER = 'display';

  /**
   * Returns the available display title options.
   *
   * @return array
   *   The title options, keyed by the stored value.
   */
  public static function getTitleOptions(): array {
    return [
      'list' => t('List'),
      'grid' => t('Grid'),
      'map' => t('Map'),
      'custom' => t('Custom'),
    ];
  }

  /**
   * Returns the title to show for a display.
   *
   * @param array $display
   *   The display configuration containing the "title" and "custom_title".
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   *   The title.
   */
  public static function getTitle(array $display) {
    $title = $display['title'] ?? '';
    if ($title === 'custom') {
      return $display['custom_title'] ?? '';
    }

    return static::getTitleOptions()[$title] ?? '';
  }

  /**
   * Returns the extra displays configuration from the list page extra values.
   *
   * @param array $extra
   *   The list page extra configuration.
   *
   * @return array
   *   The extra displays configuration, empty if there are no extra displays.
   */
  public static function getExtraDisplays(array $extra): array {
    $extra_displays = $extra['oe_list_pages_link_list_extra_displays'] ?? [];
    if (empty($extra_displays['displays'])) {
      return [];
    }

    return $extra_displays;
  }

  /**
   * Returns the configuration of the extra display requested in the URL.
   *
   * @param array $extra
   *   The list page extra configuration.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request.
   *
   * @return array|null
   *   The display configuration containing the "plugin" and
   *   "plugin_configuration" keys, or NULL if the main display is requested.
   */
  public static function getActiveExtraDisplay(array $extra, ?Request $request): ?array {
    $extra_displays = static::getExtraDisplays($extra);
    if (!$extra_displays) {
      return NULL;
    }

    $displays = array_values($extra_displays['displays']);
    $delta = static::getActiveDelta($request, count($displays));
    if ($delta === 0) {
      return NULL;
    }

    $display = $displays[$delta - 1];
    return [
      'plugin' => $display['plugin'],
      'plugin_configuration' => $display['plugin_configuration'] ?? [],
    ];
  }

  /**
   * Returns the delta of the display requested in the URL.
   *
   * Delta 0 is the primary display, while the extra displays start from 1.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request.
   * @param int $count
   *   The number of extra displays.
   *
   * @return int
   *   The delta, or 0 if the requested delta is not valid.
   */
  public static function getActiveDelta(?Request $request, int $count): int {
    $delta = static::getRequestedDelta($request);
    return $delta <= $count ? $delta : 0;
  }

  /**
   * Returns the delta of the extra display requested in the URL.
   *
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request.
   *
   * @return int
   *   The delta, or 0 if no extra display is requested.
   */
  public static function getRequestedDelta(?Request $request): int {
    if (!$request) {
      return 0;
    }

    $value = $request->query->all()[static::QUERY_PARAMETER] ?? NULL;
    if (!is_string($value) || !ctype_digit($value)) {
      return 0;
    }

    return (int) $value;
  }

  /**
   * Submit callback for the filters form.
   *
   * Runs after the original submit handler and adds the display to the
   * redirect, including when clearing the filters.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function facetsFormSubmit(array &$form, FormStateInterface $form_state): void {
    $delta = static::getRequestedDelta(\Drupal::request());
    $url = $form_state->getRedirect();
    if (!$delta || !$url instanceof Url) {
      return;
    }

    $query = $url->getOption('query') ?? [];
    $query[static::QUERY_PARAMETER] = $delta;
    $url->setOption('query', $query);
    $form_state->setRedirectUrl($url);
  }

}
