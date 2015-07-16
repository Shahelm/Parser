<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 11.07.15
 * Time: 22:25
 */
namespace Helper;

use Entities\ProductInfo;
use Monolog\Logger;

/**
 * Class ProductInfoWriter
 *
 * @package Helper
 */
class ProductInfoWriter
{
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
