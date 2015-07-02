<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 01.07.15
 * Time: 0:26
 */
namespace Helper\Path;

/**
 * @return string
 */
function var_path()
{
    return \ROOT_PATH . DIRECTORY_SEPARATOR . 'var';
}

/**
 * @return string
 */
function images_path()
{
    return var_path() . DIRECTORY_SEPARATOR . 'images';
}

/**
 * @return string
 */
function tmp_path()
{
    return var_path() . DIRECTORY_SEPARATOR . 'tmp';
}

/**
 * @return string
 */
function log_path()
{
    return var_path() . DIRECTORY_SEPARATOR . 'log';
}

/**
 * @return string
 */
function bin_path()
{
    return ROOT_PATH . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'application.php';
}

/**
 * @return string
 */
function get_product_url_dir_name()
{
    return 'product-urls';
}

/**
 * @return string
 */
function get_images_info_dir_name()
{
    return 'product-images-info';
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_product_urls_dir($parserName, $brandPageUrl)
{
    $pathToProductUrlsDir = tmp_path() .
        DIRECTORY_SEPARATOR . $parserName .
        DIRECTORY_SEPARATOR . ltrim($brandPageUrl, '/') .
        DIRECTORY_SEPARATOR . get_product_url_dir_name();
    
    return $pathToProductUrlsDir;
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_product_urls($parserName, $brandPageUrl)
{
    $pathToProductUrls = get_path_to_product_urls_dir($parserName, $brandPageUrl) . DIRECTORY_SEPARATOR . 'urls.csv';

    return $pathToProductUrls;
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_product_image_info_dir($parserName, $brandPageUrl)
{
    $pathToProductImgInfoDir = tmp_path() .
        DIRECTORY_SEPARATOR . $parserName .
        DIRECTORY_SEPARATOR . ltrim($brandPageUrl, '/') .
        DIRECTORY_SEPARATOR . get_images_info_dir_name();

    return $pathToProductImgInfoDir;
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_product_image_info($parserName, $brandPageUrl)
{
    $pathToProductImgInfo = get_path_to_product_image_info_dir($parserName, $brandPageUrl) .
        DIRECTORY_SEPARATOR . 'images-info.csv';

    return $pathToProductImgInfo;
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_product_images_dir($parserName, $brandPageUrl)
{
    $pathToProductImagesDir = images_path() .
        DIRECTORY_SEPARATOR . $parserName .
        DIRECTORY_SEPARATOR . ltrim($brandPageUrl, '/');
    
    return $pathToProductImagesDir;
}
