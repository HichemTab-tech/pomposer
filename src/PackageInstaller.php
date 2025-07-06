<?php

namespace HichemTabTech\Pomposer\Console;

use HichemTabTech\Pomposer\Console\Concerns\CommandsUtils;
use Symfony\Component\Process\Process;

class PackageInstaller
{
    use CommandsUtils;

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

        echo "⬇️  Downloading {$package['name']} ($version)...\n";

        if ($package['source']['type'] === 'git') {
            $this->installWithComposer($vendor, $name, $version, $path);
        } else {
            echo "⚠️  Unsupported source type: {$package['source']['type']}\n";
        }
    }

    protected function installWithComposer(string $vendor, string $name, string $version, string $targetPath): void
    {
        $tempDir = sys_get_temp_dir() . "/pomposer_{$vendor}_{$name}_{$version}_" . uniqid();
        mkdir($tempDir, 0755, true);

        file_put_contents("$tempDir/composer.json", json_encode([
            'require' => [
                "$vendor/$name" => $version
            ],
            'config' => [
                'vendor-dir' => "$tempDir/vendor"
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $process = new Process(['composer', 'install', '--no-dev', '--no-interaction'], $tempDir);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) {
            echo $buffer;
        });

        // Extract only the target package
        $installedPath = "$tempDir/vendor/$vendor/$name";
        if (is_dir($installedPath)) {
            mkdir(dirname($targetPath), 0755, true);
            rename($installedPath, $targetPath);
        }

        // Cleanup
        $this->removeRecursive($tempDir);
    }
}