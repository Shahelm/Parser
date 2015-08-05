<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 11.07.15
 * Time: 22:25
 */
namespace Helper;

use Entities\ProductInfo;
use Exceptions\ApplicationException;
use Monolog\Logger;

/**
 * Class ProductInfoWriter
 *
 * @package Helper
 */
class ProductInfoWriter
{
    /**
     * @return array
     */
    public static function getProductInfoHeaders()
    {
        return [
            'asin',
            'productName',
            'brand',
            'manufacturerPartNumber',
            'productDescription',
            'features',
        ];
    }

    /**
     * @param string $path
     * @param array  $headers
     * @param Logger $logger
     *
     * @throws ApplicationException
     */
    public static function writCSVHeaders($path, array $headers, $logger)
    {
        $mode = 'w+b';
        $handle = fopen($path, $mode);
        
        if (false === $handle) {
            $logger->addAlert('Failed create a file handle.', ['file' => $path, 'mode' => $mode]);
            exit(1);
        }
        
        $isWrite = CSV::writeRow($handle, $headers);

        if (false === $isWrite) {
            $logger->addError('Unable to write headers!', $headers);
            fclose($handle);
            throw new ApplicationException();
        }

        fclose($handle);
    }

    /**
     * @param resource $handle
     * @param ProductInfo $productInfo
     * @param Logger $logger
     */
    public static function write($handle, ProductInfo $productInfo, $logger)
    {
        $fields = [
            $productInfo->getAsin(),
            $productInfo->getProductName(),
            $productInfo->getBrand(),
            $productInfo->getManufacturerPartNumber(),
            $productInfo->getProductDescription(),
            $productInfo->getFeatures()
        ];

        $isWrite = CSV::writeRow($handle, $fields);

        if (false === $isWrite) {
            $logger->addError(
                'Unable to write image info!',
                [
                    'asin'                   => $productInfo->getAsin(),
                    'productName'            => $productInfo->getProductName(),
                    'brand'                  => $productInfo->getBrand(),
                    'manufacturerPartNumber' => $productInfo->getManufacturerPartNumber(),
                    'productDescription'     => $productInfo->getProductDescription(),
                    'features'               => $productInfo->getFeatures()
                ]
            );
        }
    }
}
