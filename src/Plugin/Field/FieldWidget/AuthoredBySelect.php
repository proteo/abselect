<?php

namespace Drupal\abselect\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the "Authored by (select)" widget.
 *
 * @FieldWidget(
 *   id = "authored_by_select",
 *   label = @Translation("Authored by (select)"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 *
 * @Constraint(
 *   id = "EntityHasField",
 *   label = @Translation("Entity has field", context = "Validation"),
 *   type = { "entity" },
 * )
 */
class AuthoredBySelect extends OptionsSelectWidget {

  /**
   * Used to indicate whether the form element must be set as disabled.
   *
   * @var bool
   */
  private $disabled = FALSE;

  /**
   * Stores the UID of the user selected as author.
   *
   * @var int
   */
  private $assignedAuthor;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The module handler service.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Current user.
   */
  protected AccountProxyInterface $user;

  /**
   * Constructs a RevisionLogWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   *   The current user.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleHandlerInterface $module_handler,
    AccountProxyInterface $user,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $roles = self::getUserRoles(TRUE);
    return [
      'delegation_roles' => $roles,
      'authoring_roles' => $roles,
      'disabled_visibility' => 'readonly',
    ] + parent::defaultSettings();
  }

  /**
   * Prepares an array with valid user roles.
   *
   * @param bool $keys_only
   *   Indicate if the returned array must contain roles as machine names.
   *
   * @return array
   *   Array with user roles.
   */
  public static function getUserRoles(bool $keys_only = FALSE) {
    $roles = array_filter(Role::loadMultiple(), fn($role) => !in_array($role->id(), [
      // Exclude anonymous and authenticated system roles.
      RoleInterface::ANONYMOUS_ID,
      RoleInterface::AUTHENTICATED_ID,
    ]));

    return $keys_only ? array_keys($roles) : array_map(fn($item) => $item->label(), $roles);
  }

  /**
   * Build display options if the field is disabled.
   *
   * @return array
   *   Available disabled field display options.
   */
  public function displayOptions() {
    return [
      'visible' => $this->t('Visible'),
      'readonly' => $this->t('Read only'),
      'hidden' => $this->t('Hidden'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $roles = self::getUserRoles();

    $element['delegation_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles with delegation permission'),
      '#description' => $this->t('Users with these roles will be able to chose a user other than themselves as author. Users without these roles will see their own user name as only option available.'),
      '#options' => $roles,
      '#default_value' => $this->getSetting('delegation_roles'),
      '#required' => TRUE,
    ];
    $element['authoring_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Roles with authoring permission'),
      '#description' => $this->t('Only users with these roles will be elegible as authors and thus available in the list.'),
      '#options' => $roles,
      '#default_value' => $this->getSetting('authoring_roles'),
      '#required' => TRUE,
    ];
    $element['disabled_visibility'] = [
      '#type' => 'select',
      '#title' => $this->t('Disabled field visibility'),
      '#description' => $this->t('How to display the field if the current user will not be able to select an author.'),
      '#options' => $this->displayOptions(),
      '#default_value' => $this->getSetting('disabled_visibility'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $all_roles = self::getUserRoles();

    // Roles with access.
    $delegation_roles = array_map(function ($role) use ($all_roles) {
      return $all_roles[$role] ?? NULL;
    }, array_filter($this->getSetting('delegation_roles')));
    $summary[] = 'Delegation roles: ' . implode(', ', array_filter($delegation_roles));

    // Include users from roles.
    $authoring_roles = array_map(function ($role) use ($all_roles) {
      return $all_roles[$role] ?? NULL;
    }, array_filter($this->getSetting('authoring_roles')));
    $summary[] = 'Authoring roles: ' . implode(', ', array_filter($authoring_roles));

    // Disabled field visibility.
    $options = $this->displayOptions();
    $summary[] = 'Disabled field visibility: ' . $options[$this->getSetting('disabled_visibility')];

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);

    // Save the assigned author if we've set one.
    if (isset($this->assignedAuthor)) {
      $element['#assignedAuthor'] = $this->assignedAuthor;
    }

    $disabled_visibility = $this->getSetting('disabled_visibility');

    // If the current user will not be able to select an author, alter the
    // element accordingly.
    if ($this->disabled) {
      switch ($disabled_visibility) {
        case 'readonly':
          $element['#attributes'] = ['readonly' => TRUE];
          break;

        case 'hidden':
          $element['#type'] = 'value';
          break;
      }
    }

    // If we're displaying the node form and the widget will be visible, add the
    // Javascript behavior to update the author name in the meta info area.
    if ($items->getEntity()->getEntityTypeId() == 'node' && (!$this->disabled || $disabled_visibility == 'visible')) {
      $element['#attributes']['class'] = ['authored-by-select'];
      $element['#attached']['library'][] = 'abselect/authored_by_select';
    }

    return ['target_id' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    $uid = $form_state->getValue(['uid', 0, 'target_id']);
    if (is_array($uid)) {
      $uid = reset($uid);
    }
    // If the no value was provided, this means that no user was available and
    // we must assign the anonymous user as author.
    $value = ($uid === FALSE) ? User::getAnonymousUser()->id() : $uid;
    $form_state->setValueForElement($element, $value);
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions(FieldableEntityInterface $entity) {
    if (!isset($this->options)) {
      $options = [];

      $active_uid = $this->user->id();
      $anonymous_user = User::getAnonymousUser();

      // Current user roles.
      $user_roles = $this->user->getRoles();

      // User roles with permission to assign other users as authors.
      $delegation_roles = array_filter($this->getSetting('delegation_roles'));

      // User roles allowed to be authors.
      $authoring_roles = array_filter($this->getSetting('authoring_roles'));

      // Value currently assigned to the field. When creating an entity, it will
      // be the current user ID. When editing, the saved value.
      $current_author = $this->getCurrentFieldValue($entity);

      // Define the current operation.
      $op = $entity->isNew() ? 'create' : 'edit';

      // If we're in edit mode always honor the currently saved value adding the
      // assigned author as first option.
      if ($op == 'edit') {
        $author = ($active_uid == $current_author) ? $this->user : User::load($current_author);
        $options[$author->id()] = $author->getDisplayName();
      }
      // If we're in creation mode and the active user can be author, add it.
      elseif (array_intersect($user_roles, $authoring_roles)) {
        $options[$active_uid] = $this->user->getDisplayName();
      }

      // Add users with roles allowed to be authors to the list.
      if (array_intersect($user_roles, $delegation_roles)) {
        $query = $this->entityTypeManager
          ->getStorage('user')
          ->getQuery('AND');
        $rids = array_keys($authoring_roles);
        $uids = $query
          ->accessCheck(FALSE)
          ->condition('roles', $rids, 'IN')
          ->sort('name')
          ->execute();
        if (!empty($uids)) {
          foreach (User::loadMultiple($uids) as $user) {
            $options[$user->id()] = $user->getDisplayName();
          }
        }
      }
      else {
        // If the user will not be allowed to modify the current value, activate
        // the flag to hide the field.
        $this->disabled = TRUE;
      }

      // If no users were available as authors, the only option available will
      // be the anonymous user.
      if (empty($options)) {
        $options[$anonymous_user->id()] = $anonymous_user->getDisplayName();
      }

      // If the resulting select will have only one option, it may not always
      // match the current user. In such cases, we'll save a custom property
      // "assignedAuthor" in the element array so we can synchronize the name
      // of the author in the meta information area.
      if (count($options) == 1 || !isset($options[$active_uid])) {
        $this->assignedAuthor = array_key_first($options);
      }

      $context = [
        'fieldDefinition' => $this->fieldDefinition,
        'entity' => $entity,
      ];

      $this->moduleHandler->alter('options_list', $options, $context);

      array_walk_recursive($options, [$this, 'sanitizeLabel']);

      // Options might be nested ("optgroups"). If the widget does not support
      // nested options, flatten the list.
      if (!$this->supportsGroups()) {
        $options = OptGroup::flattenOptions($options);
      }

      $this->options = $options;
    }

    return $this->options;
  }

  /**
   * Gets the value assigned to the field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Current entity.
   *
   * @return mixed
   *   Value assigned to the field.
   */
  protected function getCurrentFieldValue(FieldableEntityInterface $entity) {
    $name = $this->fieldDefinition->getName();
    $items = $entity->get($name);
    $value = $items[0]->{$this->column};
    if (is_array($value)) {
      $value = reset($value);
    }
    return $value;
  }

}
