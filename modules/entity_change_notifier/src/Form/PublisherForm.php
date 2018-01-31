<?php

namespace Drupal\entity_change_notifier\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Drupal\entity_change_notifier\Entity\PublisherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PublisherForm.
 */
class PublisherForm extends EntityForm {

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a PublisherForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   */
  public function __construct(EntityTypeManager $entity_type_manager,
                              EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_change_notifier\Entity\PublisherInterface $publisher */
    $publisher = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $publisher->label(),
      '#description' => $this->t("Label for the Publisher."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $publisher->id(),
      '#machine_name' => [
        'exists' => '\Drupal\entity_change_notifier\Entity\Publisher::load',
      ],
      '#disabled' => !$publisher->isNew(),
    ];

    $this->destinationElement($form, $publisher);

    $this->publishTypesElement($form, $publisher);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $form_state->cleanValues();

    /** @var \Drupal\entity_change_notifier\Entity\PublisherInterface $publisher */
    $publisher = $this->entity;

    // First, merge the values from the additional entity types with the entity
    // types that have bundles. This gives us entity and bundle types sorted by
    // their group, but additional entities still have string values while
    // bundles have array values.
    $publishTypes = [];
    foreach (['content', 'configuration', 'other'] as $type) {
      $publishTypes[$type] = [];
      $values = $form_state->getValue($type);
      foreach (['publish_types', 'no_bundles'] as $key) {
        if (isset($values[$key])) {
          $publishTypes[$type] = array_merge((array) $publishTypes[$type], $values[$key]);
        }
      }
    }

    // Now, we need to make the additional entities values be arrays too.
    foreach ($publishTypes as $type => &$publishType) {
      foreach ($publishType as &$entityType) {
        // The entity type contains bundles.
        if (is_array($entityType)) {
          // Filter out unselected bundles. Then, remove the keys from any
          // remaining bundles since we store them as a sequence.
          $entityType = array_values(array_filter($entityType));
        }
        else {
          // Only convert it if $entityType is 1 (meaning checked).
          if ($entityType) {
            // If it's selected, we need to make it an array for it's internal
            // bundle type (which will equal the entity type.
            $publishType[$entityType] = [$entityType];
          }
        }
      }

      // Remove any unselected publish types. This handles both additional
      // entities set to 0 and empty arrays from entities with bundles.
      $publishTypes[$type] = array_filter($publishTypes[$type]);
    }

    // Finally, remove our grouping and implode them into one configuration
    // array, since the grouping is only for the UI and not functionality.
    $publishTypes = array_merge([], (array) $publishTypes['content'], (array) $publishTypes['configuration'], (array) $publishTypes['other']);
    $publisher->setPublishTypes($publishTypes);
    $status = $publisher->save();

    switch ($status) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Publisher.', [
          '%label' => $publisher->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Publisher.', [
          '%label' => $publisher->label(),
        ]));
    }
    $form_state->setRedirectUrl($publisher->toUrl('collection'));
  }

  /**
   * Builds a list containing all bundles for a given entity type.
   */
  private function buildEntityList($entity_type_id) {
    $entityList = [];
    $bundleInfo = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    foreach ($bundleInfo as $bundle_name => $bundle) {
      $entityList[$bundle_name] = $bundle['label'];
    }
    return $entityList;

  }

  /**
   * Create the "destination" dropdown.
   *
   * @param array $form
   *   The form being built.
   * @param \Drupal\entity_change_notifier\Entity\PublisherInterface $publisher
   *   The publisher being added or edited.
   */
  private function destinationElement(array &$form, PublisherInterface $publisher) {
    $destinationEntities = $this->entityTypeManager->getStorage('ecn_destination')
      ->loadMultiple();

    if (empty($destinationEntities)) {
      \drupal_set_message($this->t('No destinations exist to publish entities to. <a href="@add-destination">Add a destination</a> before adding a publisher.', [
        '@add-destination' => Url::fromRoute('entity.ecn_destination.add_form')->toString(),
      ]), 'warning');
    }

    $options = [];
    foreach ($destinationEntities as $destination) {
      $options[$destination->id()] = $destination->label();
    }

    // Use the set destination or the first if we are adding a new Publisher.
    if (!empty($publisher->getDestination())) {
      $destination_id = $publisher->getDestination();
    }
    else {
      $keys = array_keys($options);
      $destination_id = reset($keys);
    }

    $form['destination'] = [
      '#type' => 'select',
      '#title' => $this->t('Destination'),
      '#description' => $this->t('The destination to send notifications to.'),
      '#default_value' => $destination_id,
      '#options' => $options,
      '#required' => TRUE,
    ];
  }

  /**
   * Add the publish types fields to the form.
   *
   * Entities typically are classified as either Content Entities or
   * Configuration Entities. However, nothing prevents an entity from being it's
   * own type. To make the form easier to use, we split the list of all entities
   * and bundles into three sections:
   *
   *   - Content entities
   *   - Configuration entities
   *   - Other
   *
   * Other will usually be empty and not shown at all.
   *
   * Within each group, we have to handle two cases:
   *
   *   - Entities with bundles (nodes, taxonomies, etc).
   *   - Entities with no bundles (though they have a bundle ID equalling their
   *     entity ID).
   *
   * Since showing bundles for bundle-less entities would be very noisy on the
   * form, we group them together. With each section, we end up with:
   *
   *   - A list of checkboxes of entities with bundles, sorted alphabetically
   *     by the entity label.
   *   - A single checkboxes element for all bundle-less entities.
   *
   * @param array $form
   *   The form being built.
   * @param \Drupal\entity_change_notifier\Entity\PublisherInterface $publisher
   *   The publisher being edited.
   */
  private function publishTypesElement(array &$form, PublisherInterface $publisher) {
    $definitions = $this->getSortedEntityDefinitions();

    // Initialize each details element so that form values are put into arrays.
    $form['content'] = [
      '#type' => 'details',
      '#title' => $this->t('Content entity types'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('Configuration entity types'),
      '#tree' => TRUE,
    ];
    $form['other'] = [
      '#type' => 'details',
      '#title' => $this->t('Other entity types'),
      '#tree' => TRUE,
    ];

    // Loop through the three groups of entity types.
    foreach ($definitions as $type => &$group) {
      // Sort each entity type by label.
      uasort($group, function (EntityTypeInterface $a, EntityTypeInterface $b) {
        return (string) $a->getLabel() > (string) $b->getLabel();
      });

      // Store all entity types that don't have bundles.
      $additionalEntityTypes = [];
      /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
      foreach ($group as $definition) {
        // If the entity definition has bundles, then we put all of it's bundles
        // into one checkbox list.
        if ($definition->getBundleEntityType()) {
          // Fetch the array of publish types that may already be set for this
          // entity type when editing a publisher.
          $default_value = [];
          if (isset($publisher->getPublishTypes()[$definition->id()])) {
            $default_value = $publisher->getPublishTypes()[$definition->id()];
          }
          $form[$type]['publish_types'][$definition->id()] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('@entity types', ['@entity' => $definition->getLabel()]),
            '#default_value' => $default_value,
            '#options' => $this->buildEntityList($definition->id()),
          ];
        }
        else {
          $additionalEntityTypes[$definition->id()] = $definition->getLabel();
        }
      }

      // If we have any bundle-less entities, put them at the end.
      if (!empty($additionalEntityTypes)) {
        $default_value = array_keys(array_intersect_key($additionalEntityTypes, $publisher->getPublishTypes()));
        $form[$type]['no_bundles'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Additional entity types'),
          '#options' => $additionalEntityTypes,
          '#default_value' => $default_value,
        ];
      }
    }

    // Sites will always have content and config entities, but "other" is just
    // our fallback. Most sites won't have entities that are outside of those
    // two groups.
    if (empty(Element::children($form['other']))) {
      unset($form['other']);
    }
  }

  /**
   * Return all entity definitions sorted by their group.
   *
   *  First, sort all entity definitions into 'content', 'configuration', and
   *  'other'. The group is a free-form string and can be overridden in an
   *  entity annotation, so we don't know all of the options. For example,
   *  \Drupal\Core\Entity\Annotation\ConfigEntityType::$group doesn't use a
   *  a class constant.
   *
   * @return array
   *   An array of entity definitions sorted into:
   *     - 'content'
   *     - 'configuration'
   *     - 'other'
   */
  private function getSortedEntityDefinitions() {
    $definitions = [];
    $definitions['content'] = array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $v) {
      return $v->getGroup() == 'content';
    });
    $definitions['configuration'] = array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $v) {
      return $v->getGroup() == 'configuration';
    });
    $definitions['other'] = array_filter($this->entityTypeManager->getDefinitions(), function (EntityTypeInterface $v) {
      return $v->getGroup() != 'content' && $v->getGroup() != 'configuration';
    });
    return $definitions;
  }

}
