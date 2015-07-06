<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 06.07.15
 * Time: 20:41
 */
namespace ConsoleCommands\Amazon;

use GuzzleHttp\Exception\TransferException;

/**
 * Class ProductUrlCollector
 *
 * @package ConsoleCommands\Amazon
 */
class ProductUrlCollector extends \ConsoleCommands\Autoplicity\ProductUrlCollector
{
    /**
     * @return string
     */
    protected function getParserName()
    {
        return AbstractAmazon::PARSER_NAME;
    }

    /**
     * {@inheritdoc}
     */
    protected function getProductUrls($bodyAsString)
    {
        $productLinks = [];
        
        $crawler = $this->newInstanceCrawler();
        $crawler->addContent($bodyAsString);

        $selector = 'div#resultsCol li.s-result-item div.s-item-container div.a-section > a.a-link-normal';
        
        $crawler = $crawler->filter($selector);
        
        /**
         * @var \DOMElement $node
         */
        foreach ($crawler as $node) {
            $productLinks[] = '/' . ltrim(str_replace($this->getHost(), '', $node->getAttribute('href')), '/');
        }

        return $productLinks;
    }
    
    /**
     * @param string $url
     * @param int $page
     *
     * @return array
     */
    protected function getQuery($url, $page)
    {
        $urlParts = parse_url($url);
        parse_str(urldecode($urlParts['query']), $queryParts);
        
        $rh = $queryParts['rh'];
        
        if (false === strpos($rh, 'p_n_availability:1248792011')) {
            $rh .= ',p_n_availability:1248792011';
        }
        
        $query = [
            'query' => [
                'rh'   => $rh,
                'page' => $page,
            ]
        ];
        
        return $query;
    }

    /**
     * @return int
     */
    protected function getNumbersOfPage()
    {
        $lastPage = 0;
        
        $context = ['url' => $this->brandPageUrl];
        
        try {
            /**
             * @var \GuzzleHttp\Psr7\Response $response
             */
            $response = $this->client->get($this->brandPageUrl);

            if (200 === $response->getStatusCode()) {
                /**
                 * @var \GuzzleHttp\Psr7\Stream $body
                 */
                $body = $response->getBody();

                try {
                    $bodyAsString = $body->getContents();
                    $crawler = $this->newInstanceCrawler();
                    $crawler->addContent($bodyAsString);

                    $selector = 'div#pagn > span.pagnDisabled';

                    $crawler = $crawler->filter($selector);

                    $lastPage = (int)$crawler->text();
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
        
        $lastPage = $lastPage > 0 ? $lastPage : 1;
        
        return $lastPage;
    }
}
