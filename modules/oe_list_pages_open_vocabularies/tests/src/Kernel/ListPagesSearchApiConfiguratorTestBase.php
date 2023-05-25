<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages_open_vocabularies\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\open_vocabularies\Entity\OpenVocabulary;

/**
 * Base class for open vocabularies configurator.
 */
abstract class ListPagesSearchApiConfiguratorTestBase extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'field',
    'user',
    'datetime',
    'datetime_range',
    'emr',
    'entity_reference_revisions',
    'emr_node',
    'link',
    'node',
    'oe_list_pages',
    'oe_list_pages_filters_test',
    'oe_list_page_content_type',
    'oe_list_pages_open_vocabularies',
    'oe_list_pages_open_vocabularies_test',
    'open_vocabularies',
    'options',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'taxonomy',
    'text',
    'facets',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('search_api_task');
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('facets_facet');
    $this->installEntitySchema('node');
    $this->installEntitySchema('open_vocabulary');
    $this->installEntitySchema('open_vocabulary_association');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('entity_meta');
    $this->installEntitySchema('entity_meta_relation');

    // Set tracking page size so tracking will work properly.
    $this->container->get('config.factory')
      ->getEditable('search_api.settings')
      ->set('tracking_page_size', 100)
      ->save();

    $this->installConfig(['node',
      'open_vocabularies',
      'oe_list_pages',
      'oe_list_page_content_type',
      'oe_list_pages_filters_test',
      'oe_list_pages_open_vocabularies_test',
      'emr',
      'emr_node',
    ]);

    // Create OpenVocabularies vocabularies.
    $values = [
      'id' => 'custom_vocabulary',
      'label' => $this->randomString(),
      'description' => $this->randomString(128),
      'handler' => 'taxonomy',
      'handler_settings' => [
        'target_bundles' => [
          'entity_test' => 'vocabulary_one',
        ],
      ],
    ];

    /** @var \Drupal\open_vocabularies\OpenVocabularyInterface $vocabulary */
    $vocabulary = OpenVocabulary::create($values);
    $vocabulary->save();
  }

}
