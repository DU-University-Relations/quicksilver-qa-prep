# Quicksilver QA Prep

This Quicksilver script automates the preparation of a Pantheon environment for
running automated QA tests. It replaces a manual step that was previously run
after every database clone:

1. **Enable the `du_functional_testing` module** – equivalent to:
   ```bash
   terminus drush "$SITE.$ENV" -- en du_functional_testing -y
   ```

The `du_functional_testing` module creates the `qa_*` test users and grants the
role needed for local logins.

The script is triggered automatically by the Pantheon `env:clone` workflow
(database clone), so no manual intervention is required after a sync.

> **Environment restriction:** The script executes on any non-live Pantheon
> environment, including Multidevs. On **live**, it exits immediately without
> making changes.

> **Note:** The `du_functional_testing` install hook is known to sometimes
> return a non-zero exit code. The script logs any such error and continues
> regardless, because the `qa_*` test users are still created even when the
> hook exits with an error.

This project was developed from a template for new Quicksilver projects to utilize so
that Quicksilver scripts can be installed through Composer.

Original template: https://github.com/pantheon-quicksilver/quicksilver-template

## Requirements

- PHP 8.0 or higher
- Composer
- Drupal 9+ site running on Pantheon with the `du_functional_testing` module
  available in the codebase

## Installation

This project is designed to be included from a site's `composer.json` file, and
placed in its appropriate installation directory by
[Composer Installers](https://github.com/composer/installers).

In order for this to work, you should have the following in your `composer.json`
file:

```json
{
  "require": {
    "composer/installers": "^1"
  },
  "extra": {
    "installer-paths": {
      "web/private/scripts/quicksilver/{$name}/": ["type:quicksilver-script"]
    }
  }
}
```

Then, install this package via Composer:

```bash
composer require university-of-denver/quicksilver-qa-prep:^1
```

### Add to `pantheon.yml`

Choose the hooks that match when QA starts for your site. Pantheon's
[Quicksilver hook reference](https://docs.pantheon.io/guides/quicksilver/hooks)
lists the supported workflows and where each hook runs.

#### Database clones

Use `clone_database` when QA follows a content sync, such as Live → Test,
Test → Dev, or a database clone into a Multidev:

```yaml
api_version: 1

workflows:
  clone_database:
    after:
      - type: webphp
        description: Prepare environment for QA tests
        script: private/scripts/quicksilver/quicksilver-qa-prep/qa-prep.php
```

This is the smallest recommended configuration for sites where every QA run
starts with a database clone.

#### Autopilot

Autopilot applies updates in an isolated Multidev before running visual
regression tests. Pantheon's documentation does not guarantee that Autopilot's
internal environment clone invokes a site's `clone_database` hook, so do not
rely on that hook alone for an Autopilot-triggered QA run.

For a separate QA workflow that starts after Autopilot VRT, add the explicit
`autopilot_vrt` hook. Quicksilver scripts run sequentially, so list this script
before the hook that starts the QA workflow:

```yaml
api_version: 1

workflows:
  clone_database:
    after:
      - type: webphp
        description: Prepare environment for QA tests after a database clone
        script: private/scripts/quicksilver/quicksilver-qa-prep/qa-prep.php
  autopilot_vrt:
    after:
      - type: webphp
        description: Prepare environment for post-Autopilot QA tests
        script: private/scripts/quicksilver/quicksilver-qa-prep/qa-prep.php
      # Add the hook that starts the separate QA workflow after this entry.
```

The `autopilot_vrt` hook only supports the `after` stage. It prepares accounts
for follow-on tests, not for Pantheon's VRT that just completed.

If accounts must exist before Autopilot VRT, add `sync_code` as an earlier
preparation hook:

```yaml
workflows:
  sync_code:
    after:
      - type: webphp
        description: Prepare environment for QA tests after a code sync
        script: private/scripts/quicksilver/quicksilver-qa-prep/qa-prep.php
```

Pantheon documents `sync_code` for Git pushes, upstream updates, Multidev
merges, and automated workflows. On Integrated Composer sites it runs after
build artifacts are deployed. Because it runs frequently, confirm in the
site's workflow logs that it matches the intended Autopilot flow before adding
it only for this purpose.

#### Other useful hooks

The script is safe to invoke repeatedly because enabling an already-enabled
module is idempotent. Depending on the site's workflow, these hooks may also be
useful:

| Hook | Add it when |
| --- | --- |
| `create_cloud_development_environment` (`after` only) | A newly created Multidev must be ready for QA even when no separate database clone follows. |
| `deploy` | QA runs on Test after code is deployed without cloning the database. The script's environment guard makes a Live invocation a no-op. |
| `sync_code` | QA can follow a Git push, upstream update, Multidev merge, or automated code workflow. This hook runs frequently, so omit it if database-clone coverage is sufficient. |

Do not use `clear_cache` as a general QA-preparation trigger; cache clears can
happen often and do not indicate that a testable environment was created or
updated.

### What the script does

1. Runs `drush en du_functional_testing -y` to enable the functional testing
   module, which creates `qa_*` test user accounts and grants the role needed
   for local logins. Any errors from the install hook are logged but do **not**
   fail the workflow.

No Pantheon secrets are required.
