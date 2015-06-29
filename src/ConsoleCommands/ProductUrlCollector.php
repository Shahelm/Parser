<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 0:32
 */
namespace ConsoleCommands;

use GuzzleHttp\Exception\TransferException;
use Helper\File;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * Class ProductUrlCollector
 *
 * @package ConsoleCommands
 */
class ProductUrlCollector extends AbstractCommand
{
    const PRODUCT_LIST_URL = 'product-list-url';
    const PRODUCT_LIST_PAGE = 'page';
    const COMMAND_NAME = 'product:url-collector';

    /**
     * @var string
     */
    private $pathToProductLinks;
    
    /**
     * @var string
     */
    private $productUrlsFilePath;

    /**
     * @throws \InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Gather a list of urls products page from pages product list.')
            ->addArgument(
                self::PRODUCT_LIST_URL,
                InputArgument::REQUIRED,
                'Link to a page with a list of products.'
            )
            ->addOption(
                self::PRODUCT_LIST_PAGE,
                null,
                InputOption::VALUE_OPTIONAL,
                'The page number at which to begin parsing url products.',
                1
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws ServiceNotFoundException
     * @throws ServiceCircularReferenceException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        
        $brandPageUrl = $input->getArgument(self::PRODUCT_LIST_URL);
        
        $this->pathToProductLinks = $this->getContainer()->getParameter('app.tmp.dir') .
            DIRECTORY_SEPARATOR . $brandPageUrl .
            DIRECTORY_SEPARATOR . $this->getContainer()->getParameter('product.urls.dir.name');
        
        $this->createDirIfNotExist($this->pathToProductLinks);
        
        $this->productUrlsFilePath = self::getPathToProductUrls($this->getContainer(), $brandPageUrl);
    }
    
    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start = microtime(true);
        
        $brandPageUrl = $input->getArgument(self::PRODUCT_LIST_URL);
        $page = $input->getOption(self::PRODUCT_LIST_PAGE);

        /**
         * @var int $timeOut
         */
        $timeOut = $this->getContainer()->getParameter('urlCollector.timeout');
        
        $handle = $this->openResource($this->productUrlsFilePath, 'ab');

        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('Url: %message% %current% [%bar%] %elapsed:6s% %memory:6s%');
        $progressBar->setMessage($brandPageUrl);
        $progressBar->start();
        
        while ($productLinks = $this->getListOfProductUrls($brandPageUrl, $page)) {
            try {
                $progressBar->advance();
            } catch (\LogicException $e) {
                $this->logger->warning($e->getMessage());
            }
            
            foreach ($productLinks as $url) {
                $isWrite = File::writeCsvRow($handle, [$url]);
                
                if (false === $isWrite) {
                    $this->logger->addError('Unable to write to a file url of the product.', ['url' => $url]);
                }
            }
            
            usleep($timeOut);
            $page++;
        }
        
        $progressBar->finish();
        
        $this->closeResource($handle, $this->productUrlsFilePath);
        
        $output->writeln('');
        $this->logger->info(
            'finish',
            [
                'url'  => $brandPageUrl,
                'page' => $page,
                'time' => round((microtime(true) - $start), 2),
            ]
        );
    }

    /**
     * @param string $url
     * @param int $page
     *
     * @return array
     */
    protected function getListOfProductUrls($url, $page)
    {
        $productLinks = [];
        
        $context = ['url' => $url];
        
        try {
            $query = ['query' => ['PFC.PageNumber' => $page]];
            
            /**
             * @var \GuzzleHttp\Psr7\Response $response
             */
            $response = $this->client->get($url, $query);

            if (200 === $response->getStatusCode()) {
                /**
                 * @var \GuzzleHttp\Psr7\Stream $body
                 */
                $body = $response->getBody();

                try {
                    $bodyAsString = $body->getContents();

                    $crawler = $this->getCrawler();
                    $crawler->addContent($bodyAsString);

                    $crawler = $crawler->filter('div#productsList div.product-item div.picture a');

                    /**
                     * @var \DOMElement $node
                     */
                    foreach ($crawler as $node) {
                        $productLinks[] = $node->getAttribute('href');
                    }
                } catch (\RuntimeException $e) {
                    $context['message'] = $e->getMessage();
                    $context['line'] = $e->getLine();
                    $this->logger->alert('Unable to get page content!', $context);
                }
            } else {
                $context['status-code'] = $response->getStatusCode();
                $this->logger->alert('Unable to get page content!', $context);
            }
        } catch (TransferException $e) {
            $context['message'] = $e->getMessage();
            $context['line'] = $e->getLine();

            $this->logger->alert('Unable to get page content!', $context);
        }
        
        return $productLinks;
    }

    /**
     * @param ContainerInterface $container
     * @param string $brandPageUrl
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function getPathToProductUrls($container, $brandPageUrl)
    {
        $pathToProductUrls = $container->getParameter('app.tmp.dir') .
            DIRECTORY_SEPARATOR . $brandPageUrl .
            DIRECTORY_SEPARATOR . $container->getParameter('product.urls.dir.name') .
            DIRECTORY_SEPARATOR . 'urls.csv';
        
        return $pathToProductUrls;
    }
}
