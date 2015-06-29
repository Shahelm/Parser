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

$dir = new DirectoryIterator($pathToCommands);

$commandNameSpace = '\\ConsoleCommands\\';

$commands = [];

/**
 * @var \DirectoryIterator $fileInfo
 */
foreach ($dir as $iterator) {
    if ($iterator->isFile()) {
        $commands[] = $commandNameSpace . $iterator->getBasename('.php');
    }
}

foreach ($commands as $command) {
    if (class_exists($command)) {
        $class = new \ReflectionClass($command);
        
        if ($class->isInstantiable()) {
            $app->add(new $command);
        }
    }
}

$app->run();
