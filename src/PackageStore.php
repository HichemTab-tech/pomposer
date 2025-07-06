<?php

namespace HichemTabTech\Pomposer\Console;

class PackageStore
{
    protected string $storePath;

    public function __construct(?string $basePath = null)
    {
        $this->storePath = $basePath ?? $_SERVER['HOME'] . '/.pomposer-store';
    }

    public function getPackagePath(string $vendor, string $package, string $version): string
    {
        return "{$this->storePath}/{$vendor}/{$package}/{$version}";
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