<?php

namespace Drupal\deconfig;

use Drupal\Core\Config\StorageInterface;
use Drupal\deconfig\Exception\FoundHiddenConfigurationError;

/**
 * Storage removes certain configuration items from configuration.
 */
class DeconfigStorage implements StorageInterface {
  const KEY = '_deconfig';

  /**
   * The underlying storage.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $storage;

  /**
   * The active configuration.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $activeStorage;

  /**
   * Deleted data.
   *
   * @var array
   */
  protected $deleted = [];

  /**
   * Construct new DeconfigStorage.
   *
   * @param \Drupal\Core\Config\StorageInterface $wrappedStorage
   *   The configuration storage to wrap.
   * @param \Drupal\Core\Config\StorageInterface $activeStorage
   *   The active configuration storage.
   */
  public function __construct(StorageInterface $wrappedStorage, StorageInterface $activeStorage) {
    $this->storage = $wrappedStorage;
    $this->activeStorage = $activeStorage;
  }

  /**
   * Hide configuration elements.
   *
   * @param mixed $hideSpec
   *   The _deconfig spec for what to hide.
   * @param mixed $data
   *   Configuration data.
   * @param mixed $storageData
   *   Configuration data from storage.
   * @param bool $lax
   *   Whether we're in 'lax' mode due to @ on key.
   *
   * @return mixed
   *   $data with hided elements deleted.
   */
  protected function doHide($hideSpec, $data, $storageData, $lax) {
    if (is_array($hideSpec)) {
      foreach ($hideSpec as $key => $spec) {
        list($realKey, $keyLax) = $this->hideKey($key);
        $lax = $lax || $keyLax;

        if (isset($data[$realKey])) {
          if (is_array($data[$realKey])) {
            // Handle recursive hiding.
            $data[$key] = $this->doHide($hideSpec[$key], $data[$realKey], $storageData[$realKey], $lax);
            // Delete key if it's become empty.
            if (empty($data[$realKey])) {
              unset($data[$realKey]);
            }
          }
          else {
            // $data[$realKey] is not an array, thus there cannot be any sub
            // keys we need to hide, only delete it if it's the item we want to
            // hide or else we'd delete random parents.
            if (!is_array($spec)) {
              if ($lax) {
                $data[$realKey] = $storageData[$realKey];
              }
              else {
                unset($data[$realKey]);
              }
            }
          }
        }
      }
    }
    else {
      return [];
    }

    return $data;
  }

  /**
   * Dehide configuration elements.
   *
   * @param mixed $hideSpec
   *   The _deconfig spec for what to hide.
   * @param mixed $data
   *   Configuration data.
   * @param mixed $activeData
   *   Configuration data from active storage.
   * @param bool $throw
   *   Whether to throw an error if hidden configuration exists in config.
   * @param bool $lax
   *   Whether we're in 'lax' mode due to @ on key.
   *
   * @return mixed
   *   $data with hided elements loaded from active configuration.
   */
  protected function doUnhide($hideSpec, $data, $activeData, $throw = FALSE, $lax = FALSE) {
    if (is_array($hideSpec)) {
      foreach ($hideSpec as $key => $spec) {
        list($realKey, $keyLax) = $this->hideKey($key);
        $lax = $lax || $keyLax;

        // If this is the item that's supposed be hidden ($spec is not an
        // array), but it's set, throw an error.
        if (!$lax && $throw && !is_array($spec) && !empty($data[$realKey])) {
          throw new FoundHiddenConfigurationError('Hidden config found in sync. Use "drush deconfig-remove-hidden" to fix.');
        }

        if (is_array($spec)) {
          // Handle recursive unhiding.
          $subData = isset($data[$realKey]) ? $data[$realKey] : [];
          $subActive = isset($activeData[$realKey]) ? $activeData[$realKey] : [];
          $data[$realKey] = $this->doUnhide($hideSpec[$key], $subData, $subActive, $throw, $lax);
          // Unset the key if it's empty (active didn't
          // have any value).
          if (!$lax && empty($data[$realKey])) {
            unset($data[$realKey]);
          }
        }
        else {
          // End of hide spec, copy over the value from active if it exists.
          if (isset($activeData[$realKey])) {
            $data[$realKey] = $activeData[$realKey];
          }
        }
      }
    }
    else {
      // Handle top-level unhiding.
      if (!$lax && $throw && !empty($data) && array_keys($data) !== [self::KEY] && array_keys($data) !== ['@' . self::KEY]) {
        throw new FoundHiddenConfigurationError('Hidden config found in sync. Use "drush deconfig-remove-hidden" to fix.');
      }

      if ($lax && (array_keys($activeData) == [self::KEY] || array_keys($activeData) == ['@' . self::KEY])) {
        return $data;
      }

      return $activeData ?: [];
    }

    return $data;
  }

