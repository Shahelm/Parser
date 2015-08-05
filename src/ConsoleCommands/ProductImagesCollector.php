<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 23:03
 */
namespace ConsoleCommands;

use Entities\Image;
use Exceptions\ApplicationException;
use Exceptions\ContainerException;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use Helper\Console;
use Helper\Container;
use Helper\CSV;
use Helper\ExecutorParallelQuery;
use Helper\Filesystem;
use Helper\Resource;
use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * Class ProductImagesCollector
 *
 * @package ConsoleCommands
 */
class ProductImagesCollector extends Command
{
    use Resource, Console;
    
    const OPTION_PARSER_NAME = 'parser-name';
    const COMMAND_NAME = 'product:image-collector';
    
    /**
     * @var int
     */
    private $start;
    
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var Client
     */
    private $client;
    
    /**
     * @var Logger
     */
    protected $logger;
    
    /**
     * @var Filesystem
     */
    private $fs;
    
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
     * @var string
     */
    private $projectName;
    
    /**
     * @var string
     */
    private $parserName;
    
    /**
     * @throws \InvalidArgumentException
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_NAME)
            ->setDescription('Download images on the links listed in csv.')
            ->addArgument(
                AbstractCommand::PROJECT_NAME,
                InputArgument::REQUIRED,
                'Link to a page with a list of products.'
            )
            ->addOption(
                self::OPTION_PARSER_NAME,
                null,
                InputOption::VALUE_REQUIRED,
                'The name parser for which images are downloaded.'
            )
            ->addOption(
                AbstractCommand::CSV_LINE_NUMBER,
                null,
                InputOption::VALUE_OPTIONAL,
                'Position in the csv file that you want to start downloading the images.',
                1 // exclude csv headers
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws ApplicationException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->start = microtime(true);
        
        try {
            $this->container = $this->getHelper(Container::class)->getContainer();
        } catch (\InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }
        
        $this->parserName = $input->getOption(self::OPTION_PARSER_NAME);
        
        try {
            $this->client = $this->container->get($this->parserName . '.' . 'client');
            $this->logger = $this->container->get($this->parserName . '.' . 'logger');
        } catch (ServiceNotFoundException $e) {
            throw ContainerException::wrapException($e);
        } catch (ServiceCircularReferenceException $e) {
            throw ContainerException::wrapException($e);
        }
        
        $this->fs = new Filesystem();
        
        $this->projectName = $input->getArgument(AbstractCommand::PROJECT_NAME);
        
        try {
            $poolSizeKey = $this->parserName . '.' . 'imagesCollector.poolSize';

            $this->poolSize = 3;
            
            if ($this->container->hasParameter($poolSizeKey)) {
                $this->poolSize = (int)$this->container->getParameter($poolSizeKey);
            }
            
        } catch (InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }
        
        $this->productImageInfoFilePath = \Helper\Path\get_path_to_product_image_info(
            $this->parserName,
            $this->projectName
        );
        
        $this->productImagesDir = \Helper\Path\get_path_to_product_images_dir(
            $this->parserName,
            $this->projectName
        );

        $this->fs->createDirIfNotExist($this->productImagesDir);

        $rows = CSV::getRowCount($this->productImageInfoFilePath);

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

        $this->progressBar->setMessage($this->projectName);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws ApplicationException
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $csvRowOffset = $input->getOption(AbstractCommand::CSV_LINE_NUMBER);

        $csvHandle = $this->openResource($this->productImageInfoFilePath, 'rb');
        
        try {
            $columnNames = $this->container->getParameter('app.imageInfoHeaders');
            $imagesChunkSize = 1000;
            
        } catch (InvalidArgumentException $e) {
            throw ContainerException::wrapException($e);
        }
        
        $images = [];
        $csvRowNumber = 0;
        $this->progressBar->start();

        while (false !== ($row = (CSV::readRow($csvHandle, $columnNames)))) {
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

        try {
            $output->writeln('');
            $this->logger->info(
                __CLASS__ . ':finish',
                [
                    'url'  => $this->projectName,
                    'time' => round((microtime(true) - $this->start), 2),
                ]
            );
        } catch (\Exception $e) {
            /*NOP*/
        }
    }

    /**
     * @param Response $response
     * @param Image $image
     */
    protected function saveImage($response, $image)
    {
        $imageFileName = $image->getImageFileName();
        $imageContent = $response->getBody(true);

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
        $requests = [];

        foreach ($images as $image) {
            $requests[] = $this->client->get($image->getPath());
        }

        (new ExecutorParallelQuery($this->client, $requests, 5))
            ->onSuccess(function (Request $request, $index) use ($images) {
                $this->saveImage($request->getResponse(), $images[$index]);
            })
            ->onError(function (Request $request) {
                $this->logger->addAlert('Unable to read image.', ['url' => $request->getUrl()]);
            })
            ->afterProcessing(function () {
                try {
                    $this->progressBar->advance();
                } catch (\LogicException $e) {
                    /*NOP*/
                }
            })
            ->wait();
    }
}
