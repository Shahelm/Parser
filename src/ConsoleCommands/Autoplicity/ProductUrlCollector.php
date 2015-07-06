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
    protected $pathToProductLinks;
    
    /**
     * @var string
     */
    protected $productUrlsFilePath;

    /**
     * @var string
     */
    protected $projectName;

    /**
     * @var int
     */
    protected $page;

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
                self::PROJECT_NAME,
                null,
                InputOption::VALUE_OPTIONAL,
                'Unique identifier across all brand-page'
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
        $this->projectName = $input->getOption(self::PROJECT_NAME);
        
        $this->pathToProductLinks = \Helper\Path\get_path_to_product_urls_dir(
            $this->getParserName(),
            $this->getProjectName()
        );
        
        $this->fs->createDirIfNotExist($this->pathToProductLinks);

        $this->productUrlsFilePath = \Helper\Path\get_path_to_product_urls(
            $this->getParserName(),
            $this->getProjectName()
        );
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
            $timeOutKey = $this->getParserName() . '.' . 'urlCollector.timeout';
            $timeOut = 100000;
            
            if ($this->container->hasParameter($timeOutKey)) {
                $timeOut = $this->container->getParameter($timeOutKey);
            }
        } catch (\InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }
        
        $handle = $this->openResource($this->productUrlsFilePath, 'ab');

        $progressBar = $this->initProgressBar($output);
        $progressBar->start();
        
        $numbersOfPage = $this->getNumbersOfPage();
        
        try {
            $progressBar->setProgress($this->page);
        } catch (\LogicException $e) {
            /*NOP*/
        }
        
        while ($productLinks = $this->getListOfProductUrls($this->brandPageUrl, $this->page)) {
            foreach ($productLinks as $url) {
                $isWrite = CSV::writeRow($handle, [$url]);
                
                if (false === $isWrite) {
                    $this->logger->addError('Unable to write to a file url of the product.', ['url' => $url]);
                }
            }
            
            usleep($timeOut);
            $this->page++;

            /**
             * For the case when the process is determined by stopping the last page.
             */
            if ($numbersOfPage > 0 && $this->page > $numbersOfPage) {
                break;
            }
            
            try {
                $progressBar->advance();
            } catch (\LogicException $e) {
                $this->logger->warning($e->getMessage());
            }
        }
        
        $progressBar->finish();
        
        $this->closeResource($handle, $this->productUrlsFilePath);
    }

    /**
     * 0 - means that the last page, you can define only brute force.
     *
     * @return int
     */
    protected function getNumbersOfPage()
    {
        return 0;
    }

    /**
     * @param string $url
     * @param int $page
     *
     * @return array
     */
    protected function getListOfProductUrls($url, $page)
    {
        $productUrls = [];
        
        $context = ['url' => $url];
        
        try {
            $query = $this->getQuery($url, $page);

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

                    foreach ($this->getProductUrls($bodyAsString, $productUrls) as $productUrl) {
                        $productUrls[] = $productUrl;
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
        
        return $productUrls;
    }

    /**
     * @param $page
     *
     * @return array
     */
    protected function getQuery($url, $page)
    {
        $query = ['query' => ['PFC.PageNumber' => $page]];

        return $query;
    }

    /**
     * @param string $bodyAsString
     *
     * @return array
     *
     * @throws ApplicationException
     * @throws \RuntimeException
     */
    protected function getProductUrls($bodyAsString)
    {
        $productUrls = [];
        
        $crawler = $this->newInstanceCrawler();
        $crawler->addContent($bodyAsString);

        $crawler = $crawler->filter('div#productsList div.product-item div.picture a');

        /**
         * @var \DOMElement $node
         */
        foreach ($crawler as $node) {
            $productUrls[] = $node->getAttribute('href');
        }

        return $productUrls;
    }

    /**
     * @return string
     */
    protected function getProjectName()
    {
        return null === $this->projectName ? $this->brandPageUrl : $this->projectName;
    }

    /**
     * @param OutputInterface $output
     *
     * @return ProgressBar
     */
    protected function initProgressBar(OutputInterface $output)
    {
        $numbersOfPage = $this->getNumbersOfPage();
        
        $format = 'ID: %message% %current% [%bar%] %elapsed:6s% %memory:6s%';
        
        if ($numbersOfPage > 0) {
            $format = 'ID: %message% %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%';
        }
        
        $progressBar = new ProgressBar($output, $numbersOfPage);
        $progressBar->setFormat($format);
        $progressBar->setMessage($this->getProjectName());

        return $progressBar;
    }
}
