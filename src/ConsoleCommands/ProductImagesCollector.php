<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 23:03
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
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Class ProductImagesCollector
 *
 * @package ConsoleCommands
 */
class ProductImagesCollector extends AbstractCommand
{
    const CSV_LINE_NUMBER = 'csv-line-number';
    const COMMAND_NAME = 'product:image-collector';

    /**
     * @var string
     */
    private $productImageInfoFilePath;

    /**
     * @var string
     */
    private $productImagesDir;
    
    /**
     * @var int
     */
    private $poolSize;
    
    /**
     * @var ProgressBar
     */
    private $progressBar;

    /**
     * @throws \InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Download images on the links listed in csv.')
            ->addArgument(
                ProductUrlCollector::PRODUCT_LIST_URL,
                InputArgument::REQUIRED,
                'Link to a page with a list of products.'
            )
            ->addOption(
                self::CSV_LINE_NUMBER,
                null,
                InputOption::VALUE_OPTIONAL,
                'Position in the csv file that you want to start downloading the images.',
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
        
        $this->poolSize = (int)$this->getContainer()->getParameter('imagesCollector.poolSize');
        
        $this->productImageInfoFilePath = ProductImagePathCollector::getPathToProductImageInfo(
            $this->getContainer(),
            $brandPageUrl
        );
        
        $this->productImagesDir = $this->getContainer()->getParameter('app.images.dir')
            . DIRECTORY_SEPARATOR . $brandPageUrl;

        $this->createDirIfNotExist($this->productImagesDir);

        $rows = File::getRowCount($this->productImageInfoFilePath);

        if (false === $rows) {
            $this->logger->addAlert(
                'Unable to count the number of lines in the file.',
                ['file' => $this->productImageInfoFilePath]
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
        $csvRowOffset = $input->getOption(self::CSV_LINE_NUMBER);

        $csvHandle = $this->openResource($this->productImageInfoFilePath, 'rb');

        $columnNames = $this->getContainer()->getParameter('app.imageInfoHeaders');
        
        $csvRowNumber = 0;
        
        $imagesChunkSize = (int)$this->getContainer()->getParameter('imagesCollector.imagesChunkSize');
        
        $images = [];
        
        $this->progressBar->start();

        while (false !== ($row = (File::readCsvRow($csvHandle, $columnNames)))) {
            if ($csvRowNumber >= $csvRowOffset) {
                $image = Image::fromArray($row);

                $images[] = $image;

                if (count($images) === $imagesChunkSize) {
                    $this->downloadImages($images);
                    $images = [];
                }
            }

            $csvRowNumber++;
        }
        
        if (count($images) > 0) {
            $this->downloadImages($images);
        }

        $this->progressBar->finish();

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
     * @param Response $response
     * @param Image $image
     */
    protected function saveImage($response, $image)
    {
        $body = $response->getBody();

        $imageFileName = $image->getImageFileName();
        $imageContent = $body->getContents();

        $filePath = $this->productImagesDir . DIRECTORY_SEPARATOR . $imageFileName;

        try {
            $this->fs->dumpFile($filePath, $imageContent);
        } catch (IOException $e) {
            $this->logger->addAlert(
                'Unable to save image!',
                [
                    'url'         => $image->getPath(),
                    'product-url' => $image->getProductUrl()
                ]
            );
        }
    }

    /**
     * @param Image[] $images
     *
     * @return void
     */
    private function downloadImages($images)
    {
        /**
         * @param Image[] $images
         *
         * @return \Generator
         */
        $requests = function ($images) {
            foreach ($images as $image) {
                yield new Request('GET', $image->getPath());
            }
        };

        $pool = new Pool($this->client, $requests($images), [
            'concurrency' => $this->poolSize,
            'fulfilled' => function (Response $response, $index) use ($images) {
                $this->saveImage($response, $images[$index]);

                try {
                    $this->progressBar->advance();
                } catch (\LogicException $e) {
                    /*NOP*/
                }
            },
            'rejected' => function ($reason, $index) use ($images) {
                $this->logger->addAlert(
                    'Unable to read image.',
                    [
                        'reason'      => $reason,
                        'url'         => $images[$index]->getPath(),
                        'product-url' => $images[$index]->getProductUrl()
                    ]
                );

                try {
                    $this->progressBar->advance();
                } catch (\LogicException $e) {
                    /*NOP*/
                }
            },
        ]);
        
        $promise = $pool->promise();
        
        $promise->wait();
    }
}
