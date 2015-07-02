<?php
use Symfony\Component\Console\Application;

require_once 'bootstrap.php';

$containerHelper = new \Helper\Container();

$container = $containerHelper->getContainer();

/**
 * @var Application $app
 */
$app = $container->get('app');

$app->getHelperSet()->set($containerHelper);
    
$pathToCommands = ROOT_PATH . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'ConsoleCommands';

$directoryIterator = new \RecursiveDirectoryIterator($pathToCommands, \FilesystemIterator::SKIP_DOTS);
$iterator = new \RecursiveIteratorIterator($directoryIterator, \RecursiveIteratorIterator::CHILD_FIRST);

$commandNameSpace = '\\ConsoleCommands';

$commands = [];

/**
 * @var \SplFileInfo $fileInfo
 */
foreach ($iterator as $fileInfo) {
    $pathParts = explode(DIRECTORY_SEPARATOR, $fileInfo->getPathname());

    $command = $commandNameSpace;

    $isNamespacePart = false;

    foreach ($pathParts as $part) {
        if (false === $isNamespacePart && 'ConsoleCommands' === $part) {
            $isNamespacePart = true;
        } elseif (false === $isNamespacePart) {
            continue;
        } else {
            $command .= "\\{$part}";
        }
    }

    $commands[] = str_replace('.php', '', $command);
}

foreach ($commands as $command) {
    if (class_exists($command)) {
        $class = new \ReflectionClass($command);
        
        if ($class->isInstantiable() && $class->isSubclassOf('\\Symfony\\Component\\Console\\Command\\Command')) {
            $app->add(new $command);
        }
    }
}

$app->run();
