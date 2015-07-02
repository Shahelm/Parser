<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 01.07.15
 * Time: 22:03
 */
namespace ConsoleCommands;

use Helper\Container;
use Helper\Filesystem;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class RecursiveDirectoryClearing
 *
 * @package ConsoleCommands
 */
class RecursiveDirectoryClearing extends Command
{
    const PATH = 'path';
    const COMMAND_NAME = 'directory:clear';

    /**
     * @throws \InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->addArgument(
                self::PATH,
                InputOption::VALUE_REQUIRED,
                'The path to the folder for cleaning.'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fs = new Filesystem();

        /**
         * @var Logger $logger
         */
        $logger = $this->getHelper(Container::class)->getContainer()->get('app.logger');
        
        $path = $input->getArgument(self::PATH);

        if ($fs->exists($path)) {
            $directoryIterator = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
            $iterator = new \RecursiveIteratorIterator($directoryIterator, \RecursiveIteratorIterator::CHILD_FIRST);

            $filesToRemove = [];
            $dirsToRemove = [];

            /**
             * @var \SplFileInfo $fileInfo
             */
            foreach ($iterator as $fileInfo) {
                if ($path !== $fileInfo->getRealPath()) {
                    if ($fileInfo->isFile()) {
                        $filesToRemove[] = $fileInfo->getRealPath();
                    } else {
                        $dirsToRemove[] = $fileInfo->getRealPath();
                    }
                }
            }

            foreach ($filesToRemove as $path) {
                $isDelete = unlink($path);
                if (false === $isDelete) {
                    $logger->warn('Failed to delete the resource.', [$path]);
                }
            }

            foreach ($dirsToRemove as $path) {
                $isDelete = rmdir($path);
                if (false === $isDelete) {
                    $logger->warn('Failed to delete the resource.', [$path]);
                }
            }
        }
    }
}
