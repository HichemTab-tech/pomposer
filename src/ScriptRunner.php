<?php

namespace HichemTabTech\Pomposer;

class ScriptRunner
{
    public function runScripts(array $composerJson, string $scriptKey): void
    {
        if (!isset($composerJson['scripts'][$scriptKey])) {
            return;
        }

        foreach ($composerJson['scripts'][$scriptKey] as $script) {
            if (str_starts_with($script, '@php ')) {
                $cmd = substr($script, 5);
                echo "[Pomposer] Running PHP script: php $cmd\n";
                passthru("php $cmd");
            } elseif (preg_match('/^([\\w\\\\]+)::([a-zA-Z_]\w*)$/', $script, $matches)) {
                // Static PHP handler, like Illuminate\Foundation\ComposerScripts::postAutoloadDump
                [, $class, $method] = $matches;
                echo "[Pomposer] Calling $class::$method() via separate process\n";

                // We create a small bootstrap script to execute the call in the project's context.
                $phpCode = sprintf(
                    "require_once __DIR__ . '/vendor/autoload.php'; %s::%s(null);",
                    addslashes($class), // Use addslashes to be safe
                    addslashes($method)
                );

                passthru("php -r \"$phpCode\"");

            } elseif (str_starts_with($script, '@')) {
                // Aliases like @composer or @some-custom-script
                echo "[Pomposer] Skipping alias: $script (not supported yet)\n";
            } else {
                echo "[Pomposer] Executing shell script: $script\n";
                passthru($script);
            }
        }
    }
}