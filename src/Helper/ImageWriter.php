<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 11.07.15
 * Time: 20:13
 */
namespace Helper;

use Entities\Image;
use Monolog\Logger;

class ImageWriter
{
    /**
     * @param resource $handle
     * @param Image[] $images
     * @param Logger $logger
     *
     * @return void
     */
    public static function write($handle, $images, $logger)
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
                    $logger->addError(
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
}
