<?php

namespace HichemTabTech\Pomposer;

use RuntimeException;
use Composer\Semver\Semver;

class LockParser
{
    protected string $composerPath;
    protected string $lockPath;

    protected array $resolved = [];
    protected PackagistGateway $packagist;

    public function __construct(PackagistGateway $packagist, string $composerPath = 'composer.json', string $lockPath = 'composer.lock')
    {
        $this->packagist = $packagist;
        $this->composerPath = $composerPath;
        $this->lockPath = $lockPath;
    }

    public function getPackages(): array
    {
        $composer = json_decode(file_get_contents($this->composerPath), true);

        if (file_exists($this->lockPath)) {
            $lock = json_decode(file_get_contents($this->lockPath), true);
            $packages = $lock['packages'] ?? [];
            $packagesDev = $lock['packages-dev'] ?? [];
            return [$composer, array_merge($packages, $packagesDev)];
        }

        if (!file_exists($this->composerPath)) {
            throw new RuntimeException("composer.json not found.");
        }

        $requires = $composer['require'] ?? [];
        $requiresDev = $composer['require-dev'] ?? [];

        foreach ($requires as $name => $constraint) {
            if ($name === 'php') continue;
            $this->resolve($name, $constraint);
        }

        foreach ($requiresDev as $name => $constraint) {
            if ($name === 'php') continue;
            $this->resolve($name, $constraint);
        }

        return [$composer, array_values($this->resolved)];
    }

    protected function resolve(string $package, string $constraint): void
    {
        if (isset($this->resolved[$package])) {
            return;
        }

        echo "🔍 Resolving $package ($constraint)...\n";

        $metadata = $this->packagist->getPackageMetadata($package);
        $bestVersion = $this->pickBestVersion($metadata, $constraint);
        echo "    ➡️  Best version found: {$bestVersion['version']}\n";

        if (!$bestVersion) {
            throw new RuntimeException("Could not resolve version for $package");
        }

        $this->resolved[$package] = [
            'name' => $package,
            'version' => $bestVersion['version'],
            'source' => $bestVersion['source'] ?? [],
            'dist' => $bestVersion['dist'] ?? [],
            ...$bestVersion,
        ];

        foreach ($bestVersion['require'] ?? [] as $dep => $depConstraint) {
            if (!str_contains($dep, '/')) continue; // skip ext-* or php
            $this->resolve($dep, $depConstraint);
        }
    }

    protected function pickBestVersion(array $versions, string $constraint): ?array
    {
        $currentPhpVersion = phpversion();
        foreach ($versions as $v) {
            if (str_starts_with($v['version'], 'dev')) {
                continue;
            }

            $version = $v['version_normalized'] ?? $v['version'];

            // If the package requires PHP, check compatibility
            if (isset($requires['php']) && !Semver::satisfies($currentPhpVersion, $requires['php'])) {
                continue;
            }

            // Check if this version satisfies the constraint
            if (Semver::satisfies($version, $constraint)) {
                echo "    ➡️  Version {$v['version']} satisfies constraint $constraint\n";
                return $v;
            }
        }

        // Fallback to the first stable
        foreach ($versions as $v) {
            if (!str_starts_with($v['version'], 'dev')) {
                echo "    ➡️  Fallback to first stable version: {$v['version']}\n";
                return $v;
            }
        }

        return $versions[0] ?? null;
    }
}