<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 15:25
 */
namespace ConsoleCommands;

use Entities\Image;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class ProductImagePathCollector
 *
 * @package ConsoleCommands
 */
class ProductImagePathCollector extends AbstractCommand
{
    const ERROR_GET_CONTENTS_PRODUCT_PAGES = 'Unable to retrieve the contents of the product pages.';
    const COMMAND_NAME = 'product:image-path-collector';

    /**
     * @var string
     */
    private $productUrlsFilePath;
    
    /**
     * @var string
     */
    private $productImageInfoFilePath;
    
    /**
     * @var ProgressBar
     */
    private $progressBar;
    
    /**
     * @var int
     */
    private $poolSize;

    /**
     * @throws \InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Gathers information about galleries products.')
            ->addArgument(
                ProductUrlCollector::PRODUCT_LIST_URL,
                InputArgument::REQUIRED,
                'Link to a page with a list of products.'
            )
            ->addOption(
                ProductImagesCollector::CSV_LINE_NUMBER,
                null,
                InputOption::VALUE_OPTIONAL,
                'Position in the csv file which must to start reading url-s products.',
                0
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
        
        $brandPageUrl = $input->getArgument(ProductUrlCollector::PRODUCT_LIST_URL);
        
        $this->productUrlsFilePath = ProductUrlCollector::getPathToProductUrls($this->getContainer(), $brandPageUrl);
        $this->productImageInfoFilePath = self::getPathToProductImageInfo($this->getContainer(), $brandPageUrl);

        $this->poolSize = $this->getContainer()->getParameter('imagePathCollector.poolSize');
        
        $pathToProductImagesInfo = $this->getContainer()->getParameter('app.tmp.dir') .
            DIRECTORY_SEPARATOR . $brandPageUrl .
            DIRECTORY_SEPARATOR . $this->getContainer()->getParameter('product.imgInfo.dir.name');

        $this->createDirIfNotExist($pathToProductImagesInfo);
        
        $rows = File::getRowCount($this->productUrlsFilePath);
        
        if (false === $rows) {
            $this->logger->addAlert(
                'Unable to count the number of lines in the file.',
                ['file' => $this->productUrlsFilePath]
            );
            $this->exitWithError();
        }
        
        $this->progressBar = new ProgressBar($output, $rows);
        $this->progressBar->setFormat(
            'Url: %message% %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%'
        );
        
        $this->progressBar->setMessage($brandPageUrl);
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
        
        $brandPageUrl = $input->getArgument(ProductUrlCollector::PRODUCT_LIST_URL);
        
        $handle = $this->openResource($this->productUrlsFilePath, 'rb');

        $csvHandle = $this->openResource($this->productImageInfoFilePath, 'ab');
        
        $urlsChunkSize = (int)$this->getContainer()->getParameter('imagePathCollector.urlsChunkSize');
        
        $urlsToParseImagePath = [];
        
        $this->progressBar->start();

        $columnNames = ['url'];

        while (false !== ($row = File::readCsvRow($handle, $columnNames))) {
            if (isset($row['url'])) {
                $url = $row['url'];
                
                if ('' !== $url) {
                    $urlsToParseImagePath[] = $url;

                    if ($urlsChunkSize === count($urlsToParseImagePath)) {
                        $imagesInfo = $this->parseProductsImagesInfo($urlsToParseImagePath);

                        $this->writeImages($csvHandle, $imagesInfo);

                        $urlsToParseImagePath = [];
                    }
                }
            } else {
                $this->logger->error('Unable to read the url of the csv file.', ['position' => ftell($handle)]);
            }
        }
        
        if (count($urlsToParseImagePath) > 0) {
            $imagesInfo = $this->parseProductsImagesInfo($urlsToParseImagePath);
            $this->writeImages($csvHandle, $imagesInfo);
        }
        
        $this->progressBar->finish();
        
        $this->closeResource($handle, $this->productUrlsFilePath);
        $this->closeResource($csvHandle, $this->productImageInfoFilePath);
        
        $output->writeln('');
        $this->logger->info(
            'finish',
            [
                'url'  => $brandPageUrl,
                'time' => round((microtime(true) - $start), 2),
            ]
        );
    }

    /**
     * @param resource $handle
     * @param Image[] $images
     *
     * @return void
     */
    private function writeImages($handle, $images)
    {
        if (false === empty($images)) {
            foreach ($images as $img) {
                $fields = [
                    $img->getSku(),
                    $img->getPath(),
                    $img->getOrder(),
                    $img->isIsRepresentative(),
                    $img->getProductUrl()
                ];

                $isWrite = File::writeCsvRow($handle, $fields);

                if (false === $isWrite) {
                    $this->logger->addError(
                        'Unable to write image info!',
                        [
                            'sku'               => $img->getSku(),
                            'src'               => $img->getPath(),
                            'order'             => $img->getOrder(),
                            'is-representative' => $img->isIsRepresentative(),
                            'product-url'       => $img->getProductUrl()
                        ]
                    );
                }
            }
        }
    }
    
