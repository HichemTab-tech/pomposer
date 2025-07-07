<?php

namespace HichemTabTech\Pomposer;

use RuntimeException;

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
        if (file_exists($this->lockPath)) {
            $lock = json_decode(file_get_contents($this->lockPath), true);
            return $lock['packages'] ?? [];
        }

        if (!file_exists($this->composerPath)) {
            throw new RuntimeException("composer.json not found.");
        }

        $composer = json_decode(file_get_contents($this->composerPath), true);
        $requires = $composer['require'] ?? [];

        foreach ($requires as $name => $constraint) {
            if ($name === 'php') continue;
            $this->resolve($name, $constraint);
        }

        return array_values($this->resolved);
    }

    protected function resolve(string $package, string $constraint): void
    {
        if (isset($this->resolved[$package])) {
            return;
        }

        echo "🔍 Resolving $package ($constraint)...\n";

        $metadata = $this->packagist->getPackageMetadata($package);
        $bestVersion = $this->pickBestVersion($metadata, $constraint);

        if (!$bestVersion) {
            throw new RuntimeException("Could not resolve version for $package");
        }

        $this->resolved[$package] = [
            'name' => $package,
            'version' => $bestVersion['version'],
            'source' => $bestVersion['source'] ?? [],
            'dist' => $bestVersion['dist'] ?? [],
        ];

        foreach ($bestVersion['require'] ?? [] as $dep => $depConstraint) {
            if (!str_contains($dep, '/')) continue; // skip ext-* or php
            $this->resolve($dep, $depConstraint);
        }
    }

    protected function pickBestVersion(array $versions, string $constraint): ?array
    {
        // Naive: pick the first stable that matches (I can improve later)
        foreach ($versions as $v) {
            if (str_starts_with($v['version'], 'dev')) continue;
            if (version_compare($v['version_normalized'], $constraint, '==') || $v['version'] === $constraint) {
                return $v;
            }
        }

        // Fallback to the first stable
        foreach ($versions as $v) {
            if (!str_starts_with($v['version'], 'dev')) {
                return $v;
            }
        }

        return $versions[0] ?? null;
    }
}