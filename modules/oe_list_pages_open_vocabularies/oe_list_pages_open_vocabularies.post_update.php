<?php

/**
 * @file
 * Post update functions for OE list page open vocabularies.
 */

declare(strict_types = 1);

use Drupal\open_vocabularies\Entity\OpenVocabularyAssociation;

/**
 * Updates all the open vocabularies facets with translations.
 *
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 */
function oe_list_pages_open_vocabularies_post_update_0001(&$sandbox) {
  // Facets may have been created based on vocabulary associations that are
  // translated. This update goes through all the facets created as such
  // and adds translations as needed.
  if (!isset($sandbox['total'])) {
    // Get all the association entities.
    $ids = \Drupal::entityTypeManager()->getStorage('open_vocabulary_association')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return t('No open vocabulary association facets had to be updated.');
    }

    $sandbox['ids'] = array_unique($ids);
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['current'] = 0;
    $sandbox['updated'] = 0;
    $default_language = \Drupal::languageManager()->getDefaultLanguage()->getId();
    $languages = \Drupal::languageManager()->getLanguages();
    $sandbox['languages'] = [];
    foreach ($languages as $language) {
      if ($language->getId() !== $default_language) {
        $sandbox['languages'][] = $language->getId();
      }
    }
  }

  // We process one association at the time.
  $id = array_pop($sandbox['ids']);
  $association = OpenVocabularyAssociation::load($id);
  $updated = FALSE;
  foreach ($sandbox['languages'] as $language) {
    /** @var \Drupal\language\Config\LanguageConfigOverride $config_translation */
    $config_translation = \Drupal::languageManager()->getLanguageConfigOverride($language, 'open_vocabularies.open_vocabulary_association.' . $association->id());
    if ($config_translation->isNew()) {
      // If it's new, it means there is no translation of this association in
      // this language.
      continue;
    }

    // If we have a translation, it means we need to simply resave it to trigger
    // the update.
    $config_translation->save();
    $updated = TRUE;
  }

  $sandbox['current']++;
  if ($updated) {
    $sandbox['updated']++;
  }

  $sandbox['#finished'] = empty($sandbox['total']) ? 1 : ($sandbox['current'] / $sandbox['total']);

  if ($sandbox['#finished'] === 1) {
    return t('A total of @updated association facets have been updated with translations.', ['@updated' => $sandbox['updated']]);
  }
}
