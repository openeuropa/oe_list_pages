<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_list_pages_link_list_extra_displays\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_list_pages\ListPageConfiguration;
use Drupal\oe_list_pages_link_list_displays\Event\ListDisplayResolveEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the resolution of the display based on the URL.
 *
 * @group batch1
 */
class ListDisplayResolveSubscriberTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'user',
    'node',
    'extra_field',
    'facets',
    'search_api',
    'oe_link_lists',
    'oe_list_pages',
    'oe_list_pages_link_list_displays',
    'oe_list_pages_link_list_extra_displays',
  ];

  /**
   * Tests the display resolution based on the request.
   */
  public function testDisplayResolve(): void {
    $event_dispatcher = $this->container->get('event_dispatcher');
    $request_stack = $this->container->get('request_stack');

    foreach (static::displayResolveCases() as $case => [$query, $extra, $expected]) {
      $request_stack->push(new Request($query));

      $configuration = new ListPageConfiguration([
        'entity_type' => 'node',
        'bundle' => 'content_type_one',
        'extra' => $extra,
      ]);
      $event = new ListDisplayResolveEvent($configuration, static::getPrimaryDisplay());
      $event_dispatcher->dispatch($event, ListDisplayResolveEvent::NAME);

      $this->assertEquals($expected, $event->getDisplayConfiguration(), sprintf('The display is correctly resolved for the "%s" case.', $case));
      $request_stack->pop();
    }
  }

  /**
   * Returns the test cases for testDisplayResolve().
   *
   * @return array
   *   The test cases, each containing the request query arguments, the list
   *   page extra configuration and the expected display configuration.
   */
  protected static function displayResolveCases(): array {
    $primary = static::getPrimaryDisplay();
    $extra = [
      'oe_list_pages_link_list_displays' => [
        'display' => $primary,
      ],
      'oe_list_pages_link_list_extra_displays' => [
        'primary_title' => [
          'title' => 'list',
          'custom_title' => '',
        ],
        'displays' => [
          [
            'title' => 'grid',
            'custom_title' => '',
            'plugin' => 'test_markup',
            'plugin_configuration' => [],
          ],
          [
            'title' => 'custom',
            'custom_title' => 'My display',
            'plugin' => 'test_configurable_title',
            'plugin_configuration' => ['link' => FALSE],
          ],
        ],
      ],
    ];

    return [
      'no query argument' => [
        [],
        $extra,
        $primary,
      ],
      'primary display delta' => [
        ['display' => '0'],
        $extra,
        $primary,
      ],
      'first extra display' => [
        ['display' => '1'],
        $extra,
        [
          'plugin' => 'test_markup',
          'plugin_configuration' => [],
        ],
      ],
      'second extra display' => [
        ['display' => '2'],
        $extra,
        [
          'plugin' => 'test_configurable_title',
          'plugin_configuration' => ['link' => FALSE],
        ],
      ],
      'out of range delta' => [
        ['display' => '3'],
        $extra,
        $primary,
      ],
      'negative delta' => [
        ['display' => '-1'],
        $extra,
        $primary,
      ],
      'non numeric delta' => [
        ['display' => 'foo'],
        $extra,
        $primary,
      ],
      'array delta' => [
        ['display' => ['1']],
        $extra,
        $primary,
      ],
      'no extra displays' => [
        ['display' => '1'],
        [
          'oe_list_pages_link_list_displays' => [
            'display' => $primary,
          ],
        ],
        $primary,
      ],
    ];
  }

  /**
   * Returns the primary display configuration used in the tests.
   *
   * @return array
   *   The display configuration.
   */
  protected static function getPrimaryDisplay(): array {
    return [
      'plugin' => 'title',
      'plugin_configuration' => [],
    ];
  }

}
