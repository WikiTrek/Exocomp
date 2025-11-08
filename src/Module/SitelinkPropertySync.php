<?php

namespace Exocomp\Module;

use Exocomp\Api\WikibaseHelper;
use Exocomp\Logger\BotLogger;

class SitelinkPropertySync implements ModuleInterface
{
    private WikibaseHelper $wikibaseHelper;
    private BotLogger $logger;
    private array $config;
    private bool $dryRun = false;
    private array $stats = [
        'checked' => 0,
        'synced' => 0,
        'errors' => 0,
        'skipped' => 0,
    ];
    
    public function __construct(
        WikibaseHelper $wikibaseHelper,
        BotLogger $logger,
        array $config,
        bool $dryRun = false
    ) {
        $this->wikibaseHelper = $wikibaseHelper;
        $this->logger = $logger;
        $this->config = $config;
        $this->dryRun = $dryRun;
    }
    
    /**
     * Main execution method
     */
    public function execute(): void
    {
        $property = $this->config['property'] ?? 'P42';
        $sitelink = $this->config['sitelink'] ?? 'wikidata';
        
        $mode = $this->dryRun ? 'DRY-RUN' : 'LIVE';
        $this->logger->info("Starting sitelink-property sync [{$mode}] (Property: {$property}, Sitelink: {$sitelink})");
        
        // Get all item IDs from Wikibase
        $itemIds = $this->wikibaseHelper->getAllItems();
        
        if (empty($itemIds)) {
            $this->logger->warning("No items found to process");
            return;
        }
        
        $this->logger->info("Processing " . count($itemIds) . " items");
        
        foreach ($itemIds as $itemId) {
            $this->syncItem($itemId, $property, $sitelink);
        }
        
        $this->logger->info("Sync complete. Stats: " . json_encode($this->stats));
    }
    
    /**
     * Synchronize a single item
     */
    private function syncItem(string $itemId, string $property, string $sitelink): void
    {
        $this->stats['checked']++;
        
        try {
            $item = $this->wikibaseHelper->getItem($itemId);
            
            if (!$item) {
                $this->logger->warning("Item {$itemId} not found");
                $this->stats['errors']++;
                return;
            }
            
            // Get values from sitelink and property
            $sitelinkValue = $this->wikibaseHelper->getSitelinkValue($item, $sitelink);
            $propertyValue = $this->wikibaseHelper->getPropertyValue($item, $property);
            
            // Log what we found
            $this->logger->debug("Item {$itemId}: sitelink={$sitelinkValue}, property={$propertyValue}");
            
            // If both are empty, skip
            if (!$sitelinkValue && !$propertyValue) {
                $this->logger->debug("Item {$itemId} has neither sitelink nor property, skipping");
                $this->stats['skipped']++;
                return;
            }
            
            // If they match, skip
            if ($sitelinkValue === $propertyValue) {
                $this->logger->debug("Item {$itemId} already synchronized");
                $this->stats['skipped']++;
                return;
            }
            
            // Determine which value to use (prefer sitelink if it exists)
            $targetValue = $sitelinkValue ?? $propertyValue;
            
            if ($this->dryRun) {
                $this->logger->info("[DRY-RUN] Would sync {$itemId}: sitelink={$sitelinkValue}, property={$propertyValue} -> {$targetValue}");
                $this->stats['synced']++;
                return;
            }
            
            // Synchronize
            $updated = false;
            
            if ($sitelinkValue !== $targetValue && $sitelinkValue !== null) {
                if ($this->wikibaseHelper->setSitelink($itemId, $sitelink, $targetValue)) {
                    $this->logger->info("Updated sitelink for {$itemId} to '{$targetValue}'");
                    $updated = true;
                } else {
                    $this->logger->error("Failed to update sitelink for {$itemId}");
                    $this->stats['errors']++;
                }
            }
            
            if ($propertyValue !== $targetValue && $propertyValue !== null) {
                if ($this->wikibaseHelper->setClaimValue($itemId, $property, $targetValue)) {
                    $this->logger->info("Updated property {$property} for {$itemId} to '{$targetValue}'");
                    $updated = true;
                } else {
                    $this->logger->error("Failed to update property {$property} for {$itemId}");
                    $this->stats['errors']++;
                }
            }
            
            if ($updated) {
                $this->stats['synced']++;
            }
            
        } catch (\Exception $e) {
            $this->logger->error("Error processing {$itemId}: " . $e->getMessage());
            $this->stats['errors']++;
        }
    }
    
    /**
     * Get execution statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }
    
    /**
     * Get module metadata
     */
    public function getMetadata(): array
    {
        return [
            'name' => 'SitelinkPropertySync',
            'description' => 'Synchronizes a sitelink with a specific property in Wikibase items',
            'version' => '1.0.0',
            'author' => 'WikiTrek',
        ];
    }
}
