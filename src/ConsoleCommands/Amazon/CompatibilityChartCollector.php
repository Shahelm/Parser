<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 13.07.15
 * Time: 13:51
 */
namespace ConsoleCommands\Amazon;

use Exceptions\ApplicationException;
use Guzzle\Http\Message\Request;
use Helper\CSV;
use Helper\ExecutorParallelQuery;
use Helper\Resource;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class CompatibilityChartCollector
 *
 * @package ConsoleCommands\Amazon
 */
class CompatibilityChartCollector extends AbstractAmazon
{
    use Resource;
    
    const COMMAND_NAME = ':product:compatibility-chart-collector';
    
    /**
     * @var string
     */
    private $compatibilityChartsInfoFilePath;
    
    /**
     * @var ProgressBar
     */
    private $progressBar;
    
    /**
     * @var int
     */
    private $csvLineNumber;
    
    /**
     * @var string
     */
    private $compatibilityChartsPath;
    
    /**
     * @var array
     */
    private static $csvHeaders = [
        'year',
        'make',
        'model',
        'notes',
        'brand',
        'manufacturerPartNumber',
        'trim',
        'engine',
        'other'
    ];
    
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
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws \ConsoleCommands\Exceptions\NotValidInputData
     * @throws ApplicationException
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $this->csvLineNumber = (int)$input->getArgument(self::CSV_LINE_NUMBER);
        $this->projectName   = $input->getArgument(self::PROJECT_NAME);
        
        $this->compatibilityChartsInfoFilePath = \Helper\Path\get_path_to_compatibility_charts_info(
            $this->getParserName(),
            $this->getProjectName()
        );

        $this->fs->createDirIfNotExist(\Helper\Path\get_path_to_compatibility_charts_dir(
            $this->getParserName(),
            $this->getProjectName()
        ));
        
        $this->compatibilityChartsPath = \Helper\Path\get_path_to_compatibility_charts(
            $this->getParserName(),
            $this->getProjectName()
        );
        
