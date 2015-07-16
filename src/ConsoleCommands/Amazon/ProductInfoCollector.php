<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 07.07.15
 * Time: 21:29
 */
namespace ConsoleCommands\Amazon;

use Entities\Image;
use Entities\ProductInfo;
use Exceptions\ApplicationException;
use Exceptions\ContainerException;
use Guzzle\Http\Message\Request;
use Helper\Console;
use Helper\CSV;
use Helper\ExecutorParallelQuery;
use Helper\ImageWriter;
use Helper\ProductInfoWriter;
use Helper\Resource;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class ProductInfoCollector
 *
 * @package ConsoleCommands\Amazon
 */
class ProductInfoCollector extends AbstractAmazon
{
    use Resource, Console;
    
    const COMMAND_NAME = ':product:info-collector';
    
    /**
     * @var int
     */
    private $csvLineNumber;

    /**
     * @var string
     */
    private $productImageInfoFilePath;
    
    /**
     * @var string
     */
    private $pathToProductUrls;
    
    /**
     * @var string
     */
    private $productInfoFilePath;
    
    /**
     * @var string
     */
    private $compatibilityChartsInfoFilePath;
    
    /**
     * @var ProgressBar
     */
    private $progressBar;
    
    /**
     * @var string
     */
    private $featureSeparator;
    
    /**
     * @var string
     */
    private $host;
    
