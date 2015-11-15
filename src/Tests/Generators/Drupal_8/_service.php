<?php

/**
 * @file
 * Contains \Drupal\foo\Example.
 */

namespace Drupal\foo;

use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Node\NodeStorageInterface;

/**
 * Example description.
 */
class Example {

  /**
   * The entity query factory.
   *
   * @var QueryFactory
   */
  protected $entityQuery;

  /**
   * Node storage.
   *
   * @var NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * Constructs a(n) Example object.
   *
   * @param QueryFactory $entity_query
   *   The entity query factory service.
   *
   * @param EntityManagerInterface $entity_manager
   * The entity manager.
   */
  public function __construct(QueryFactory $entity_query,  EntityManagerInterface $entity_manager) {
    $this->entityQuery = $entity_query;
    $this->nodeStorage = $entity_manager->getStorage('node');
  }

  /**
   * Retrieves the last created node.
   */
  public function getLastNode() {
    $nids = $this->entityQuery->get('node')
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->execute();

    $nid = reset($nids);
    return $nid ? $this->nodeStorage->load($nid) : FALSE;
  }

}
