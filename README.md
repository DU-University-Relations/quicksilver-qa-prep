# Quicksilver QA Prep

This Quicksilver script automates the preparation of a Pantheon environment for
running automated QA tests. It replaces two manual steps that were previously run
after every database clone:

1. **Enable the `du_functional_testing` module** – equivalent to:
   ```bash
   terminus drush "$SITE.$ENV" -- en du_functional_testing -y
   ```
2. **Grant the `legacy_test_and_training_accounts` role to every `qa_*` test
   user** – the `du_functional_testing` module creates one test user per
   non-built-in Drupal role (e.g. `qa_administrator`, `qa_editor`, …). The
   `legacy_test_and_training_accounts` role allows those accounts to bypass
   SSO/SAML during automated testing.

The script is triggered automatically by the Pantheon `env:clone` workflow
(database clone), so no manual intervention is required after a sync.

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
- The `legacy_test_and_training_accounts` role must exist on the site

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
      "web/private/scripts/quicksilver": ["type:quicksilver-script"]
    }
  }
}
```

Then, install this package via Composer:

```bash
composer require university-of-denver/quicksilver-qa-prep:^1
```

### Add to `pantheon.yml`

Add the following to your `pantheon.yml` file to run the Quicksilver script
whenever a database clone occurs (e.g. syncing Live → Test or Test → Dev):

```yaml
api_version: 1

workflows:
  env_clone:
    after:
      - type: webphp
        description: Prepare environment for QA tests
        script: private/scripts/quicksilver/university-of-denver/quicksilver-qa-prep/qa-prep.php
```

### What the script does

1. Runs `drush en du_functional_testing -y` to enable the functional testing
   module, which creates `qa_*` test user accounts (one per non-built-in role).
   Any errors from the install hook are logged but do **not** stop execution.
2. Runs `drush role:list --format=json` to retrieve all role machine names
   (excluding the built-in `anonymous` and `authenticated` roles).
3. For each role, derives the corresponding `qa_` username
   (e.g. `administrator` → `qa_administrator`) and runs
   `drush user:role:add legacy_test_and_training_accounts <username>`.

If a `qa_*` user does not exist on the cloned environment the script emits a
warning and continues rather than failing the workflow.

No Pantheon secrets are required.
