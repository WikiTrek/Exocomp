<?php
/**
 * Exocomp Bot Configuration Example
 * 
 * Copy this file to config.php and update with your actual values.
 * 
 * SECURITY: Never commit config.php to Git!
 * Use environment variables for sensitive credentials.
 */

return [
    // Your Wikibase instance
    'wikibase' => [
        'url' => $_ENV['EXOCOMP_WIKIBASE_URL'] ?? 'https://data.wikitrek.org',
        'api_path' => $_ENV['EXOCOMP_API_PATH'] ?? '/w/api.php',
    ],
    
    // Bot credentials - ALWAYS use environment variables!
    'bot' => [
        'username' => $_ENV['EXOCOMP_BOT_USERNAME'] ?? 'ExocompBot',
        'password' => $_ENV['EXOCOMP_BOT_PASSWORD'] ?? '', // Bot password name
        'token' => $_ENV['EXOCOMP_BOT_TOKEN'] ?? '',       // Bot token
    ],
    
    // Logging configuration
    'logging' => [
        'path' => $_ENV['EXOCOMP_LOG_PATH'] ?? __DIR__ . '/logs',
        'level' => $_ENV['EXOCOMP_LOG_LEVEL'] ?? 'info',
    ],
    
    // Module configurations
    'modules' => [
        'sitelink-property-sync' => [
            'enabled' => true,
            'property' => 'P42',
            'sitelink' => 'wikidata',
            'dry_run' => false,
        ],
    ],
];
