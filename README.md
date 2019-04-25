Deconfig
========

Drupal 8's configuration management is great for controlling site
configuration, but sometimes a developer wants to loosen the shackles
on certain configuration items and let the site administrator change
the setting without it being overwritten by the configuration
management system on the next configuration sync.

As a safety measure, it requires the hidden configuration not to be
present in the config sync storage so it's obvious for developers that
the given configuration is not enforced. It will throw an error if it
encounters a hidden config item in config sync, which will cause
`drush cim`/`drush cex` to error out.

## Deconfig'ing configuration

In order to deconfig, simply add a `_deconfig` entry to the file in
the config sync storage file specifying what to deconfig, and remove
the configuration. The `_deconfig` item duplicates the hierarchy of
the configuration, allowing for deconfig'ing the entire file or
individual items.

The values of `_deconfig` are strings that ideally should describe why
the item was hidden.

### Examples

To deconfig whole file:

``` yaml
_deconfig: 'Hide this configuration'
```

To deconfig site.mail:

``` yaml
_deconfig:
  mail: 'Let administrator configure site email'
name: 'Le site'
slogan: 'One site to rule them all'
page:
  403: ''
  404: ''
  front: '/frontpage'
admin_compact_mode: false
weight_select_max: 100
langcode: en
default_langcode: en
```

## Drush commands

`drush deconfig-remove-hidden`: Clean up command that'll remove any
deconfig'ed configuration from config sync. This is the easy way out
of `cim`/`cex` throwing errors.

## Implementation details

Deconfig does its magic by implementing a config.storage.sync storage
that reads deconfig'ed configuration from the active storage rather
than the sync storage, so they will always appear to be up to date.
