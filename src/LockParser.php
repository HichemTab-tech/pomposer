<?php

namespace HichemTabTech\Pomposer\Console;

use RuntimeException;

class LockParser
{
    protected string $lockPath;

    public function __construct(string $lockPath = 'composer.lock')
    {
        $this->lockPath = $lockPath;
    }

    public function getPackages(): array
    {
        if (!file_exists($this->lockPath)) {
            throw new RuntimeException("composer.lock not found at $this->lockPath");
        }

        $data = json_decode(file_get_contents($this->lockPath), true);

        if (!isset($data['packages'])) {
            throw new RuntimeException("Invalid composer.lock format.");
        }

        return $data['packages']; // You can also return 'packages-dev' if needed
    }
}