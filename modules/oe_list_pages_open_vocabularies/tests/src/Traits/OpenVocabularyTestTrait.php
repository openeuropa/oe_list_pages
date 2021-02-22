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
   * @return array
   *   The created terms.
   */
  public function createTestTaxonomyVocabularyAndTerms(): array {
    // Create a new tags vocabulary.
    $vocabulary = Vocabulary::create([
      'name' => 'Tags',
      'vid' => 'oe_list_pages_ov_tags',
    ]);
    $vocabulary->save();

    $tags = [
      'yellow color',
      'green color',
    ];

    // Create new terms.
    for ($i = 0; $i < count($tags); $i++) {
      $values = [
        'name' => $tags[$i],
        'vid' => 'oe_list_pages_ov_tags',
      ];
      $term = Term::create($values);
      $term->save();
      $terms[] = $term;
    }

    return $terms;
  }

  /**
   * Creates a new OpenVocabulary assocation for a skos vocabulary.
   *
   * @param array $fields
   *   The field ids for the association.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface
   *   The created association.
   */
  public function createSkosVocabularyAssociation(array $fields): OpenVocabularyAssociationInterface {
    $vocabulary = [
      'id' => 'skos_vocabulary',
      'label' => 'My skos vocabulary',
      'description' => $this->randomString(128),
      'handler' => 'rdf_skos',
      'handler_settings' => [
        'concept_schemes' => ['http://publications.europa.eu/resource/authority/country'],
      ],
    ];
    $vocabulary = OpenVocabulary::create($vocabulary);
    $vocabulary->save();

    $vocabulary_association_values = [
      'label' => 'Skos association',
      'name' => 'skos_vocabulary',
      'widget_type' => 'entity_reference_autocomplete',
      'required' => TRUE,
      'help_text' => 'Some text',
      'predicate' => 'http://example.com/#name',
      'cardinality' => 5,
      'vocabulary' => 'skos_vocabulary',
      'fields' => $fields,
    ];
    $this->container->get('kernel')->rebuildContainer();
    /** @var \Drupal\open_vocabularies\OpenVocabularyAssociationInterface $association */
    $association = OpenVocabularyAssociation::create($vocabulary_association_values);
    $association->save();
    $vocabulary_associations[] = $association;
    return $association;
  }

  /**
   * Creates a new OpenVocabulary assocation for a taxonomy vocabulary.
   *
   * @param array $fields
   *   The field ids for the association.
   * @param string $suffix
   *   The suffix to be added.
   *
   * @return \Drupal\open_vocabularies\OpenVocabularyAssociationInterface
   *   The created association.
   */
  public function createTagsVocabularyAssociation(array $fields, string $suffix = ''): OpenVocabularyAssociationInterface {
    // Create new open vocabulary for tags vocabulary.
    $vocabulary = [
      'id' => 'tags_vocabulary' . $suffix,
      'label' => 'My taxonomy vocabulary',
      'description' => $this->randomString(128),
      'handler' => 'taxonomy',
      'handler_settings' => [
        'target_bundles' => [
          'oe_list_pages_ov_tags' => 'oe_list_pages_ov_tags',
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
    $vocabulary_associations[] = $association;
    return $association;
  }

}
