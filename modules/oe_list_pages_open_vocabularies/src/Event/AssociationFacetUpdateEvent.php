<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_open_vocabularies\Event;

use Drupal\facets\FacetInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired when a facet is updated from an open vocabularies association.
 */
class AssociationFacetUpdateEvent extends Event {

  /**
   * The name of the event.
   */
  const NAME = 'oe_list_pages_open_vocabularies.associated_facet_update_event';

  /**
   * The created but not yet saved facet.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected $facet;

  /**
   * AssociationFacetUpdateEvent constructor.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The created but not yet saved facet.
   */
  public function __construct(FacetInterface $facet) {
    $this->facet = $facet;
  }

  /**
   * Return the facet object.
   *
   * @return \Drupal\facets\FacetInterface
   *   The facet object instance.
   */
  public function getFacet(): FacetInterface {
    return $this->facet;
  }

  /**
   * Set the updated facet object.
   *
   * @param \Drupal\facets\FacetInterface $facet
   *   The facet object instance.
   *
   * @return $this
   */
  public function setFacet(FacetInterface $facet): AssociationFacetUpdateEvent {
    $this->facet = $facet;
    return $this;
  }

}
