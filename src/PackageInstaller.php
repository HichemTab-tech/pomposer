<?php

namespace HichemTabTech\Pomposer\Console;

use RuntimeException;
use Symfony\Component\Process\Process;

class PackageInstaller
{
    public function __construct(
        protected PackageStore $store
    ) {
        $this->store->ensureStoreExists();
    }

    public function install(array $package): void
    {
        [$vendor, $name] = explode('/', $package['name']);
        $version = $package['version'];
        $path = $this->store->getPackagePath($vendor, $name, $version);

        if (is_dir($path)) {
            echo "✅ Package {$package['name']} already exists in cache.\n";
            return;
        }

        echo "⬇️  Downloading {$package['name']} ({$version})...\n";

        if ($package['source']['type'] === 'git') {
            $this->downloadFromGit($package['source']['url'], $package['source']['reference'], $path);
        } else {
            echo "⚠️  Unsupported source type: {$package['source']['type']}\n";
        }
    }

    protected function downloadFromGit(string $url, string $reference, string $targetDir): void
    {
        $process = new Process([
            'git', 'clone', '--depth=1', '--branch', $reference, $url, $targetDir
        ]);

        $process->setTimeout(300);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        if (!$process->isSuccessful()) {
            throw new RuntimeException("Failed to clone $url");
        }
    }
}