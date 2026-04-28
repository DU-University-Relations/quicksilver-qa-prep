# Quicksilver QA Prep

This Quicksilver script automates the preparation of a Pantheon environment for
running automated QA tests. It replaces two manual steps that were previously run
after every database clone:

1. **Enable the `du_functional_testing` module** – equivalent to:
   ```bash
   terminus drush "$SITE.$ENV" -- en du_functional_testing -y
   ```
2. **Grant all Drupal roles to QA login user(s)** – equivalent to the
   `grant-login-roles` npm script in `du-playwright`, which wraps
   `drush user:role:add` calls for every role returned by `drush role:list`.

The script is triggered automatically by the Pantheon `env:clone` workflow
(database clone), so no manual intervention is required after a sync.

This project was developed from a template for new Quicksilver projects to utilize so
that Quicksilver scripts can be installed through Composer.


Original template: https://github.com/pantheon-quicksilver/quicksilver-template

## Requirements

- PHP 8.0 or higher
- Composer
- Drupal 9+ site running on Pantheon with the `du_functional_testing` module
  available in the codebase
- (Optional) `qa_login_users` secret set via Terminus Secrets Manager – see
  [Configuration](#configuration) below

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

### Configuration

The only optional configuration is the list of Drupal usernames that should
receive all available roles after a database clone.

| Pantheon Secret   | Description                                                                 | Default    |
|-------------------|-----------------------------------------------------------------------------|------------|
| `qa_login_users`  | Comma-separated list of Drupal usernames to grant all roles (e.g. `qa_user,tester`) | `qa_user`  |

Set the secret via [Terminus Secrets Manager](https://github.com/pantheon-systems/terminus-secrets-manager-plugin):

```bash
terminus secret:site:set <site-name> qa_login_users "qa_user,another_test_user"
```

### What the script does

1. Runs `drush en du_functional_testing -y` to enable the functional testing
   module on the cloned environment.
2. Runs `drush role:list --format=json` to retrieve all role machine names
   (excluding the built-in `anonymous` and `authenticated` roles).
3. For each username listed in `qa_login_users`, verifies the account exists
   and then runs `drush user:role:add <role> <username>` for every role.

If a user does not exist on the cloned environment the script emits a warning
and continues rather than failing the workflow.
