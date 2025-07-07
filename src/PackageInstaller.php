<?php

namespace HichemTabTech\Pomposer;

use HichemTabTech\Pomposer\Concerns\CommandsUtils;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use ZipArchive;

class PackageInstaller
{
    use CommandsUtils;

    private $context;

    public function __construct(
        protected PackageStore $store
    ) {
        $this->store->ensureStoreExists();
        $options = [
            'http' => [
                'header' => "User-Agent: My-PHP-Script/1.0\r\n"
            ]
        ];

        // Create the stream context
        $this->context = stream_context_create($options);

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

        echo "⬇️  Downloading {$package['name']} ($version) via dist...\n";

        $metadata = $this->getMetadata($vendor, $name);

        $target = $this->findVersion($metadata, $version);
        if (!$target || !isset($target['dist']['url'])) {
            throw new RuntimeException("Could not find ZIP dist for {$package['name']}@$version");
        }

        $zipUrl = $target['dist']['url'];

        $this->downloadAndExtractZip($zipUrl, $path);

        echo "📦 Stored in $path\n";
    }

    protected function getMetadata(string $vendor, string $name): array
    {
        $url = "https://repo.packagist.org/p2/$vendor/$name.json";
        $json = file_get_contents($url, context: $this->context);

        if (!$json) {
            throw new RuntimeException("Failed to fetch metadata for $vendor/$name");
        }

        return json_decode($json, true);
    }

    protected function findVersion(array $metadata, string $version): ?array
    {
        $packages = $metadata['packages'] ?? [];

        foreach ($packages as $pkgVersions) {
            foreach ($pkgVersions as $ver) {
                if ($ver['version_normalized'] === $version || $ver['version'] === $version) {
                    return $ver;
                }
            }
        }

        return null;
    }

    protected function downloadAndExtractZip(string $url, string $targetPath): void
    {
        $fs = new Filesystem();

        $tmpZip = tempnam(sys_get_temp_dir(), 'pomposer_zip_') . '.zip';
        file_put_contents($tmpZip, file_get_contents($url, context: $this->context));

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) === true) {
            $tmpExtract = sys_get_temp_dir() . '/pomposer_unpack_' . uniqid();
            $fs->makeDirectory($tmpExtract, 0755, true);

            $zip->extractTo($tmpExtract);
            $zip->close();

            $dirs = collect($fs->directories($tmpExtract));

            if ($dirs->count() !== 1) {
                throw new RuntimeException("Unexpected ZIP structure (expected one root folder).");
            }

            $extractedFolder = $dirs->first();

            // Ensure parent directories exist
            $fs->ensureDirectoryExists(dirname($targetPath));

            // Move contents safely
            $fs->move($extractedFolder, $targetPath);

            // Clean up
            $fs->delete($tmpZip);
            $fs->deleteDirectory($tmpExtract);
        } else {
            throw new RuntimeException("Failed to extract zip archive.");
        }
    }
}