  protected function hideKey($hideKey) {
    $key = $hideKey;
    $lax = FALSE;

    if ($hideKey[0] === '@') {
      $key = substr($hideKey, 1);
      $lax = TRUE;
    }

    return [$key, $lax];
  }

  /**
   * Split up configuration and deconfig data.
   */
  protected function explode($data) {
    $hideSpec = [];
    $lax = FALSE;

    if (isset($data[self::KEY])) {
      $hideSpec = $data[self::KEY];
      unset($data[self::KEY]);
    }
    elseif (isset($data['@' . self::KEY])) {
      $hideSpec = $data['@' . self::KEY];
      unset($data['@' . self::KEY]);
      $lax = TRUE;
    }

    return [$hideSpec, $data, $lax];
  }

  /**
   * Join configuration and deconfig data.
   */
  protected function implode($hideSpec, $data, $lax) {
    if ($hideSpec) {
      $data = [($lax ? '@' : '') . self::KEY => $hideSpec] + $data;
    }

    return $data;
  }

  /**
   * Read configuration item without error checking.
   */
  public function readRaw($name) {
    list($hideSpec, $data, $lax) = $this->explode($this->storage->read($name));

    if ($hideSpec) {
      $activeData = $this->activeStorage->read($name);
      $data = $this->doUnhide($hideSpec, $data, $activeData, FALSE, $lax);
      $data = $this->implode($hideSpec, $data, $lax);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function exists($name) {
    return $this->storage->exists($name);
  }

  /**
   * {@inheritdoc}
   */
  public function read($name) {
    list($hideSpec, $data, $lax) = $this->explode($this->storage->read($name));

    if ($hideSpec) {
      $activeData = $this->activeStorage->read($name);
      $data = $this->doUnhide($hideSpec, $data, $activeData, TRUE, $lax);
      $data = $this->implode($hideSpec, $data, $lax);
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function readMultiple(array $names) {
    $list = [];
    foreach ($names as $name) {
      if ($data = $this->read($name)) {
        $list[$name] = $data;
      }
    }
    return $list;
  }

  /**
   * {@inheritdoc}
   */
  public function write($name, array $data) {
    list($hideSpec, $data, $lax) = $this->explode($data);

    if ($hideSpec) {
      $storageData = isset($this->deleted[$name]) ? $this->deleted[$name] : $this->storage->read($name);
      unset($this->deleted[$name]);

      $data = $this->doHide($hideSpec, $data, $storageData, $lax);
      $data = $this->implode($hideSpec, $data, $lax);
    }

    return $this->storage->write($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($name) {
    // In case this is a delete in preparation for doing a new export, we need
    // to save the previous values in case there's @'ed values.
    list($hideSpec, $data, $lax) = $this->explode($this->storage->read($name));

    if ($hideSpec) {
      $this->deleted[$name] = $data;
    }

    return $this->storage->delete($name);
  }

  /**
   * {@inheritdoc}
   */
  public function rename($name, $new_name) {
    return $this->storage->rename($name, $new_name);
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data) {
    return $this->storage->encode($data);
  }

  /**
   * {@inheritdoc}
   */
  public function decode($raw) {
    return $this->storage->decode($raw);
  }

  /**
   * {@inheritdoc}
   */
  public function listAll($prefix = '') {
    return $this->storage->listAll($prefix);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll($prefix = '') {
    $files = $this->listAll($prefix);
    $success = !empty($files);
    foreach ($files as $name) {
      if (!$this->delete($name) && $success) {
        $success = FALSE;
      }
    }
    if ($success && $this->collection != StorageInterface::DEFAULT_COLLECTION) {
      // Remove empty directories.
      if (!(new \FilesystemIterator($this->getCollectionDirectory()))->valid()) {
        $this->getFileSystem()->rmdir($this->getCollectionDirectory());
      }
    }
    return $success;
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection($collection) {
    return new static(
      $this->storage->createCollection($collection),
      $this->activeStorage->createCollection($collection)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAllCollectionNames() {
    return $this->storage->getAllCollectionNames();
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->storage->getCollectionName();
  }

}
