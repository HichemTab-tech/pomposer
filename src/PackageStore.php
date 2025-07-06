<?php

namespace HichemTabTech\Pomposer\Console;

use HichemTabTech\Pomposer\Console\Concerns\CommandsUtils;

class PackageStore
{
    use CommandsUtils;
    protected string $storePath;

    public function __construct()
    {
        $basePath = $this->getUserHomeDirectory();
        $this->storePath = $basePath . '/.pomposer-store';
    }

    public function getPackagePath(string $vendor, string $package, string $version): string
    {
        return "$this->storePath/$vendor/$package/$version";
    }

    public function packageExists(string $vendor, string $package, string $version): bool
    {
        return is_dir($this->getPackagePath($vendor, $package, $version));
    }

    public function ensureStoreExists(): void
    {
        if (!is_dir($this->storePath)) {
            mkdir($this->storePath, 0755, true);
        }
    }

    public function getStorePath(): string
    {
        return $this->storePath;
    }
}