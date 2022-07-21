<?php

/**
 * @file
 * Views API integration functions.
 */

/**
 * Implements hook_views_data_alter().
 */
function abselect_views_data_alter(&$data) {
  $data['node_field_data']['authored_by_select'] = [
    'title' => t('Authored by (select)'),
    'filter' => [
      'title' => t('Authored by (select)'),
      'help' => t('Author selectable from a select list.'),
      'field' => 'uid',
      'id' => 'authored_by_select',
    ],
  ];
}
