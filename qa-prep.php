<?php
/**
 * @file
 * Prepares a Pantheon environment for running automated QA tests.
 *
 * Triggered on the env:clone workflow. This script:
 *   1. Enables the du_functional_testing Drupal module, which creates qa_*
 *      test user accounts (one per non-built-in role).
 *   2. Grants the legacy_test_and_training_accounts role to every qa_* user
 *      so they can bypass SSO/SAML during testing.
 *
 * Note: The du_functional_testing install hook may return a non-zero exit code
 * due to known issues, but the qa_* test users are still created. The script
 * therefore logs any errors from that step and always continues.
 */

// ---------------------------------------------------------------------------
// 1. Enable du_functional_testing module.
//    Errors from the install hook are non-fatal – log and continue.
// ---------------------------------------------------------------------------
echo "Enabling du_functional_testing module...\n";
$module_output = [];
$module_code = 0;
exec('drush en du_functional_testing -y 2>&1', $module_output, $module_code);
echo implode("\n", $module_output) . "\n";

if ($module_code !== 0) {
  echo "Warning: du_functional_testing returned exit code {$module_code}. Proceeding to grant roles.\n\n";
}
else {
  echo "du_functional_testing module enabled successfully.\n\n";
}

// ---------------------------------------------------------------------------
// 2. Fetch all Drupal roles to derive the expected qa_* usernames.
// ---------------------------------------------------------------------------
echo "Fetching Drupal role list...\n";
$roles_output = [];
$roles_code = 0;
exec('drush role:list --format=json 2>&1', $roles_output, $roles_code);
$roles_json = implode('', $roles_output);

if ($roles_code !== 0) {
  echo "Error: Failed to retrieve role list (exit code {$roles_code}).\n";
  exit(1);
}

$roles_data = json_decode($roles_json, TRUE);
if (!is_array($roles_data)) {
  echo "Error: Could not parse role list JSON.\n";
  echo "Raw output: {$roles_json}\n";
  exit(1);
}

// Build qa_* usernames from non-built-in role machine names.
// e.g. the "administrator" role → "qa_administrator" user.
$skip_roles = ['anonymous', 'authenticated'];
$qa_users = [];
foreach (array_keys($roles_data) as $role) {
  if (!in_array($role, $skip_roles, TRUE)) {
    $qa_users[] = 'qa_' . $role;
  }
}

if (empty($qa_users)) {
  echo "No QA test users to update. Exiting.\n";
  exit(0);
}

echo 'QA users to update: ' . implode(', ', $qa_users) . "\n\n";

// ---------------------------------------------------------------------------
// 3. Grant legacy_test_and_training_accounts to each qa_* user.
//    This role allows test accounts to bypass SSO/SAML.
// ---------------------------------------------------------------------------
foreach ($qa_users as $username) {
  echo "Granting legacy_test_and_training_accounts to '{$username}'...\n";
  $output = [];
  exec("drush user:role:add legacy_test_and_training_accounts $username 2>&1", $output);
  echo implode("\n", $output) . "\n";
}

echo "QA environment preparation complete.\n";
