<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 0:32
 */
namespace ConsoleCommands\Autoplicity;

use ConsoleCommands\Exceptions\NotValidInputData;
use Exceptions\ApplicationException;
use Exceptions\ContainerException;
use GuzzleHttp\Exception\TransferException;
use Helper\CSV;
use Helper\Resource;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ProductUrlCollector
 *
 * @package ConsoleCommands
 */
class ProductUrlCollector extends AbstractAutoplicity
{
    use Resource;
    
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
     * @var int
     */
    private $page;
    
    /**
     * @throws \InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName($this->getParserName() . ':' . self::COMMAND_NAME)
            ->setDescription('Gather a list of urls products page from pages product list.')
            ->addArgument(
                self::BRAND_PAGE,
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
     * @throws NotValidInputData
     * @throws ApplicationException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        
        $this->brandPageUrl = $input->getArgument(self::BRAND_PAGE);
        $this->page = $input->getOption(self::PRODUCT_LIST_PAGE);
        
        $this->pathToProductLinks = \Helper\Path\get_path_to_product_urls_dir(
            $this->getParserName(),
            $this->brandPageUrl
        );
        
        $this->fs->createDirIfNotExist($this->pathToProductLinks);

        $this->productUrlsFilePath = \Helper\Path\get_path_to_product_urls($this->getParserName(), $this->brandPageUrl);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws ApplicationException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            /**
             * @var int $timeOut
             */
            $timeOut = $this->container->getParameter('autoplicity.urlCollector.timeout');
        } catch (\InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }
        
        $handle = $this->openResource($this->productUrlsFilePath, 'ab');

        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('Url: %message% %current% [%bar%] %elapsed:6s% %memory:6s%');
        $progressBar->setMessage($this->brandPageUrl);
        $progressBar->start();
        
        while ($productLinks = $this->getListOfProductUrls($this->brandPageUrl, $this->page)) {
            try {
                $progressBar->advance();
            } catch (\LogicException $e) {
                $this->logger->warning($e->getMessage());
            }
            
            foreach ($productLinks as $url) {
                $isWrite = CSV::writeRow($handle, [$url]);
                
                if (false === $isWrite) {
                    $this->logger->addError('Unable to write to a file url of the product.', ['url' => $url]);
                }
            }
            
            usleep($timeOut);
            $this->page++;
        }
        
        $progressBar->finish();
        
        $this->closeResource($handle, $this->productUrlsFilePath);
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

                    $crawler = $this->newInstanceCrawler();
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
}
