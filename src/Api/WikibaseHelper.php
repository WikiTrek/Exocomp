<?php

namespace Exocomp\Api;

use Exocomp\Logger\BotLogger;

/**
 * WikibaseHelper - Minimal wrapper around boz-mw
 * 
 * This class should only add convenience methods, not reimplement
 * what boz-mw already provides.
 */
class WikibaseHelper
{
    private $wikibase;  // boz-mw Wikibase instance
    private BotLogger $logger;
    
    public function __construct($wikibase, BotLogger $logger)
    {
        $this->wikibase = $wikibase;
        $this->logger = $logger;
    }
    
    /**
     * Get an item - delegates to boz-mw
     */
    public function getItem(string $itemId): ?array
    {
        try {
            // This assumes boz-mw's query() method handles the API call
            $result = $this->wikibase->query([
                'action' => 'wbgetentities',
                'ids' => $itemId,
                'props' => 'sitelinks|claims',
                'format' => 'json',
            ]);
            
            return $result['entities'][$itemId] ?? null;
        } catch (\Exception $e) {
            $this->logger->error("getItem({$itemId}): {$e->getMessage()}");
            return null;
        }
    }
    
    /**
     * Extract property value from item - LOCAL LOGIC ONLY
     */
    public function getPropertyValue(array $item, string $property): ?string
    {
        $claims = $item['claims'][$property] ?? [];
        if (empty($claims)) {
            return null;
        }
        
        $value = $claims[0]['mainsnak']['datavalue']['value'] ?? null;
        if (!$value) {
            return null;
        }
        
        return is_array($value) && isset($value['id']) ? $value['id'] : (string)$value;
    }
    
    /**
     * Extract sitelink value from item - LOCAL LOGIC ONLY
     */
    public function getSitelinkValue(array $item, string $site): ?string
    {
        return $item['sitelinks'][$site]['title'] ?? null;
    }
    
    /**
     * Set sitelink - delegates to boz-mw
     */
    public function setSitelink(string $itemId, string $site, string $title): bool
    {
        try {
            // Assumes boz-mw's post() method handles POST requests
            $this->wikibase->post([
                'action' => 'wbsetsitelink',
                'id' => $itemId,
                'linksite' => $site,
                'linktitle' => $title,
                'summary' => "Updated via Exocomp",
                'format' => 'json',
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("setSitelink({$itemId}): {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Set claim - delegates to boz-mw
     */
    public function setClaimValue(string $itemId, string $property, string $value): bool
    {
        try {
            // Assumes boz-mw can handle this, but check documentation
            $this->wikibase->post([
                'action' => 'wbsetclaim',
                'entity' => $itemId,
                'property' => $property,
                'snaktype' => 'value',
                'value' => json_encode(['entity-type' => 'item', 'numeric-id' => (int)substr($value, 1)]),
                'summary' => "Updated via Exocomp",
                'format' => 'json',
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("setClaimValue({$itemId}): {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Get all items - delegates to boz-mw for API call
     */
    public function getAllItems(int $limit = 500): array
    {
        $items = [];
        $continue = null;
        
        try {
            while (true) {
                $query = [
                    'action' => 'query',
                    'list' => 'allpages',
                    'apnamespace' => '0',
                    'aplimit' => min($limit, 500),
                    'format' => 'json',
                ];
                
                if ($continue !== null) {
                    $query['apcontinue'] = $continue;
                }
                
                // boz-mw should handle the HTTP request
                $result = $this->wikibase->query($query);
                
                if (!isset($result['query']['allpages'])) {
                    break;
                }
                
                foreach ($result['query']['allpages'] as $page) {
                    $items[] = $page['title'];
                }
                
                if (count($items) >= $limit) {
                    break;
                }
                
                // Check for continuation
                if (!isset($result['continue']['apcontinue'])) {
                    break;
                }
                
                $continue = $result['continue']['apcontinue'];
            }
            
            return array_slice($items, 0, $limit);
            
        } catch (\Exception $e) {
            $this->logger->error("getAllItems(): {$e->getMessage()}");
            return [];
        }
    }
    
    /**
     * Get raw boz-mw instance for direct access if needed
     */
    public function getRawWikibase()
    {
        return $this->wikibase;
    }
}
