<?php

namespace HichemTabTech\Pomposer\Console\Concerns;

use Illuminate\Support\ProcessUtils;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use function Illuminate\Support\php_binary;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

trait CommandsUtils
{

    public string $display = '';

    /**
     * Get the path to the appropriate PHP binary.
     *
     * @return string
     */
    protected function phpBinary(): string
    {
        $phpBinary = function_exists('Illuminate\Support\php_binary')
            ? php_binary()
            : (new PhpExecutableFinder)->find(false);

        return $phpBinary !== false
            ? ProcessUtils::escapeArgument($phpBinary)
            : 'php';
    }

    /**
     * Run the given commands.
     *
     * @param array $commands
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param  string|null  $workingPath
     * @param  array  $env
     * @return Process
     */
    protected function runCommands(array $commands, InputInterface $input, OutputInterface $output, ?string $workingPath = null, array $env = []): Process
    {
        if (!$output->isDecorated()) {
            $commands = array_map(function ($value) {
                if (Str::startsWith($value, ['chmod', 'git', $this->phpBinary().' ./vendor/bin/pest'])) {
                    return $value;
                }

                return $value.' --no-ansi';
            }, $commands);
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                if (Str::startsWith($value, ['chmod', 'git', $this->phpBinary().' ./vendor/bin/pest'])) {
                    return $value;
                }

                return $value.' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, $env, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR AND file_exists('/dev/tty') AND is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write('    '.$line);
        });

        return $process;
    }

    /**
     * Get the installation directory.
     *
     * @param  string  $name
     * @return string
     */
    protected function getInstallationDirectory(string $name): string
    {
        return $name !== '.' ? getcwd().'/'.$name : '.';
    }

    /**
     * Verify that the application does not already exist.
     *
     * @param string $directory
     * @return void
     */
    protected function verifyApplicationDoesntExist(string $directory): void
    {
        if ((is_dir($directory) || is_file($directory)) AND $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    public function table(array $headers, array $rows): void
    {
        table($headers, $rows);
        $this->display .= json_encode([$headers, $rows]);
    }

    public function info(string $message): void
    {
        info($message);
        $this->display .= $message;
    }

    public function getDisplay(): string
    {
        return $this->display;
    }

    /**
     * Gets the user's home directory in a cross-platform way.
     *
     * @return string The path to the home directory.
     * @throws RuntimeException If the home directory cannot be determined.
     */
    function getUserHomeDirectory(): string
    {
        // Check for the 'HOME' environment variable (common on Linux/macOS)
        if (getenv('HOME')) {
            return rtrim(getenv('HOME'), '/\\');
        }

        // Check for Windows-specific environment variables
        if (getenv('HOMEDRIVE') && getenv('HOMEPATH')) {
            return rtrim(getenv('HOMEDRIVE') . getenv('HOMEPATH'), '/\\');
        }

        // Fallback for other Windows environments
        if (getenv('USERPROFILE')) {
            return rtrim(getenv('USERPROFILE'), '/\\');
        }

        throw new RuntimeException('Could not determine the user home directory.');
    }

    protected function removeRecursive(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            array_map([$this, 'removeRecursive'], glob($path . '/*', GLOB_MARK));
            rmdir($path);
        }
    }
}