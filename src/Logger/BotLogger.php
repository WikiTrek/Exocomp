<?php

namespace Exocomp\Logger;

class BotLogger
{
    private string $logPath;
    private string $logFile;
    private array $levels = ['debug', 'info', 'warning', 'error'];
    private string $currentLevel;
    
    public function __construct(string $logPath, string $level = 'info')
    {
        $this->logPath = $logPath;
        $this->currentLevel = $level;
        
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }
        
        $this->logFile = $logPath . '/exocomp-' . date('Y-m-d') . '.log';
    }
    
    public function debug(string $message): void
    {
        $this->log('debug', $message);
    }
    
    public function info(string $message): void
    {
        $this->log('info', $message);
    }
    
    public function warning(string $message): void
    {
        $this->log('warning', $message);
    }
    
    public function error(string $message): void
    {
        $this->log('error', $message);
    }
    
    private function log(string $level, string $message): void
    {
        if (!in_array($level, $this->levels)) {
            return;
        }
        
        $levelIndex = array_search($level, $this->levels);
        $currentLevelIndex = array_search($this->currentLevel, $this->levels);
        
        // Only log if this level is >= current level threshold
        if ($levelIndex < $currentLevelIndex) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        echo $logMessage; // Also output to console
    }
}