    /**
     * @param array $urlsToParseImagePath
     *
     * @return Image[]
     *
     * @throws \InvalidArgumentException
     */
    private function parseProductsImagesInfo(array $urlsToParseImagePath)
    {
        $imagesInfo = [];

        $requests = function ($urls) {
            foreach ($urls as $url) {
                yield new Request('GET', $url);
            }
        };

        $pool = new Pool($this->client, $requests($urlsToParseImagePath), [
            'concurrency' => $this->poolSize,
            'fulfilled' => function (
                Response $response,
                $index
            ) use (
                $urlsToParseImagePath,
                &$imagesInfo
            ) {
                $images = $this->parseProductImagesInfo($response, $urlsToParseImagePath[$index]);

                try {
                    $this->progressBar->advance();
                } catch (\LogicException $e) {
                    /*NOP*/
                }

                foreach ($images as $image) {
                    if ($image instanceof Image) {
                        $imagesInfo[] = $image;
                    }
                }
            },
            'rejected' => function ($reason, $index) use ($urlsToParseImagePath) {
                try {
                    $this->progressBar->advance();
                } catch (\LogicException $e) {
                    /*NOP*/
                }

                $this->logger->addAlert(
                    'Unable to read the page content.',
                    [
                        'reason' => $reason,
                        'url'    => $urlsToParseImagePath[$index]
                    ]
                );
            },
        ]);

        /**
         * Initiate the transfers and create a promise
         */
        $promise = $pool->promise();
        
        /**
         * Force the pool of requests to complete.
         */
        $promise->wait();
        
        return $imagesInfo;
    }

    /**
     * @param Response $response
     * @param string $url
     *
     * @return \Entities\Image[]
     *
     * @throws \InvalidArgumentException
     */
    private function parseProductImagesInfo($response, $url)
    {
        $images = [];
        
        /**
         * @var \GuzzleHttp\Psr7\Stream $body
         */
        $body = $response->getBody();

        try {
            $bodyAsString = $body->getContents();

            $crawler = $this->getCrawler();
            $crawler->addContent($bodyAsString);

            $sku = $this->getProductSku($crawler);
            $pictureSrc = $this->getPictureSource($crawler);
            $isRepresentativePhoto = $this->isRepresentativePhoto($crawler);

            $images[] = new Image($sku, $pictureSrc, 0, $isRepresentativePhoto, $url);

            $thumbsSrc = $this->getThumbsSrc($crawler);

            if (false === empty($thumbsSrc)) {
                foreach ($thumbsSrc as $key => $src) {
                    /**
                     * Position thumbs always shifted by 1,
                     * because the first position is always the main picture.
                     */
                    $order = $key + 1;

                    $images[] = new Image($sku, $src, $order, false, $url);
                }
            }
        } catch (\RuntimeException $e) {
            $this->logger->addError('Unable to read the page content.', ['url' => $url]);
        }
        
        return $images;
    }
    
    /**
     * @param Crawler $crawler
     *
     * @return bool
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function isRepresentativePhoto(Crawler $crawler)
    {
        $galleryTitle = $this->getPictureImage($crawler)->attr('data-title');
        
        return 'Representative Photo' === trim($galleryTitle);
    }

    /**
     * @param Crawler $crawler
     *
     * @return string
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function getProductSku(Crawler $crawler)
    {
        $sku = $crawler->filter('div.overview > div.productpadeleft > div.sku')->text();
        
        $sku = preg_replace('#[sku]\s{0,}:?\s{0,}#i', '', trim($sku));
        
        return $sku;
    }
    
    /**
     * @param Crawler $crawler
     *
     * @return null|string
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     */
    private function getPictureSource(Crawler $crawler)
    {
        $picture = $this->getPictureImage($crawler);
        
        $src = $picture->attr('src');

        $src = $this->prepareImgSrc($src);
        
        return $src;
    }
    
    /**
     * @param Crawler $crawler
     *
     * @return Crawler
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    private function getPictureImage(Crawler $crawler)
    {
        return $crawler->filter('div#galleria div.picture img')->first();
    }

    /**
     * @param string $src
     *
     * @return string
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     */
    private function prepareImgSrc($src)
    {
        $src = trim($src);
        
        if ('' !== $src) {
            $src = str_replace($this->getContainer()->getParameter('app.autoplicity.host'), '', $src);
            
            if ('' !== $src) {
                $src = '/' . $src;
            }
        }
        
        return $src;
    }

    /**
     * @param Crawler $crawler
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws InvalidArgumentException
     *
     * @return array
     */
    private function getThumbsSrc(Crawler $crawler)
    {
        $result = [];
        
        $imageNodes = $crawler->filter('div#galleria > div.picture-thumbs > div > a > img');
        
        if (count($imageNodes) > 0) {
            /**
             * @var \DOMElement $imgNode
             */
            foreach ($imageNodes as $imgNode) {
                $src = $this->prepareImgSrc($imgNode->getAttribute('src'));

                if ('' !== $src) {
                    $result[] = $src;
                }
            }
        }
        
        return $result;
    }

    /**
     * @param ContainerInterface $container
     * @param string $brandPageUrl
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public static function getPathToProductImageInfo(ContainerInterface $container, $brandPageUrl)
    {
        $pathToProductUrls = $container->getParameter('app.tmp.dir') .
            DIRECTORY_SEPARATOR . $brandPageUrl .
            DIRECTORY_SEPARATOR . $container->getParameter('product.imgInfo.dir.name') .
            DIRECTORY_SEPARATOR . 'images-info';

        return $pathToProductUrls;
    }
}
