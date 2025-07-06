<?php

namespace HichemTabTech\Pomposer\Console;

use HichemTabTech\Pomposer\Console\Concerns\CommandsUtils;

class VendorLinker
{
    use CommandsUtils;

    protected string $vendorDir;

    public function __construct(string $vendorDir = 'vendor')
    {
        $this->vendorDir = rtrim($vendorDir, '/');
    }

    public function link(array $packages): void
    {
        foreach ($packages as $package) {
            [$vendor, $name] = explode('/', $package['name']);
            $version = $package['version'];

            $storePath = (new PackageStore())->getPackagePath($vendor, $name, $version);
            $linkPath = "{$this->vendorDir}/{$vendor}/{$name}";

            if (!is_dir($storePath)) {
                echo "❌ Package not found in store: {$vendor}/{$name}@{$version}\n";
                continue;
            }

            $this->ensureDirectoryExists(dirname($linkPath));

            // Remove existing if any (in case of rebuild)
            if (file_exists($linkPath)) {
                $this->removeRecursive($linkPath);
            }

            // Use symlink (Windows fallback to copy)
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $this->copyRecursive($storePath, $linkPath);
            } else {
                symlink($storePath, $linkPath);
            }

            echo "🔗 Linked {$vendor}/{$name} → {$linkPath}\n";
        }
    }

    protected function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    protected function copyRecursive(string $source, string $dest): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $dest . DIRECTORY_SEPARATOR . $iterator->getInnerIterator()->getSubPathName();
            if ($item->isDir()) {
                mkdir($target, 0755, true);
            } else {
                copy($item, $target);
            }
        }
    }
}