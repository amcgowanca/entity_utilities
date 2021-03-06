<?php

namespace Drupal\entity_utilities\Storage;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Provides Drupal core's UserStorage class methods.
 *
 * Additionally, Drupal core does not provide support at this time for creating
 * an anonymous user entity object through its storage class. Furthermore, this
 * trait implements the method "getAnonymousUser()" which mimics Drupal core's
 * User::getAnonymousUser() implementation. Developers wishing to utilize this
 * can do so, and furthermore can apply the following patch available here:
 * https://gist.github.com/amcgowanca/28b296dd76622835b8dcc7f546c04f93, to
 * provide support within the User entity class.
 *
 * @see \Drupal\user\UserStorage
 */
trait UserStorageTrait {

  /**
   * {@inheritdoc}
   */
  protected function doSaveFieldItems(ContentEntityInterface $entity, array $names = []) {
    // The anonymous user account is saved with the fixed user ID of 0.
    // Therefore we need to check for NULL explicitly.
    if ($entity->id() === NULL) {
      $entity->uid->value = $this->database->nextId($this->database->query('SELECT MAX(uid) FROM {' . $this->getBaseTable() . '}')->fetchField());
      $entity->enforceIsNew();
    }
    return parent::doSaveFieldItems($entity, $names);
  }

  /**
   * {@inheritdoc}
   */
  protected function isColumnSerial($table_name, $schema_name) {
    // User storage does not use a serial column for the user id.
    return $table_name == $this->revisionTable && $schema_name == $this->revisionKey;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastLoginTimestamp(UserInterface $account) {
    $this->database->update($this->getDataTable())
      ->fields(['login' => $account->getLastLoginTime()])
      ->condition('uid', $account->id())
      ->execute();
    // Ensure that the entity cache is cleared.
    $this->resetCache([$account->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function updateLastAccessTimestamp(AccountInterface $account, $timestamp) {
    $this->database->update($this->getDataTable())
      ->fields([
        'access' => $timestamp,
      ])
      ->condition('uid', $account->id())
      ->execute();
    // Ensure that the entity cache is cleared.
    $this->resetCache([$account->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRoleReferences(array $rids) {
    // Remove the role from all users.
    $this->database->delete('user__roles')
      ->condition('roles_target_id', $rids)
      ->execute();

    $this->resetCache();
  }

  /**
   * {@inheritdoc}
   */
  public function getAnonymousUser() {
    $class = $this->entityClass;
    return new $class([
      'uid' => [LanguageInterface::LANGCODE_DEFAULT => 0],
      'name' => [LanguageInterface::LANGCODE_DEFAULT => ''],
      // Explicitly set the langcode to ensure that field definitions do not
      // need to be fetched to figure out a default.
      'langcode' => [LanguageInterface::LANGCODE_DEFAULT => LanguageInterface::LANGCODE_NOT_SPECIFIED]
    ], $this->entityTypeId);
  }

}
