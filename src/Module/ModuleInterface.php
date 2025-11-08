<?php

namespace Exocomp\Module;

/**
 * Interface for all Exocomp modules
 * 
 * Each module must implement this interface to be
 * compatible with the Exocomp bot framework
 */
interface ModuleInterface
{
    /**
     * Execute the module
     */
    public function execute(): void;
    
    /**
     * Get execution statistics
     */
    public function getStats(): array;
    
    /**
     * Get module metadata
     */
    public function getMetadata(): array;
}
