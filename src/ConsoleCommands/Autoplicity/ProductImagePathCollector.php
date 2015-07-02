<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 15:25
 */
namespace ConsoleCommands\Autoplicity;

use ConsoleCommands\Exceptions\NotValidInputData;
use Entities\Image;
use Exceptions\ApplicationException;
use Exceptions\ContainerException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Helper\Console;
use Helper\CSV;
use Helper\Resource;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class ProductImagePathCollector
 *
 * @package ConsoleCommands
 */
class ProductImagePathCollector extends AbstractAutoplicity
{
    use Resource, Console;
    
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
        $this->setName($this->getParserName() . ':' . self::COMMAND_NAME)
            ->setDescription('Gathers information about galleries products.')
            ->addArgument(
                ProductUrlCollector::BRAND_PAGE,
                InputArgument::REQUIRED,
                'Link to a page with a list of products.'
            )
            ->addOption(
                self::CSV_LINE_NUMBER,
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
     * @throws NotValidInputData
     * @throws ApplicationException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        
        $this->brandPageUrl = $input->getArgument(ProductUrlCollector::BRAND_PAGE);
        
        $this->productUrlsFilePath = \Helper\Path\get_path_to_product_urls($this->getParserName(), $this->brandPageUrl);
        
        try {
            $this->poolSize = $this->container->getParameter('autoplicity.imagePathCollector.poolSize');
        } catch (InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }
        
        $this->fs->createDirIfNotExist(
            \Helper\Path\get_path_to_product_image_info_dir(
                $this->getParserName(),
                $this->brandPageUrl
            )
        );

        $this->productImageInfoFilePath = \Helper\Path\get_path_to_product_image_info(
            $this->getParserName(),
            $this->brandPageUrl
        );
        
        $rows = CSV::getRowCount($this->productUrlsFilePath);
        
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
        
        $this->progressBar->setMessage($this->brandPageUrl);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws ApplicationException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $handle = $this->openResource($this->productUrlsFilePath, 'rb');
        $csvHandle = $this->openResource($this->productImageInfoFilePath, 'ab');
        
        try {
            $urlsChunkSize = (int)$this->container->getParameter('autoplicity.imagePathCollector.urlsChunkSize');
        } catch (InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }
        
        $urlsToParseImagePath = [];
        
        $this->progressBar->start();

        $columnNames = ['url'];

        while (false !== ($row = CSV::readRow($handle, $columnNames))) {
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

                $isWrite = CSV::writeRow($handle, $fields);

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
     * @throws ApplicationException
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
     * @throws ApplicationException
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

            $crawler = $this->newInstanceCrawler();
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
     * @throws ApplicationException
     */
    private function isRepresentativePhoto(Crawler $crawler)
    {
        try {
            $galleryTitle = $this->getPictureImage($crawler)->attr('data-title');
        } catch (\InvalidArgumentException $e) {
            throw ApplicationException::wrapException($e);
        }

        return 'Representative Photo' === trim($galleryTitle);
    }

    /**
     * @param Crawler $crawler
     *
     * @return string
     *
     * @throws ApplicationException
     */
    private function getProductSku(Crawler $crawler)
    {
        try {
            $sku = $crawler->filter('div.overview > div.productpadeleft > div.sku')->text();
        } catch (\Exception $e) {
            throw ApplicationException::wrapException($e);
        }

        $sku = preg_replace('#[sku]\s{0,}:?\s{0,}#i', '', trim($sku));
        
        return $sku;
    }
    
    /**
     * @param Crawler $crawler
     *
     * @return null|string
     *
     * @throws ApplicationException
     */
    private function getPictureSource(Crawler $crawler)
    {
        $picture = $this->getPictureImage($crawler);
        
        try {
            $src = $picture->attr('src');
        } catch (\InvalidArgumentException $e) {
            throw ApplicationException::wrapException($e);
        }

        $src = $this->prepareImgSrc($src);
        
        return $src;
    }
    
    /**
     * @param Crawler $crawler
     *
     * @return Crawler
     *
     * @throws ApplicationException
     */
    private function getPictureImage(Crawler $crawler)
    {
        try {
            $image = $crawler->filter('div#galleria div.picture img')->first();
        } catch (\RuntimeException $e) {
            throw ApplicationException::wrapException($e);
        }

        return $image;
    }

    /**
     * @param string $src
     *
     * @return string
     *
     * @throws ApplicationException
     */
    private function prepareImgSrc($src)
    {
        $src = trim($src);
        
        if ('' !== $src) {
            try {
                $src = str_replace($this->container->getParameter('autoplicity.host'), '', $src);
            } catch (InvalidArgumentException $e) {
                throw ContainerException::wrapException($e);
            }
            
            if ('' !== $src) {
                $src = '/' . $src;
            }
        }
        
        return $src;
    }

    /**
     * @param Crawler $crawler
     *
     * @return array
     *
     * @throws ApplicationException
     */
    private function getThumbsSrc(Crawler $crawler)
    {
        $result = [];
        
        try {
            $imageNodes = $crawler->filter('div#galleria > div.picture-thumbs > div > a > img');
        } catch (\RuntimeException $e) {
            throw ApplicationException::wrapException($e);
        }

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
}