        $rows = CSV::getRowCount($this->compatibilityChartsInfoFilePath);

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
     * @return int
     * @throws ApplicationException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->progressBar->getMaxSteps()) {
            $this->writeCSVHeaders();
            
            $this->progressBar->start();

            try {
                $this->progressBar->setProgress($this->csvLineNumber);
            } catch (\LogicException $e) {
                /*NOP*/
            }
    
            $chartsInfoHandle = $this->openResource($this->compatibilityChartsInfoFilePath, 'rb');
    
            $columnNames = [
                'brand',
                'manufacturerPartNumber',
                'urlChartInfo'
            ];
            
            $csvRowNumber = 0;
            $chartsInfo = [];
            $productUrlsChunkSize = 100;
            
            while (false !== ($row = (CSV::readRow($chartsInfoHandle, $columnNames)))) {
                if ($csvRowNumber >= $this->csvLineNumber) {
                    $chartsInfo[$row['urlChartInfo']] = $row;
                    
                    if (count($chartsInfo) === $productUrlsChunkSize) {
                        $compatibilityCharts = $this->getCompatibilityCharts($chartsInfo);
                        $this->writeCompatibilityCharts($compatibilityCharts);
                        $chartsInfo = [];
                    }
                }
    
                $csvRowNumber++;
            }
            
            if (count($chartsInfo) > 0) {
                $compatibilityCharts = $this->getCompatibilityCharts($chartsInfo);
                $this->writeCompatibilityCharts($compatibilityCharts);
            }
            
            $this->progressBar->finish();
            
            $this->closeResource($chartsInfoHandle, $this->compatibilityChartsInfoFilePath);
        }
    }

    /**
     * @param array $chartsInfo
     *
     * @return array
     */
    private function getCompatibilityCharts(array $chartsInfo)
    {
        $compatibilityCharts = [];
        $chartsInfoIndexByUrl = [];
        $request = [];
        
        foreach ($chartsInfo as $chartInfoUrl => $chartInfo) {
            $request[] = $this->client->get($chartInfoUrl);

            $chartsInfoIndexByUrl[$chartInfoUrl] = [
                'brand'                  => $chartInfo['brand'],
                'manufacturerPartNumber' => $chartInfo['manufacturerPartNumber']
            ];
        }

        (new ExecutorParallelQuery($this->client, $request, 3))
            ->onSuccess(function (Request $request) use (&$compatibilityCharts, $chartsInfoIndexByUrl) {
                $url = $request->getUrl();
                $response = $request->getResponse();
                
                try {
                    $bodyAsString = $response->getBody(true);
                    
                    $crawler = $this->newInstanceCrawler();
                    $crawler->addContent($bodyAsString);

                    $tableCrawler = $crawler->filter('div.content table.compatibility');
        
                    $tableHeaders = $tableCrawler->filter('tr')->eq(0)->filter('th')->each(function (Crawler $node) {
                        return trim(strtolower($node->text()));
                    });

                    $compatibilityChart = $tableCrawler->filter('tr')
                        ->reduce(function (Crawler $node, $index) {
                            /**
                             * skip table header
                             */
                            return $index > 0;
                        })
                        ->each(function (Crawler $node) use ($tableHeaders) {
                            $row = $node->filter('td')->each(function (Crawler $node) use ($tableHeaders) {
                                return trim($node->text());
                            });
                            
                            return array_combine($tableHeaders, $row);
                        });
                    
                    $brand = $chartsInfoIndexByUrl[$url]['brand'];
                    $manufacturerPartNumber = $chartsInfoIndexByUrl[$url]['manufacturerPartNumber'];
                    
                    foreach ($compatibilityChart as $row) {
                        $chart = [
                            'make'                   => '',
                            'model'                  => '',
                            'year'                   => '',
                            'trim'                   => '',
                            'engine'                 => '',
                            'notes'                  => '',
                        ];
                        
                        $other = [];
                        
                        foreach ($row as $columnName => $value) {
                            if (isset($chart[$columnName])) {
                                $chart[$columnName] = $value;
                            } else {
                                $other[] = implode('[=]', [$columnName, $value]);
                            }
                        }

                        $chart['other'] = '';

                        if (false === empty($other)) {
                            $chart['other'] = implode('[|]', $other);
                        }
                        
                        $chart['brand'] = $brand;
                        $chart['manufacturerPartNumber'] = $manufacturerPartNumber;

                        $compatibilityCharts[] = $chart;
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

        return $compatibilityCharts;
    }

    /**
     * @param array $compatibilityCharts
     *
     * @return void
     */
    private function writeCompatibilityCharts(array $compatibilityCharts)
    {
        if (false === empty($compatibilityCharts)) {
            $handle = $this->openResource($this->compatibilityChartsPath, 'ab');
            
            foreach ($compatibilityCharts as $compatibilityChart) {
                $fields = [
                    'year'                   => $compatibilityChart['year'],
                    'make'                   => $compatibilityChart['make'],
                    'model'                  => $compatibilityChart['model'],
                    'notes'                  => $compatibilityChart['notes'],
                    'brand'                  => $compatibilityChart['brand'],
                    'manufacturerPartNumber' => $compatibilityChart['manufacturerPartNumber'],
                    'trim'                   => $compatibilityChart['trim'],
                    'engine'                 => $compatibilityChart['engine'],
                    'other'                  => $compatibilityChart['other'],
                ];
                
                $isWrite = CSV::writeRow($handle, $fields);
                
                if (false === $isWrite) {
                    $this->logger->addError('Unable to write compatibility chart info!', $fields);
                }
            }
            
            $this->closeResource($handle, $this->compatibilityChartsPath);
        }
    }

    /**
     * @throws ApplicationException
     */
    private function writeCSVHeaders()
    {
        $handle = $this->openResource($this->compatibilityChartsPath, 'w+b');

        $isWrite = CSV::writeRow($handle, self::$csvHeaders);

        if (false === $isWrite) {
            $this->logger->addError('Unable to write compatibility chart headers!', self::$csvHeaders);
            throw new ApplicationException();
        }

        $this->closeResource($handle, $this->compatibilityChartsPath);
    }
}
