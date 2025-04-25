<?php

namespace Drupal\abselect\Plugin\views\filter;

use Drupal\Core\Database\Connection;
use Drupal\user\Entity\User;
use Drupal\views\Plugin\views\filter\InOperator;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The current active database's master connection.
   */
  protected Connection $connection;

  /**
   * Constructs a new Date handler.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Database\Connection $connection
   *   The current active database's master connection.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, Connection $connection) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!empty($this->valueOptions)) {
      return $this->valueOptions;
    }

    // Get a list of users that have content assigned to them.
    $query = $this->connection->query('SELECT DISTINCT(uid) FROM {node_field_data}');

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
