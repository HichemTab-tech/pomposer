<?php

namespace HichemTabTech\Pomposer\Console;

class AutoloadGenerator
{
    protected string $vendorDir;

    public function __construct(string $vendorDir = 'vendor')
    {
        $this->vendorDir = rtrim($vendorDir, '/');
    }

    public function generate(array $packages): void
    {
        $psr4 = [];
        $classmap = [];

        foreach ($packages as $package) {
            $name = $package['name'];
            $version = $package['version'];
            [$vendor, $pkg] = explode('/', $name);

            $path = (new PackageStore())->getPackagePath($vendor, $pkg, $version);
            $composerPath = $path . '/composer.json';

            if (!file_exists($composerPath)) {
                continue;
            }

            $composerData = json_decode(file_get_contents($composerPath), true);

            if (isset($composerData['autoload']['psr-4'])) {
                foreach ($composerData['autoload']['psr-4'] as $namespace => $relPath) {
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
        }

        $this->writeFile("{$this->vendorDir}/composer/autoload_psr4.php", $this->buildPsr4($psr4));
        $this->writeFile("{$this->vendorDir}/composer/autoload_classmap.php", $this->buildClassmap($classmap));
        $this->writeFile("{$this->vendorDir}/autoload.php", $this->buildMainAutoload());
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

return {$export};

PHP;
    }

    protected function buildClassmap(array $paths): string
    {
        // In real use: recursively scan these paths for .php classes
        // Here we fake an empty classmap

        $map = []; // @todo: implement scanning later
        $export = var_export($map, true);

        return <<<PHP
<?php

// autoload_classmap.php

return {$export};

PHP;
    }

    protected function buildMainAutoload(): string
    {
        return <<<PHP
<?php

// vendor/autoload.php

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

// classmap not implemented yet

PHP;
    }
}