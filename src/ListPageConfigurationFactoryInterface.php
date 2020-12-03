<?php

declare(strict_types = 1);

namespace Drupal\oe_list_pages;

use Drupal\oe_list_pages\Form\ListPageConfigurationSubForm;

/**
 * Defines factories for the list page configuration subform.
 */
interface ListPageConfigurationFactoryInterface {

  /**
   * Returns an instance of the list page configuration subform.
   *
   * @param \Drupal\oe_list_pages\ListPageConfiguration $configuration
   *   The default configuration.
   *
   * @return \Drupal\oe_list_pages\Form\ListPageConfigurationSubForm
   *   The subform.
   */
  public function getForm(ListPageConfiguration $configuration): ListPageConfigurationSubForm;

}
