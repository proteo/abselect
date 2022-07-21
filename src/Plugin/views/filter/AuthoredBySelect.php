<?php

namespace Drupal\abselect\Plugin\views\filter;

use Drupal\user\Entity\User;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Author selectable from a select list.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("authored_by_select")
 */
class AuthoredBySelect extends InOperator {

  /**
   * Form element type.
   *
   * @var string
   */
  protected $valueFormType = 'select';

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!empty($this->valueOptions)) {
      return $this->valueOptions;
    }

    // Get a list of users that have content assigned to them.
    $query = \Drupal::database()->query('SELECT DISTINCT(uid) FROM {node_field_data}');

    $this->valueOptions = [];

    foreach ($query as $row) {
      $user = ($row->uid == 0) ? User::getAnonymousUser() : User::load($row->uid);
      $this->valueOptions[$row->uid] = $user->getDisplayName();
    }

    // Order array by user name. We don't do this in the query so the array can
    // be ordered after having the actual user's display name.
    asort($this->valueOptions);

    return $this->valueOptions;
  }

}
