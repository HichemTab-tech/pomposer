<?php

namespace HichemTabTech\Pomposer;

use HichemTabTech\Pomposer\Concerns\CommandsUtils;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use ZipArchive;

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

        echo "⬇️  Downloading {$package['name']} ($version) via dist...\n";

        if (!isset($package['dist']['url'])) {
            throw new RuntimeException("Could not find ZIP dist for {$package['name']}@$version");
        }

        $zipUrl = $package['dist']['url'];

        $this->downloadAndExtractZip($zipUrl, $path);

        echo "📦 Stored in $path\n";
    }

    protected function downloadAndExtractZip(string $url, string $targetPath): void
    {
        $fs = new Filesystem();

        $options = [
            'http' => [
                'header' => "User-Agent: HichemTabTech/Pomposer/1.0\r\n"
            ]
        ];
        $context = stream_context_create($options);

        $tmpZip = tempnam(sys_get_temp_dir(), 'pomposer_zip_') . '.zip';
        file_put_contents($tmpZip, file_get_contents($url, false, $context));

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