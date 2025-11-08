<?php
/**
 * Exocomp - Sitelink Property Sync Module
 * 
 * Synchronizes Wikibase sitelinks with specific properties using boz-mw.
 * 
 * Usage:
 *   php modules/sitelink-property-sync.php [--dry-run] [--help] [--debug]
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('EXOCOMP_ROOT', __DIR__ . '/..');
define('BOZ_MW_ROOT', EXOCOMP_ROOT . '/..');
define('EXOCOMP_START_TIME', time());

// ============================================================================
// Load boz-mw with batteries included
// ============================================================================

$bozmwAutoload = BOZ_MW_ROOT . '/boz-mw/autoload-with-laser-cannon.php';
if (!file_exists($bozmwAutoload)) {
    fatalError(
        "boz-mw not found at: {$bozmwAutoload}\n" .
        "Please copy boz-mw to the parent directory:\n" .
        "  parent-directory/boz-mw/\n" .
        "  parent-directory/exocomp/"
    );
}
require $bozmwAutoload;

// ============================================================================
// Load Exocomp Composer autoloader
// ============================================================================

$composerAutoload = EXOCOMP_ROOT . '/vendor/autoload.php';
if (!file_exists($composerAutoload)) {
    fatalError(
        "Composer dependencies not installed.\n" .
        "Run: cd " . EXOCOMP_ROOT . " && composer install"
    );
}
require $composerAutoload;

// ============================================================================
// Load Environment & Configuration
// ============================================================================

loadEnvironmentVariables();

$configPath = EXOCOMP_ROOT . '/config.php';
if (!file_exists($configPath)) {
    fatalError(
        "Configuration file not found: config.php\n" .
        "Run: cp " . EXOCOMP_ROOT . "/config.example.php " . EXOCOMP_ROOT . "/config.php"
    );
}
$config = require $configPath;

// ============================================================================
// Imports
// ============================================================================

use Exocomp\Bot;
use Exocomp\Api\WikibaseHelper;
use Exocomp\Logger\BotLogger;
use Exocomp\Module\SitelinkPropertySync;

// ============================================================================
// Command Line Arguments
// ============================================================================

$arguments = parseArguments($argv);

if ($arguments['help'] ?? false) {
    showHelp();
    exit(0);
}

$dryRun = $arguments['dry-run'] ?? false;
$verbose = $arguments['verbose'] ?? false;
$debug = $arguments['debug'] ?? false;

// ============================================================================
// Initialization
// ============================================================================

try {
    // Initialize logger
    $logger = new BotLogger(
        $config['logging']['path'] ?? './logs',
        $verbose ? 'debug' : ($config['logging']['level'] ?? 'info')
    );
    
    $logger->info("=".str_repeat("=", 78)."=");
    $logger->info("Exocomp Bot - Sitelink Property Sync Module");
    $logger->info("=".str_repeat("=", 78)."=");
    $logger->info("Start time: " . date('Y-m-d H:i:s'));
    $logger->info("PHP Version: " . phpversion());
    $logger->info("boz-mw path: {$bozmwAutoload}");
    
    if ($dryRun) {
        $logger->warning("DRY-RUN MODE: Changes will NOT be written to the wiki");
    }
    
    if ($debug) {
        $logger->debug("DEBUG MODE: Enabling boz-mw debug output");
    }
    
    // ========================================================================
    // Initialize Wikibase Connection using boz-mw
    // ========================================================================
    
    $logger->info("Connecting to Wikibase instance...");
    
    $wikibaseUrl = $config['wikibase']['url'] ?? '';
    
    if (!$wikibaseUrl) {
        fatalError("Wikibase URL not configured in config.php");
    }
    
    try {
        // Use boz-mw's wiki() factory function
        $wikibase = wiki($wikibaseUrl);
        
        if (!$wikibase) {
            fatalError("Failed to initialize Wikibase connection for: {$wikibaseUrl}");
        }
        
        $logger->info("Connected to: {$wikibaseUrl}");
        
    } catch (\Exception $e) {
        fatalError("Failed to initialize Wikibase: " . $e->getMessage());
    }
    
    // Enable debug if requested
    if ($debug) {
        bozmw_debug();
    }
    
    // ========================================================================
    // Authenticate Bot using boz-mw
    // ========================================================================
    
    $logger->info("Authenticating bot account...");
    
    $botUsername = $config['bot']['username'] ?? '';
    $botPassword = $config['bot']['password'] ?? '';
    
    if (!$botUsername || !$botPassword) {
        fatalError("Bot credentials not configured. Check config.php and .env");
    }
    
    try {
        // Use boz-mw's login() method
        $wikibase->login($botUsername, $botPassword);
        $logger->info("Bot authenticated as: {$botUsername}");
    } catch (\Exception $e) {
        fatalError("Failed to authenticate bot: " . $e->getMessage());
    }
    
    // ========================================================================
    // Module Setup
    // ========================================================================
    
    $logger->info("Initializing SitelinkPropertySync module...");
    
    $moduleConfig = $config['modules']['sitelink-property-sync'] ?? [];
    
    if (!$moduleConfig || !($moduleConfig['enabled'] ?? true)) {
        fatalError("Module is not enabled in configuration");
    }
    
    // Create helper with boz-mw instance
    $helper = new WikibaseHelper($wikibase, $logger);
    
    // Create module
    $module = new SitelinkPropertySync(
        $helper,
        $logger,
        $moduleConfig,
        $dryRun || ($moduleConfig['dry_run'] ?? false)
    );
    
    $logger->info("Module initialized successfully");
    $logger->info("Configuration: " . json_encode($moduleConfig, JSON_PRETTY_PRINT));
    
    // ========================================================================
    // Execution
    // ========================================================================
    
    $logger->info("Starting synchronization process...");
    
    try {
        $module->execute();
    } catch (\Exception $e) {
        $logger->error("Module execution failed: " . $e->getMessage());
        $logger->error("Stack trace: " . $e->getTraceAsString());
        exit(1);
    }
    
    // ========================================================================
    // Results & Statistics
    // ========================================================================
    
    $stats = $module->getStats();
    $elapsed = time() - EXOCOMP_START_TIME;
    
    $logger->info("");
    $logger->info("=".str_repeat("=", 78)."=");
    $logger->info("Execution Statistics");
    $logger->info("=".str_repeat("=", 78)."=");
    $logger->info("Items checked:  " . $stats['checked']);
    $logger->info("Items synced:   " . $stats['synced']);
    $logger->info("Items skipped:  " . $stats['skipped']);
    $logger->info("Errors:         " . $stats['errors']);
    $logger->info("Execution time: " . formatDuration($elapsed));
    $logger->info("=".str_repeat("=", 78)."=");
    
    $logger->info("Bot completed successfully");
    $logger->info("End time: " . date('Y-m-d H:i:s'));
    
    exit(0);
    
} catch (\Exception $e) {
    $errorMsg = "Fatal error: " . $e->getMessage();
    if (isset($logger)) {
        $logger->error($errorMsg);
        $logger->error("Stack trace: " . $e->getTraceAsString());
    } else {
        echo $errorMsg . "\n";
    }
    exit(1);
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Load environment variables from .env file
 */
