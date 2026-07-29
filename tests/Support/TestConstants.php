<?php

/**
 * Synthetic Test Constants for automated testing.
 * All test values are synthetic and do not represent real individuals.
 * Raw National ID values must never appear in logs, reports, or evidence output.
 */

// Synthetic test National IDs - used only in test assertions and seeding.
// Reports must use redacted labels: ********01, ********02, etc.
define('TEST_NATIONAL_ID_ACTIVE', '1000000001');
define('TEST_NATIONAL_ID_INACTIVE', '1000000002');
define('TEST_NATIONAL_ID_ARCHIVED', '1000000003');
define('TEST_NATIONAL_ID_NONEXISTENT', '9999999999');
define('TEST_NATIONAL_ID_LOCKOUT_TARGET', '9990001112');

/**
 * Redact a National ID for safe display in reports and evidence output.
 * Shows only last 2 digits.
 */
function redactNationalId(string $nationalId): string
{
    return '********' . substr($nationalId, -2);
}
