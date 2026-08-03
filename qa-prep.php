<?php
/**
 * @file
 * Prepares a Pantheon environment for running automated QA tests.
 *
 * Triggered on the env:clone workflow. This script:
 *   1. Enables the du_functional_testing Drupal module, which creates qa_*
 *      test user accounts and grants the role needed for local logins.
 *
 * Note: The du_functional_testing install hook may return a non-zero exit code
 * due to known issues, but the qa_* test users are still created. The script
 * therefore logs any errors from that step without failing the workflow.
 */

// ---------------------------------------------------------------------------
// 0. Environment guard – run on any non-live environment.
// ---------------------------------------------------------------------------
$current_env = $_ENV['PANTHEON_ENVIRONMENT'] ?? getenv('PANTHEON_ENVIRONMENT') ?: 'unknown';

if ($current_env === 'live') {
  echo "Skipping QA prep: the script does not run on the live environment.\n";
  exit(0);
}

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
  echo "Warning: du_functional_testing returned exit code {$module_code}.\n\n";
}
else {
  echo "du_functional_testing module enabled successfully.\n\n";
}

echo "QA environment preparation complete.\n";
