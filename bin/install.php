<?php
/**
 * Standalone installer for the whmcs-webpay gateway schema.
 *
 * Usage (from repo root):
 *   php bin/install.php
 *
 * This script is idempotent: it creates the required tables if they do not
 * exist and applies lightweight column upgrades. It is the recommended way
 * to provision the schema when deploying new releases instead of relying on
 * implicit table creation at request time.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$repoRoot = dirname(__DIR__);
$autoloadCandidates = [
    $repoRoot . '/vendor/autoload.php',
    $repoRoot . '/../../autoload.php',
];

$autoloadLoaded = false;
foreach ($autoloadCandidates as $candidate) {
    if (file_exists($candidate)) {
        require_once $candidate;
        $autoloadLoaded = true;
        break;
    }
}

if (!$autoloadLoaded) {
    fwrite(STDERR, "Composer autoload not found. Run `composer install` before executing this script.\n");
    exit(1);
}

$whmcsRoot = getenv('WHMCS_ROOT') ?: dirname($repoRoot, 2);
if (!file_exists($whmcsRoot . '/init.php') && !file_exists($whmcsRoot . '/dbconnect.php')) {
    fwrite(STDERR, "WHMCS root not found at {$whmcsRoot}. Set WHMCS_ROOT env var to the WHMCS installation path.\n");
    exit(1);
}

if (file_exists($whmcsRoot . '/init.php')) {
    require_once $whmcsRoot . '/init.php';
} else {
    require_once $whmcsRoot . '/dbconnect.php';
}

if (!class_exists('\\WHMCS\\Database\\Capsule')) {
    fwrite(STDERR, "WHMCS Capsule not available. Ensure the script runs in a WHMCS-aware PHP context.\n");
    exit(1);
}

require_once $repoRoot . '/modules/gateways/webpaydirecto/lib/TransactionStore.class.php';

try {
    \WebpayDirecto\TransactionStore::install();
    fwrite(STDOUT, "whmcs-webpay schema installed successfully.\n");
    fwrite(STDOUT, "  - " . \WebpayDirecto\TransactionStore::TABLE . "\n");
    fwrite(STDOUT, "  - " . \WebpayDirecto\TransactionStore::RATE_LIMIT_TABLE . "\n");
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "Installation failed: " . $e->getMessage() . "\n");
    exit(1);
}
