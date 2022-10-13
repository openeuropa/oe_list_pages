<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages_open_vocabularies\Event;

use Drupal\facets\FacetInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Event for updating search api facet configuration.
 */
class SearchApiFacetUpdateEvent extends Event {

  /**
   * The facet.
   *
   * @var \Drupal\facets\FacetInterface
   */
  protected FacetInterface $facet;

  /**
   * SearchApiFacetUpdateEvent constructor.
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
  public function setFacet(FacetInterface $facet): SearchApiFacetUpdateEvent {
    $this->facet = $facet;
    return $this;
  }

}
