<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_list_pages_open_vocabularies\Traits;

use Drupal\open_vocabularies\Entity\OpenVocabulary;
use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;
use Drupal\open_vocabularies\OpenVocabularyAssociationInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Contains helper methods to create OpenVocabularies and associations in tests.
 */
trait OpenVocabularyTestTrait {

  /**
   * Creates test taxonomy vocabulary and terms.
   *
   * @param array $term_names
   *   The name of the terms to create.
   * @param string $vid
   *   The vocabulary ID.
   *
   * @return array
   *   The created terms.
   */
  public function createTestTaxonomyVocabularyAndTerms(array $term_names, string $vid = 'oe_list_pages_ov_tags'): array {
    // Create a new tags vocabulary.
    $vocabulary = Vocabulary::create([
      'name' => 'Tags',
      'vid' => 'oe_list_pages_ov_tags',
    ]);
    $vocabulary->save();

    // Create new terms.
    $terms = [];
    for ($i = 0; $i < count($term_names); $i++) {
      $values = [
        'name' => $term_names[$i],
        'vid' => $vid,
      ];
      $term = Term::create($values);
      $term->save();
      $terms[] = $term;
    }

    return $terms;
  }

  /**
   * Creates a new OpenVocabulary association for a SKOS vocabulary.
   *
   * @param array $fields
   *   The field ids for the association.
   * @param array $concept_schemes
   *   The target concept schemes.
   * @param array $names
   *   Specific skos vocabulary association settings.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface
   *   The created association.
   */
  public function createSkosVocabularyAssociation(array $fields, array $concept_schemes = [], array $names = []): OpenVocabularyAssociationInterface {
    $names += [
      'vocabulary_id' => 'skos_vocabulary',
      'vocabulary_label' => 'My skos vocabulary',
      'association_id' => 'skos_vocabulary',
      'association_label' => 'Skos association',
    ];

    $vocabulary = [
      'id' => $names['vocabulary_id'],
      'label' => $names['vocabulary_label'],
      'description' => $this->randomString(128),
      'handler' => 'rdf_skos',
      'handler_settings' => [
        'concept_schemes' => $concept_schemes,
      ],
    ];
    $vocabulary = OpenVocabulary::create($vocabulary);
    $vocabulary->save();

    $vocabulary_association_values = [
      'label' => $names['association_label'],
      'name' => $names['association_id'],
      'widget_type' => 'entity_reference_autocomplete',
      'required' => TRUE,
      'help_text' => 'Some text',
      'predicate' => 'http://example.com/#name',
      'cardinality' => 5,
      'vocabulary' => $names['vocabulary_id'],
      'fields' => $fields,
    ];
    $this->container->get('kernel')->rebuildContainer();
    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = OpenVocabularyAssociation::create($vocabulary_association_values);
    $association->save();

    return $association;
  }

  /**
   * Creates a new OpenVocabulary association for a taxonomy vocabulary.
   *
   * @param array $fields
   *   The field ids for the association.
   * @param string $target_bundle
   *   The target taxonomy vocabulary bundle.
   * @param string $suffix
   *   The suffix to be added.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface
   *   The created association.
   */
  public function createTagsVocabularyAssociation(array $fields, string $target_bundle = 'oe_list_pages_ov_tags', string $suffix = ''): OpenVocabularyAssociationInterface {
    // Create new open vocabulary for tags vocabulary.
    $vocabulary = [
      'id' => 'tags_vocabulary' . $suffix,
      'label' => 'My taxonomy vocabulary',
      'description' => $this->randomString(128),
      'handler' => 'taxonomy',
      'handler_settings' => [
        'target_bundles' => [
          $target_bundle => $target_bundle,
        ],
      ],
    ];
    $vocabulary = OpenVocabulary::create($vocabulary);
    $vocabulary->save();

    $vocabulary_association_values = [
      'label' => 'Tags association' . $suffix,
      'name' => 'tags_vocabulary' . $suffix,
      'widget_type' => 'entity_reference_autocomplete',
      'required' => TRUE,
      'help_text' => 'Some text',
      'predicate' => 'http://example.com/#name',
      'cardinality' => 5,
      'vocabulary' => 'tags_vocabulary' . $suffix,
      'fields' => $fields,
    ];

    $this->container->get('kernel')->rebuildContainer();

    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = OpenVocabularyAssociation::create($vocabulary_association_values);
    $association->save();

    return $association;
  }

}
