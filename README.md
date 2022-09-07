# HELfi navigation

![CI](https://github.com/City-of-Helsinki/drupal-module-helfi-navigation/workflows/CI/badge.svg)

Helfi navigation allows aggregation of instance specific menus and sharing menus between Helfi-instances.
The master repository for all menus is `Etusivu`-instance


## Features

- Push instance specific main-navigation to Etusivu-instance.
- Fetch aggregated main-navigation from Etusivu-instance.
- Fetch global header and footer navigations from Etusivu-instance.
- Render Fetched navigations with blocks.

Supported menus:
- `main`
- `footer-bottom-navigation`
- `footer-top-navigation`
- `footer-top-navigation-2`
- `header-top-navigation`


### Main-navigation syncing

Helfi_navigation-module can push instance specific `main`-navigation to Etusivu.

Etusivu-instance aggregates all instance specific main-navigations to a single `global navigation`.
Global navigation can be fetched to any instance and rendered using blocks.

Main navigation is synced when:
- User creates/updates/deletes main-navigation's menulink-item
- Makes any changes to main-navigation

If sync fails for any reason the menu is queued and will be synced by a queue worker on next cron run.

- A block extending `ExternalMenuBlock`-class handles
  - Fetching the latest version of the navigation.
  - Caching the fetched navigation request.
  - Rendering the navigation.

### Other navigations

- A menu block extending `ExternalMenuBlock`-class can also handle all other supported menus.
  - Only main-navigation has syncing option. Other navigations are created in Etusivu-instance.

