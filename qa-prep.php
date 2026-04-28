<?php
/**
 * @file
 * Prepares a Pantheon environment for running automated QA tests.
 *
 * Triggered on the env:clone workflow. This script:
 *   1. Enables the du_functional_testing Drupal module.
 *   2. Grants all available Drupal roles to the QA login user(s).
 *
 * Required Pantheon secret (set via Terminus Secrets Manager):
 *   - qa_login_users: Comma-separated list of Drupal usernames to receive all
 *     roles. Defaults to "qa_user" when not set.
 */

// ---------------------------------------------------------------------------
// 1. Enable du_functional_testing module.
// ---------------------------------------------------------------------------
echo "Enabling du_functional_testing module...\n";
$output = [];
$return_code = 0;
exec('drush en du_functional_testing -y 2>&1', $output, $return_code);
echo implode("\n", $output) . "\n";

if ($return_code !== 0) {
  echo "Error: Failed to enable du_functional_testing module (exit code {$return_code}).\n";
  exit(1);
}
echo "du_functional_testing module enabled successfully.\n\n";

// ---------------------------------------------------------------------------
// 2. Get all available Drupal roles.
// ---------------------------------------------------------------------------
echo "Fetching Drupal role list...\n";
$roles_json = '';
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

// Collect role machine names, skipping the built-in anonymous/authenticated roles.
$skip_roles = ['anonymous', 'authenticated'];
$role_names = array_filter(array_keys($roles_data), function ($role) use ($skip_roles) {
  return !in_array($role, $skip_roles, TRUE);
});

if (empty($role_names)) {
  echo "No additional roles found to assign. Skipping grant-login-roles step.\n";
  exit(0);
}

echo 'Roles to assign: ' . implode(', ', $role_names) . "\n\n";

// ---------------------------------------------------------------------------
// 3. Determine which users should receive all roles.
// ---------------------------------------------------------------------------
$qa_users_secret = pantheon_get_secret('qa_login_users');
$qa_users_raw = !empty($qa_users_secret) ? $qa_users_secret : 'qa_user';
$qa_users = array_filter(array_map('trim', explode(',', $qa_users_raw)));

if (empty($qa_users)) {
  echo "Warning: No QA users configured. Set the 'qa_login_users' Pantheon secret.\n";
  exit(0);
}

// ---------------------------------------------------------------------------
// 4. Grant all roles to each QA user.
// ---------------------------------------------------------------------------
$roles_csv = implode(',', $role_names);
$all_success = TRUE;

foreach ($qa_users as $username) {
  echo "Granting roles to user '{$username}'...\n";

  // Verify the user exists before attempting to assign roles.
  $check_output = [];
  $check_code = 0;
  exec("drush user:information --format=json " . escapeshellarg($username) . " 2>&1", $check_output, $check_code);
  if ($check_code !== 0) {
    echo "Warning: User '{$username}' not found – skipping.\n\n";
    continue;
  }

  foreach ($role_names as $role) {
    $add_output = [];
    $add_code = 0;
    exec('drush user:role:add ' . escapeshellarg($role) . ' ' . escapeshellarg($username) . ' 2>&1', $add_output, $add_code);
    if ($add_code !== 0) {
      echo "Warning: Could not assign role '{$role}' to '{$username}': " . implode(' ', $add_output) . "\n";
      $all_success = FALSE;
    }
    else {
      echo "  Role '{$role}' assigned to '{$username}'.\n";
    }
  }
  echo "\n";
}

if ($all_success) {
  echo "QA environment preparation complete.\n";
}
else {
  echo "QA environment preparation finished with warnings. Review output above.\n";
}
