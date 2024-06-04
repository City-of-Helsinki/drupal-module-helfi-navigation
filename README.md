# Navigation

![CI](https://github.com/City-of-Helsinki/drupal-module-helfi-navigation/workflows/CI/badge.svg)
[![codecov](https://codecov.io/gh/City-of-Helsinki/drupal-module-helfi-navigation/branch/main/graph/badge.svg?token=FQZHJAJYOZ)](https://codecov.io/gh/City-of-Helsinki/drupal-module-helfi-navigation) [![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=City-of-Helsinki_drupal-module-helfi-navigation&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=City-of-Helsinki_drupal-module-helfi-navigation)

Navigation module allows aggregation of instance specific main-navigations and sharing menus between Helfi-instances.
The master repository for all menus is `Etusivu`-instance

## How to use

- Update the helfi_navigation.setting.yml configuration values.

## Features

- Push instance specific main-navigation to Etusivu-instance.
- Fetch aggregated main-navigation from Etusivu-instance.
- Fetch global header and footer navigations from Etusivu-instance.
- Render Fetched navigations with blocks.
- Custom REST-endpoint for mobile navigation: `/api/v1/global-mobile-menu`

### Main-navigation syncing

Etusivu-instance aggregates all instance specific main-navigations to a single `global navigation`.
Global navigation can be fetched to any instance and rendered using blocks.

Main navigation is synced when:
- User creates/updates/deletes main-navigation's menulink-item
- Makes any changes to main-navigation

If sync fails for any reason the menu is queued and will be synced by a queue worker on next cron run.

A block extending `ExternalMenuBlock`-class handles:
- Fetching the latest version of the navigation.
- Caching the fetched navigation request.
- Rendering the navigation.

### Other navigations

A menu block extending `ExternalMenuBlock`-class can also handle all other supported menus.
Only main-navigation has syncing option. Other navigations are created in Etusivu-instance.

## Local development

- Setup local Etusivu-instance.
  - Run `drush upwd helfi-admin 123` to update admin password. It is used as the API key in other instances.
- Setup any other instance with helfi_navigation module enabled.
  - Add the following line to local.settings.php. Otherwise, syncing global navigation won't work
    ```php
    $config['helfi_api_base.api_accounts']['vault'][] = [
      'id' => 'helfi_navigation',
      'plugin' => 'authorization_token',
      'data' => base64_encode('helfi-admin:123'),
    ];
      ```

Local environment forces synced links always to be absolute URLs, whereas other environments allow relative URLs.

If you wish to replicate the production setup (when testing through one domain, like `helfi-proxy.docker.so` for example). Add the following line to `local.services.yml`:
```yaml
parameters:
  helfi_navigation.absolute_url_always: false
```

### Steps after both instances are up and running.
1. Edit and save menu on any instance with helfi_navigation module enabled.
   - When adding new items, make sure both the menu link and the node are enabled. disabled content won't be synced.
2. Run `drush cron`.
   - After that you can run `docker compose logs app` to see possible exceptions or if menu sync cron succeeded.
3. Go to Etusivu and run drush cr. The navigation should have been updated
    based on the changes you made
4. Instances should fetch the menus from Etusivu and update the related blocks after `drush cr` and page refresh.

## Changes not updating to the global mobile menu?
The global mobile navigation API can be found from `/api/v1/global-mobile-menu` path so check if your changes are
visible there. If they are, the problem is probably caused by caches. The browser caches the global mobile menu for 24 hours. You can clear this cache on Chrome by opening developer tools and on the `Network` tab select the
`Disable cache` checkbox and reload the page.
