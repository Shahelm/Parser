<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 1:12
 */
namespace Helper;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Class Container
 *
 * @package Helper
 */
class Container extends Helper
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct()
    {
        $container = new ContainerBuilder();
        $container->setParameter('ROOT_PATH', \ROOT_PATH);
        
        $loader = new YamlFileLoader($container, new FileLocator(\ROOT_PATH . DIRECTORY_SEPARATOR . 'configs'));

        $loader->load('services.yml');
        $container->compile();
        
        $this->container = $container;
    }
    
    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     *
     * @api
     */
    public function getName()
    {
        return self::class;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer()
    {
        return $this->container;
    }
}
