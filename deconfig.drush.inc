<?php

/**
 * @file
 * Drush commands for Deconfig.
 */

use Drupal\Core\Config\StorageInterface;
use Drupal\deconfig\DeconfigStorage;
use Drupal\deconfig\Exception\FoundHiddenConfigurationError;
use Drush\Log\LogLevel;

/**
 * Implements hook_drush_help().
 */
function deconfig_drush_help($command) {
  switch ($command) {
    case 'drush:deconfig-remove-hidden':
      return dt('Remove any configuration in sync storage that has been hidden.');
  }
}

/**
 * Implements hook_drush_command().
 */
function deconfig_drush_command() {
  $items = [];

  $items['deconfig-remove-hidden'] = [
    'description' => dt('Remove hidden config from config.storage.sync.'),
    'examples' => [
      'Clean configuration' => 'drush deconfig-remove-hidden',
    ],
    'bootstrap' => DRUSH_BOOTSTRAP_DATABASE,
    'aliases' => ['drh'],
  ];

  return $items;
}

/**
 * Callback for deconfig-remove-hidden commond.
 */
function drush_deconfig_remove_hidden() {
  $storage = Drupal::service('config.storage.sync');
  if (!$storage instanceof DeconfigStorage) {
    drush_set_error('BAD_STORAGE', "config.storage.sync is not Deconfig. Someone's messed with it?");
    return;
  }

  // Loop through all config items in all collections and rewrite them (which
  // removes hidden configuration) if loading failed.
  $collections = [StorageInterface::DEFAULT_COLLECTION] + $storage->getAllCollectionNames();
  foreach ($collections as $collection) {
    $storage = $storage->createCollection($collection);
    foreach ($storage->listAll() as $name) {
      try {
        $storage->read($name);
      }
      catch (FoundHiddenConfigurationError $e) {
        $storage->write($name, $storage->readRaw($name));
        drush_log('Removed hidden configuration from "' . $name . '"', LogLevel::OK);
      }
    }
  }
}
