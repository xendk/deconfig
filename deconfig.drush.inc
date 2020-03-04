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
  \Drupal::service('deconfig.commands')->deconfigRemoveHidden();
}
