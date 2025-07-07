<?php

namespace HichemTabTech\Pomposer;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class AutoloadGenerator
{
    protected string $vendorDir;
    protected string $projectRoot;

    public function __construct(string $vendorDir = 'vendor')
    {
        $this->vendorDir = rtrim($vendorDir, '/');
        $this->projectRoot = getcwd();
    }

    private function joinPaths(...$paths): string
    {
        return preg_replace('#[/\\\\]+#', DIRECTORY_SEPARATOR, implode(DIRECTORY_SEPARATOR, $paths));
    }

    public function generate(array $packages, array $composerJson): void
    {
        $psr4 = [];
        $classmapPathsToScan = [];
        $files = [];

        $this->extractFromComposerJson(
            $composerJson,
            $psr4,
            $classmapPathsToScan,
            $files,
            $this->projectRoot
        );

        foreach ($packages as $package) {
            $name = $package['name'];
            $version = $package['version'];
            [$vendor, $pkg] = explode('/', $name);

            $path = (new PackageStore())->getPackagePath($vendor, $pkg, $version);
            $composerPath = $path . '/composer.json';

            if (!file_exists($composerPath)) {
                echo "⚠️  Skipping $name ($version) - composer.json not found at $composerPath\n";
                continue;
            }

            $composerData = json_decode(file_get_contents($composerPath), true);

            $this->extractFromComposerJson(
                $composerData,
                $psr4,
                $classmapPathsToScan,
                $files,
                $path
            );
        }

        $allPathsToScanForClassmap = $classmapPathsToScan;
        foreach ($psr4 as $paths) {
            $allPathsToScanForClassmap = array_merge($allPathsToScanForClassmap, $paths);
        }
        $allPathsToScanForClassmap = array_unique($allPathsToScanForClassmap);

        $this->writeFile("$this->vendorDir/composer/autoload_classmap.php", $this->buildClassmap($allPathsToScanForClassmap));
        $this->writeFile("$this->vendorDir/composer/autoload_files.php", $this->buildFilesAutoload($files));
        $this->writeFile("$this->vendorDir/composer/autoload_psr4.php", $this->buildPsr4($psr4));
        $this->writeFile("$this->vendorDir/autoload.php", $this->buildMainAutoload());

        $this->writeFile(
            "$this->vendorDir/composer/ClassLoader.php",
            $this->getPomposerClassLoaderStub()
        );
    }

    private function extractFromComposerJson(
        array $composerData,
        array &$psr4,
        array &$classmapPaths ,
        array &$files,
        string $basePath = ""
    ): void
    {
        if (isset($composerData['autoload']['psr-4'])) {
            foreach ($composerData['autoload']['psr-4'] as $namespace => $relPaths) {
                foreach ((array) $relPaths as $relPath) {
                    $psr4[$namespace][] = $this->joinPaths($basePath, $relPath);
                }
            }
        }

        if (isset($composerData['autoload']['classmap'])) {
            foreach ($composerData['autoload']['classmap'] as $relPath) {
                $classmapPaths[] = $this->joinPaths($basePath, $relPath);
            }
        }

        if (isset($composerData['autoload']['files'])) {
            foreach ($composerData['autoload']['files'] as $relPath) {
                $absPath = $this->joinPaths($basePath, $relPath);
                if (file_exists($absPath)) {
                    $files[] = $absPath;
                }
            }
        }
    }

