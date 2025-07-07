<?php

namespace HichemTabTech\Pomposer\Console;

use Illuminate\Support\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InstallCommand extends Command
{
    use Concerns\ConfiguresPrompts;
    use Concerns\CommandsUtils;

    /**
     * The Composer instance.
     *
     * @var Composer
     */
    protected Composer $composer;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->setName('install')
            ->setDescription('install composer packages');
    }

    /**
     * Interact with the user before validating the input.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $this->configurePrompts($input, $output);

        $output->write(PHP_EOL.'  <fg=red>
         _____                                          
        |  __ \                                         
        | |__) |__  _ __ ___  _ __   ___  ___  ___ _ __ 
        |  ___/ _ \| \'_ ` _ \| \'_ \ / _ \/ __|/ _ \ \'__|
        | |  | (_) | | | | | | |_) | (_) \__ \  __/ |
        |_|   \___/|_| |_| |_| .__/ \___/|___/\___|_|
                             | |
                             |_|                                                                            
        </>'.PHP_EOL.PHP_EOL);

        $output->writeln('<fg=yellow>⚠️ Pomposer is in beta. Use at your own risk! Not for production use. It was built just as a proof of concept for now.</>');

        $output->writeln('<fg=blue>💡 Interested in helping Pomposer grow?</>');
        $output->writeln('<fg=blue>👉 If you have ideas, experience, or just curiosity — please join the discussion and contribute at: https://github.com/HichemTab-tech/pomposer/discussions/4r</>');
        $output->writeln('<fg=cyan>https://github.com/HichemTab-tech/pomposer</>' . PHP_EOL);

    }

    /**
     * Execute the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->configurePrompts($input, $output);

        $output->writeln('<info>🔍 Reading composer.lock...</info>');

        $parser = new LockParser();
        $packages = $parser->getPackages();

        $store = new PackageStore();
        $installer = new PackageInstaller($store);

        foreach ($packages as $pkg) {
            $installer->install($pkg);
        }

        $output->writeln('<info>⚙️ Generating autoload files...</info>');

        (new AutoloadGenerator())->generate($packages);

        $output->writeln('<info>✅ Pomposer install complete!</info>');

        return Command::SUCCESS;
    }
}
