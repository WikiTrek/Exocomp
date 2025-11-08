<?php

namespace Exocomp;

use Exocomp\Logger\BotLogger;
use Exocomp\Module\ModuleInterface;

class Bot
{
    private BotLogger $logger;
    private array $config;
    private array $modules = [];
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->logger = new BotLogger(
            $config['logging']['path'],
            $config['logging']['level']
        );
    }
    
    /**
     * Register a module
     */
    public function registerModule(string $name, ModuleInterface $module): void
    {
        $this->modules[$name] = $module;
        $this->logger->debug("Module registered: {$name}");
    }
    
    /**
     * Run a specific module
     */
    public function runModule(string $name): void
    {
        if (!isset($this->modules[$name])) {
            throw new \Exception("Module not found: {$name}");
        }
        
        $module = $this->modules[$name];
        $this->logger->info("Running module: {$name}");
        
        $module->execute();
        
        $stats = $module->getStats();
        $this->logger->info("Module {$name} completed with stats: " . json_encode($stats));
    }
    
    /**
     * Run all enabled modules
     */
    public function runAll(): void
    {
        if (empty($this->modules)) {
            $this->logger->warning("No modules registered");
            return;
        }
        
        foreach ($this->modules as $name => $module) {
            try {
                $this->runModule($name);
            } catch (\Exception $e) {
                $this->logger->error("Error running module {$name}: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get logger instance
     */
    public function getLogger(): BotLogger
    {
        return $this->logger;
    }
}
