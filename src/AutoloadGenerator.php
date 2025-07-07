<?php

namespace HichemTabTech\Pomposer;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AutoloadGenerator
{
    protected string $vendorDir;

    public function __construct(string $vendorDir = 'vendor')
    {
        $this->vendorDir = rtrim($vendorDir, '/');
    }

    public function generate(array $packages, array $composerJson): void
    {
        $psr4 = [];
        $classmap = [];
        $files = [];

        $this->extractFromComposerJson(
            $composerJson,
            $psr4,
            $classmap,
            $files,
            "composer.json"
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
                $classmap,
                $files,
                $path
            );
        }

        $this->writeFile("$this->vendorDir/composer/autoload_classmap.php", $this->buildClassmap($classmap));
        $this->writeFile("$this->vendorDir/composer/autoload_files.php", $this->buildFilesAutoload($files));
        $this->writeFile("$this->vendorDir/composer/autoload_psr4.php", $this->buildPsr4($psr4));
        $this->writeFile("$this->vendorDir/autoload.php", $this->buildMainAutoload());
    }

    private function extractFromComposerJson(
        array $composerData,
        array &$psr4,
        array &$classmap,
        array &$files,
        string $path
    ): void
    {
        if (isset($composerData['autoload']['psr-4'])) {
            foreach ($composerData['autoload']['psr-4'] as $namespace => $relPath) {
                if (is_array($relPath)) {
                    // Handle multiple paths for the same namespace
                    foreach ($relPath as $subPath) {
                        $absPath = $path . '/' . $subPath;
                        $psr4[$namespace][] = $absPath;
                    }
                    continue;
                }
                $absPath = $path . '/' . $relPath;
                $psr4[$namespace][] = $absPath;
            }
        }

        if (isset($composerData['autoload']['classmap'])) {
            foreach ($composerData['autoload']['classmap'] as $relPath) {
                $absPath = $path . '/' . $relPath;
                $classmap[] = $absPath;
            }
        }

        if (isset($composerData['autoload']['files'])) {
            foreach ($composerData['autoload']['files'] as $relPath) {
                $absPath = $path . '/' . $relPath;
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

// autoload_psr4.php

return $export;

PHP;
    }

    protected function buildClassmap(array $paths): string
    {
        $map = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $class = $this->extractClassNameFromFile($file->getRealPath());
                if ($class) {
                    $map[$class] = $file->getRealPath();
                }
            }
        }
        $export = var_export($map, true);

        return <<<PHP
<?php

// autoload_classmap.php

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

// vendor/autoload.php

// PSR-4 autoloading
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

// Classmap autoloading
\$classmap = require __DIR__ . '/composer/autoload_classmap.php';
spl_autoload_register(function (\$class) use (\$classmap) {
    if (isset(\$classmap[\$class])) {
        require \$classmap[\$class];
    }
});

// Load global include files (helpers, etc.)
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
}