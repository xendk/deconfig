<?php

namespace Drupal\deconfig\Commands;

use Drupal\Core\Config\StorageInterface;
use Drupal\deconfig\DeconfigStorage;
use Drupal\deconfig\Exception\FoundHiddenConfigurationError;
use Drush\Commands\DrushCommands;

class DeconfigCommands extends DrushCommands {

  /**
   * The deconfig storage.
   *
   * @var StorageInterface
   */
  protected $storage;

  /**
   * DeconfigCommands constructor.
   *
   * @param StorageInterface $storage
   *   The deconfig storage.
   */
  public function __construct(StorageInterface $storage) {
    parent::__construct();
    $this->storage = $storage;
  }

  /**
   * Remove any configuration in sync storage that has been hidden.
   *
   * @command deconfig-remove-hidden
   * @aliases drh
   * @bootstrap database
   * @usage deconfig-remove-hidden
   *   Removes hidden config from config.storage.sync.
   */
  public function deconfigRemoveHidden() {
    if (!$this->storage instanceof DeconfigStorage) {
      $this->logger()->error("config.storage.sync is not Deconfig. Someone's messed with it?");
      return;
    }

    // Loop through all config items in all collections and rewrite them (which
    // removes hidden configuration) if loading failed.
    $collections = [StorageInterface::DEFAULT_COLLECTION] + $this->storage->getAllCollectionNames();
    foreach ($collections as $collection) {
      $storage = $this->storage->createCollection($collection);
      foreach ($storage->listAll() as $name) {
        try {
          $storage->read($name);
        }
        catch (FoundHiddenConfigurationError $e) {
          $storage->write($name, $storage->readRaw($name));
          $this->logger()->notice('Removed hidden configuration from "' . $name . '"');
        }
      }
    }
  }

}