    protected function writeFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $content);
    }

    protected function buildPsr4(array $map): string
    {
        $export = var_export($map, true);

        return <<<PHP
<?php

return $export;

PHP;
    }

    protected function buildClassmap(array $paths): string
    {
        $map = [];

        foreach ($paths as $path) {
            $realPath = realpath($path);

            if ($realPath === false) {
                continue;
            }

            if (is_file($realPath)) {
                $class = $this->extractClassNameFromFile($realPath);
                if ($class) {
                    $map[$class] = $realPath;
                }
                continue;
            }

            if (is_dir($realPath)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($iterator as $file) {
                    if ($file->getExtension() !== 'php' || $file->isDir()) {
                        continue;
                    }

                    $filePath = $file->getRealPath();
                    $class = $this->extractClassNameFromFile($filePath);
                    if ($class) {
                        $map[$class] = $filePath;
                    }
                }
            }
        }

        $map['Composer\\Autoload\\ClassLoader'] = $this->joinPaths($this->projectRoot, $this->vendorDir, 'composer', 'ClassLoader.php');

        ksort($map);
        $export = var_export($map, true);

        return <<<PHP
<?php

\$baseDir = dirname(dirname(__DIR__));

return $export;

PHP;
    }

    protected function buildFilesAutoload(array $files): string
    {
        $entries = '';
        foreach ($files as $file) {
            $escaped = var_export($file, true);
            $id = md5($file);
            $entries .= "    \$require('$id', $escaped);\n";
        }

        return <<<PHP
<?php

\$GLOBALS['__composer_autoload_files'] = \$GLOBALS['__composer_autoload_files'] ?? [];

\$require = \\Closure::bind(static function (\$fileIdentifier, \$file) {
    if (empty(\$GLOBALS['__composer_autoload_files'][\$fileIdentifier])) {
        \$GLOBALS['__composer_autoload_files'][\$fileIdentifier] = true;
        require \$file;
    }
}, null, null);

$entries

PHP;
    }

    protected function buildMainAutoload(): string
    {
        return <<<PHP
<?php

\$psr4 = require __DIR__ . '/composer/autoload_psr4.php';
foreach (\$psr4 as \$namespace => \$dirs) {
    foreach ((array) \$dirs as \$dir) {
        spl_autoload_register(function (\$class) use (\$namespace, \$dir) {
            if (str_starts_with(\$class, \$namespace)) {
                \$path = \$dir . '/' . str_replace('\\\\', '/', substr(\$class, strlen(\$namespace))) . '.php';
                if (file_exists(\$path)) {
                    require \$path;
                }
            }
        });
    }
}

\$classmap = require __DIR__ . '/composer/autoload_classmap.php';
spl_autoload_register(function (\$class) use (\$classmap) {
    if (isset(\$classmap[\$class])) {
        require \$classmap[\$class];
    }
});

require __DIR__ . '/composer/autoload_files.php';

PHP;
    }

    protected function extractClassNameFromFile(string $file): ?string
    {
        $contents = file_get_contents($file);

        // Match namespace
        $namespace = '';
        if (preg_match('/namespace\s+([^;]+);/', $contents, $nsMatch)) {
            $namespace = trim($nsMatch[1]) . '\\';
        }

        // Match class/interface/trait/enum
        if (preg_match('/^\s*(?:\b(?:final|abstract|readonly)\b\s+)*\b(class|interface|trait|enum)\b\s+([a-zA-Z0-9_]+)/mi', $contents, $classMatch)) {
            return $namespace . $classMatch[2];
        }

        return null;
    }

    protected function getPomposerClassLoaderStub(): string
    {
        $stubPath = __DIR__ . '/stubs/ClassLoader.php.stub';

        if (!file_exists($stubPath)) {
            throw new RuntimeException("ClassLoader stub file not found at $stubPath");
        }

        return file_get_contents($stubPath);
    }

    /**
     * Generates all necessary package manifest files for full compatibility.
     * This includes installed.json, installed.php, and InstalledVersions.php.
     *
     * @param array $packages The list of all installed packages.
     * @param array $rootComposerJson The content of the root composer.json.
     */
    public function generatePackageManifests(array $packages, array $rootComposerJson): void
    {
        $manifestJson = [
            'packages' => $packages,
            'dev' => true,
            'dev-package-names' => [],
        ];
        $jsonContent = json_encode($manifestJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->writeFile($this->joinPaths($this->vendorDir, 'composer', 'installed.json'), $jsonContent);

        $rootPackageName = $rootComposerJson['name'] ?? '__root__';
        $installedPhp = [
            'root' => [
                'name' => $rootPackageName,
                'version' => $rootComposerJson['version'] ?? 'dev-main',
                'pretty_version' => $rootComposerJson['version'] ?? 'dev-main',
                'type' => 'project',
                'install_path' => $this->projectRoot,
                'aliases' => [],
                'reference' => null,
            ],
            'versions' => [],
        ];

        foreach ($packages as $package) {
            $packageName = $package['name'];

            $fakeInstallPath = $this->joinPaths($this->vendorDir, $packageName);

            $installedPhp['versions'][$packageName] = [
                'pretty_version' => $package['version'],
                'version' => $package['version_normalized'] ?? $package['version'],
                'type' => $package['type'] ?? 'library',
                'install_path' => $fakeInstallPath, // Use the calculated fake path
                'aliases' => $package['aliases'] ?? [],
                'reference' => $package['source']['reference'] ?? $package['dist']['reference'] ?? null,
            ];
        }

        $installedPhp['versions'][$rootPackageName] = $installedPhp['root'];

        $phpContent = '<?php return ' . var_export($installedPhp, true) . ';';
        $this->writeFile($this->joinPaths($this->vendorDir, 'composer', 'installed.php'), $phpContent);

        $this->writeFile(
            $this->joinPaths($this->vendorDir, 'composer', 'InstalledVersions.php'),
            $this->getInstalledVersionsStub()
        );
    }

    /**
     * Helper to load the InstalledVersions stub file.
     */
    protected function getInstalledVersionsStub(): string
    {
        $stubPath = __DIR__ . '/stubs/InstalledVersions.php.stub';
        if (!file_exists($stubPath)) {
            throw new RuntimeException("InstalledVersions stub not found at $stubPath");
        }
        return file_get_contents($stubPath);
    }
}