    /**
     * @throws \InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName($this->getParserName() . self::COMMAND_NAME)
            ->addArgument(
                self::PROJECT_NAME,
                InputArgument::REQUIRED
            )
            ->addArgument(
                self::CSV_LINE_NUMBER,
                InputArgument::OPTIONAL,
                '',
                0
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \ConsoleCommands\Exceptions\NotValidInputData
     * @throws \Exceptions\ApplicationException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->csvLineNumber = $input->getArgument(self::CSV_LINE_NUMBER);
        $this->projectName   = $input->getArgument(self::PROJECT_NAME);

        try {
            $this->featureSeparator = $this->container->getParameter($this->getParserName() . '.features.separator');
            $this->host = $this->container->getParameter($this->getParserName() . '.host');
        } catch (InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }

        $this->pathToProductUrls = \Helper\Path\get_path_to_product_urls(
            $this->getParserName(),
            $this->getProjectName()
        );

        $this->fs->createDirIfNotExist(\Helper\Path\get_path_to_product_image_info_dir(
            $this->getParserName(),
            $this->getProjectName()
        ));
        
        $this->fs->createDirIfNotExist(\Helper\Path\get_path_to_product_info_dir(
            $this->getParserName(),
            $this->getProjectName()
        ));

        $this->fs->createDirIfNotExist(\Helper\Path\get_path_to_compatibility_charts_info_dir(
            $this->getParserName(),
            $this->getProjectName()
        ));
        
        $this->productImageInfoFilePath = \Helper\Path\get_path_to_product_image_info(
            $this->getParserName(),
            $this->getProjectName()
        );
        
        $this->productInfoFilePath = \Helper\Path\get_path_to_product_info(
            $this->getParserName(),
            $this->getProjectName()
        );
        
        $this->compatibilityChartsInfoFilePath = \Helper\Path\get_path_to_compatibility_charts_info(
            $this->getParserName(),
            $this->getProjectName()
        );
        
        $rows = CSV::getRowCount($this->pathToProductUrls);

        if (false === $rows) {
            $this->logger->addAlert(
                'Unable to count the number of lines in the file.',
                ['file' => $this->pathToProductUrls]
            );
            $this->exitWithError();
        }

        $this->progressBar = new ProgressBar($output, $rows);
        $this->progressBar->setFormat(
            'Id: %message% %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%'
        );

        $this->progressBar->setMessage($this->getProjectName());
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws ApplicationException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->progressBar->start();

        try {
            $this->progressBar->setProgress($this->csvLineNumber);
        } catch (\LogicException $e) {
            /*NOP*/
        }

        $csvHandle = $this->openResource($this->pathToProductUrls, 'rb');
        $columnNames = ['url'];
        $csvRowNumber = 0;

        $productUrls = [];

        $productUrlsChunkSize = 100;

        while (false !== ($row = (CSV::readRow($csvHandle, $columnNames)))) {
            if ($csvRowNumber >= $this->csvLineNumber) {
                $productUrl = $row['url'];

                $productUrls[] = $productUrl;

                if (count($productUrls) === $productUrlsChunkSize) {
                    $productsInfo = $this->getProductsInfo($productUrls);
                    $this->writeProductsInfo($productsInfo);
                    $this->writeCompatibilityInfo($productsInfo);
                    $productUrls = [];
                }
            }

            $csvRowNumber++;
        }

        if (count($productUrls) > 0) {
            $productsInfo = $this->getProductsInfo($productUrls);
            $this->writeProductsInfo($productsInfo);
            $this->writeCompatibilityInfo($productsInfo);
        }

        $this->progressBar->finish();
        $this->closeResource($csvHandle, $this->pathToProductUrls);
    }

    /**
     * @param array $productUrls
     *
     * @return ProductInfo[]
     */
    private function getProductsInfo(array $productUrls)
    {
        $productsInfo = [];

        $request = [];
        
        foreach ($productUrls as $url) {
            $request[] = $this->client->get($this->host . ltrim($url, '/'), []);
        }
        
        (new ExecutorParallelQuery($this->client, $request, 3))
            ->onSuccess(function (Request $request) use (&$productsInfo) {
                $url = $request->getUrl();
                $response = $request->getResponse();

                try {
                    $bodyAsString = $response->getBody(true);

                    $asin = $this->getAsin($bodyAsString);
                    $manufacturerPartNumber = $this->getManufacturerPartNumber($bodyAsString);
                    
                    if ($asin && $manufacturerPartNumber) {
                        $productName            = $this->getProductName($bodyAsString);
                        $brand                  = $this->getBrand($bodyAsString);
                        $productDescription     = $this->getProductDescription($bodyAsString);
                        $features               = $this->getFeatures($bodyAsString);
                        $pathOfImages           = $this->getPathOfImages($bodyAsString);

                        $images = [];

                        if (false === empty($pathOfImages)) {
                            $order = 0;

                            foreach ($pathOfImages as $imgPath) {
                                $images[] = new Image($asin, $imgPath, $order, false, $url);
                                $order++;
                            }
                        }

                        $productsInfo[] = new ProductInfo(
                            $asin,
                            $productName,
                            $brand,
                            $manufacturerPartNumber,
                            $productDescription,
                            $features,
                            $images
                        );
                    } else {
                        $this->logger->info('Unable to find ASIN or Manufacturer Part Number', ['url' => $url]);
                    }
                } catch (\Exception $e) {
                    $this->logger->addError(
                        'Unable to process the content of the page.',
                        ['url' => $url, 'message' => $e->getMessage()]
                    );
                }
            })
            ->onError(function (Request $request) {
                $this->logger->addError('Unable to process the content of the page.', ['url' => $request->getUrl()]);
            })
            ->afterProcessing(function () {
                try {
                    $this->progressBar->advance();
                } catch (\LogicException $e) {
                    /*NOP*/
                }
            })
            ->wait();

        return $productsInfo;
    }
    
    /**
     * @param string $bodyAsString
     *
     * @return string
     *
     * @throws ApplicationException
     */
    private function getAsin($bodyAsString)
    {
        $selector = 'div#prodDetails div.col2 div.techD  table tr > td';
        $asin = $this->findItemValueInProductDetails($bodyAsString, 'asin', $selector);

        return $asin;
    }

    /**
     * @param string $bodyAsString
     *
     * @return string
     *
     * @throws ApplicationException
     */
    private function getProductName($bodyAsString)
    {
        $productName = '';

        $crawler = $this->newInstanceCrawler($bodyAsString);
        $crawler->addContent($bodyAsString);

        $selector = 'span#productTitle';

        try {
            $productName = trim($crawler->filter($selector)->text());
        } catch (\Exception $e) {
            /*NOP*/
        }

        return $productName;
    }

    /**
     * @param string $bodyAsString
     *
     * @return string
     */
    private function getBrand($bodyAsString)
    {
        $selector = 'div#prodDetails div.col1 div.techD  table tr > td';
        $brand = $this->findItemValueInProductDetails($bodyAsString, 'brand', $selector);
        
        return $brand;
    }

    /**
     * @param string $bodyAsString
     *
     * @return string
     */
    private function getManufacturerPartNumber($bodyAsString)
    {
        $selector = 'div#prodDetails div.col1 div.techD  table tr > td';
        
        $manufacturerPartNumber = $this->findItemValueInProductDetails(
            $bodyAsString,
            'manufacturer part number',
            $selector
        );
        
        return $manufacturerPartNumber;
    }

    /**
     * @param string $bodyAsString
     * @param string $itemName
     * @param string $selector
     *
     * @return string
     */
    private function findItemValueInProductDetails($bodyAsString, $itemName, $selector)
    {
        $itemValue = '';
        
        try {
            $crawler = $this->newInstanceCrawler();
            $crawler->addContent($bodyAsString);
            
            $nodes = $crawler->filter($selector);
            
            if (count($nodes) > 0) {
                $isValue = false;
                
                /**
                 * @var \DOMElement $node
                 */
                foreach ($nodes as $node) {
                    if ($isValue) {
                        $itemValue = trim($node->nodeValue);
                        break;
                    }

                    if (0 === strcasecmp(trim($node->nodeValue), $itemName)) {
                        $isValue = true;
                    }
                }
            }
        } catch (\Exception $e) {
            /*NOP*/
        }
        
        return $itemValue;
    }
    
    /**
     * @param string $bodyAsString
     *
     * @return string
     *
     * @throws ApplicationException
     */
    private function getProductDescription($bodyAsString)
    {
        $productDescription = '';

        try {
            $crawler = $this->newInstanceCrawler();
            $crawler->addContent($bodyAsString);

            $selector = 'div.a-container';

            $pageScripts = $crawler->filter($selector)->html();
            
            preg_match('#var iframeContent\s+=\s+\"(.*)\"\;#', $pageScripts, $matches);
            
            if (isset($matches[1])) {
                $descriptionHtml = urldecode($matches[1]);

                $crawler = $this->newInstanceCrawler();
                $crawler->addContent($descriptionHtml);
                
                $selector = 'div#productDescription div.content div.productDescriptionWrapper';
                $productDescription = trim($crawler->filter($selector)->text());
            }
        } catch (\Exception $e) {
            /*NOP*/
        }
        
        return $productDescription;
    }

    /**
     * @param string $bodyAsString
     *
     * @return string
     */
    private function getFeatures($bodyAsString)
    {
        $result = '';
        
        try {
            $crawler = $this->newInstanceCrawler();
            $crawler->addContent($bodyAsString);
            
            $selector = 'div#feature-bullets ul li span.a-list-item';

            $features = $crawler->filter($selector)->each(function (Crawler $node) {
                return trim($node->text());
            });
            
            if (false === empty($features)) {
                $result = implode($this->featureSeparator, $features);
            }
        } catch (\Exception $e) {
            /*NOP*/
        }
        
        return $result;
    }

    /**
     * @param string $bodyAsString
     *
     * @return array
     */
    private function getPathOfImages($bodyAsString)
    {
        $productImages = [];
        
        $getImages = function ($imagesScope, $type) {
            $imagesPattern = '#' . $type . '\"\:\"([^\"]*)\"#';
            preg_match_all($imagesPattern, $imagesScope, $images);
            
            $productImages = [];
            
            if (isset($images[1])) {
                $productImages = $images[1];
            }
            
            return $productImages;
        };
        
        try {
            $pattern = '#P\.when\s{0,}.*\s+var\s{0,}data\s{0,}\=\s{0,}\{\s{0,}(.*)#';

            preg_match($pattern, $bodyAsString, $matches);

            if (isset($matches[1])) {
                $productImages = $getImages($matches[1], 'main');
            }
            
            if (empty($productImages) && isset($matches[1])) {
                $productImages = $getImages($matches[1], 'large');
            }

            if (empty($productImages) && isset($matches[1])) {
                $productImages = $getImages($matches[1], 'hiRes');
            }
        } catch (\Exception $e) {
            /*NOP*/
        }
        
        /**
         * remove no img
         */
        $productImages = array_filter($productImages, function ($imgPath) {
            return false === strpos($imgPath, 'no-img');
        });
        
        return $productImages;
    }

    /**
     * @param ProductInfo[] $productsInfo
     *
     * @return void
     */
    private function writeProductsInfo($productsInfo)
    {
        if (false === empty($productsInfo)) {
            $imgHandle = $this->openResource($this->productImageInfoFilePath, 'ab');
            $productInfoHandle = $this->openResource($this->productInfoFilePath, 'ab');
            
            foreach ($productsInfo as $productInfo) {
                ImageWriter::write($imgHandle, $productInfo->getImages(), $this->logger);
                ProductInfoWriter::write($productInfoHandle, $productInfo, $this->logger);
            }
            
            $this->closeResource($imgHandle, $this->productImageInfoFilePath);
            $this->closeResource($productInfoHandle, $this->productInfoFilePath);
        }
    }

    /**
     * @param ProductInfo[] $productsInfo
     */
    private function writeCompatibilityInfo($productsInfo)
    {
        if (false === empty($productsInfo)) {
            $compatibleHandle = $this->openResource($this->compatibilityChartsInfoFilePath, 'ab');

            $compatibilityUrl = '/gp/product/compatibility-chart/{asin}/ref=au_pf_dp_chart';
            $numbersOfTotalFitments = 0;
            
            $requestOptions = [];
            
            foreach ($productsInfo as $productInfo) {
                $urls = [];
                
                $url                    = $this->getCompatibilityUrl($productInfo, $compatibilityUrl);
                $brand                  = $productInfo->getBrand();
                $manufacturerPartNumber = $productInfo->getManufacturerPartNumber();
                
                $request = $this->client->get($url, [], $requestOptions);
                $response = $this->client->send($request);
                
                try {
                    $bodyAsString = $response->getBody(true);
                    $isEmptyCompatibilityChart = $this->isEmptyCompatibilityChart($bodyAsString);

                    if (false === $isEmptyCompatibilityChart) {
                        $numbersOfTotalFitments = $this->getNumbersOfTotalFitments($bodyAsString);
                        $numbersOfTotalFitments = ceil($numbersOfTotalFitments);
                    }

                    if ($numbersOfTotalFitments > 0) {
                        for ($i = 0; $i <= $numbersOfTotalFitments; $i += 25) {
                            $urls[] = $url . '&i=' . $i;
                        }
                    } elseif (false === $isEmptyCompatibilityChart) {
                        $urls[] = $url;
                    }

                    if (false === empty($urls)) {
                        foreach ($urls as $url) {
                            $fields = [
                                $brand,
                                $manufacturerPartNumber,
                                $url
                            ];

                            $isWrite = CSV::writeRow($compatibleHandle, $fields);

                            if (false === $isWrite) {
                                $this->logger->addError('Unable to write compatibility chart row', $fields);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->addError(
                        'Unable to process the content of the page.',
                        ['url' => $url, 'message' => $e->getMessage()]
                    );
                }
            }

            $this->closeResource($compatibleHandle, $this->compatibilityChartsInfoFilePath);
        }
    }

    /**
     * @param string $bodyAsString
     *
     * @return bool
     *
     * @throws ApplicationException
     */
    private function isEmptyCompatibilityChart($bodyAsString)
    {
        $result = false;
        
        $empty = 'This product has no compatibility chart.';

        try {
            $crawler = $this->newInstanceCrawler();
            $crawler->addContent($bodyAsString);
            $data = $crawler->filter('div.bucket h2')->text();

            $result = $empty === $data;
        } catch (\Exception $e) {
            /*NOP*/
        }
        
        return $result;
    }

    /**
     * @param string $bodyAsString
     *
     * @return int
     *
     * @throws ApplicationException
     */
    private function getNumbersOfTotalFitments($bodyAsString)
    {
        $numbersOfTotalFitments = 0;

        try {
            $crawler = $this->newInstanceCrawler();
            $crawler->addContent($bodyAsString);
            
            $selector = 'div.pagination div.numberofresults';
            $pagination = $crawler->filter($selector)->text();
            
            $paginationParts = explode(' ', trim($pagination));

            $numbersOfTotalFitments = (int)$paginationParts[4];
        } catch (\Exception $e) {
            /*NOP*/
        }
        
        return $numbersOfTotalFitments;
    }

    /**
     * @param ProductInfo $productInfo
     * @param string $compatibilityUrl
     *
     * @return string
     */
    private function getCompatibilityUrl($productInfo, $compatibilityUrl)
    {
        return $this->host . ltrim(str_replace('{asin}', $productInfo->getAsin(), $compatibilityUrl), '/');
    }
}