function loadEnvironmentVariables(): void
{
    $envFile = EXOCOMP_ROOT . '/.env';
    
    if (!file_exists($envFile)) {
        return;
    }
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || trim($line) === '') {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            if (($value[0] ?? null) === '"' && ($value[-1] ?? null) === '"') {
                $value = substr($value, 1, -1);
            } elseif (($value[0] ?? null) === "'" && ($value[-1] ?? null) === "'") {
                $value = substr($value, 1, -1);
            }
            
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

/**
 * Parse command line arguments
 */
function parseArguments(array $argv): array
{
    $arguments = [];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if (strpos($arg, '--') === 0) {
            if (strpos($arg, '=') !== false) {
                [$key, $value] = explode('=', $arg, 2);
                $arguments[substr($key, 2)] = $value;
            } else {
                $arguments[substr($arg, 2)] = true;
            }
        } elseif (strpos($arg, '-') === 0) {
            $arguments[substr($arg, 1)] = true;
        }
    }
    
    return $arguments;
}

/**
 * Show help message
 */
function showHelp(): void
{
    echo <<<'HELP'
Exocomp - Sitelink Property Sync Module

DESCRIPTION:
    Synchronizes Wikibase sitelinks with specific properties using boz-mw.

USAGE:
    php modules/sitelink-property-sync.php [OPTIONS]

OPTIONS:
    --dry-run              Run without making changes
    --verbose              Enable verbose debug output
    --debug                Enable boz-mw debug mode
    --help                 Show this help message

EXAMPLES:
    php modules/sitelink-property-sync.php --dry-run
    php modules/sitelink-property-sync.php --verbose
    php modules/sitelink-property-sync.php --debug

SETUP REQUIREMENTS:
    1. boz-mw must be in parent directory:
       parent-directory/
       ├── boz-mw/
       └── exocomp/

    2. Configuration:
       cp config.example.php config.php
       cp .env.example .env
       # Edit .env with your credentials

    3. Run:
       php modules/sitelink-property-sync.php

HELP;
}

/**
 * Fatal error handler
 */
function fatalError(string $message): void
{
    echo "ERROR: {$message}\n";
    exit(1);
}

/**
 * Format duration
 */
function formatDuration(int $seconds): string
{
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    
    if ($hours > 0) {
        return sprintf("%dh %dm %ds", $hours, $minutes, $secs);
    } elseif ($minutes > 0) {
        return sprintf("%dm %ds", $minutes, $secs);
    } else {
        return sprintf("%ds", $secs);
    }
}
