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
 * @param string $parserName
 *
 * @return string
 */
function images_path($parserName)
{
    return var_path() . DIRECTORY_SEPARATOR . $parserName . DIRECTORY_SEPARATOR . 'images';
}

/**
 * @param string $parserName
 *
 * @return string
 */
function tmp_path($parserName)
{
    return var_path() . DIRECTORY_SEPARATOR . $parserName . DIRECTORY_SEPARATOR . 'tmp';
}

/**
 * @param string $parserName
 *
 * @return string
 */
function log_path($parserName)
{
    return var_path() . DIRECTORY_SEPARATOR . $parserName . DIRECTORY_SEPARATOR . 'log';
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
    $pathToProductUrlsDir = var_path() .
        DIRECTORY_SEPARATOR . $parserName .
        DIRECTORY_SEPARATOR . 'tmp' .
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
    $pathToProductImgInfoDir = var_path() .
        DIRECTORY_SEPARATOR . $parserName .
        DIRECTORY_SEPARATOR . 'tmp' .
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
    $pathToProductImagesDir = images_path($parserName) . DIRECTORY_SEPARATOR . ltrim($brandPageUrl, '/');
    
    return $pathToProductImagesDir;
}

/**
 * @return string
 */
function get_product_info_dir_name()
{
    return 'product-info';
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_product_info_dir($parserName, $brandPageUrl)
{
    $pathToProductInfoDir = var_path() .
        DIRECTORY_SEPARATOR . $parserName .
        DIRECTORY_SEPARATOR . 'tmp' .
        DIRECTORY_SEPARATOR . ltrim($brandPageUrl, '/') .
        DIRECTORY_SEPARATOR . get_product_info_dir_name();
    
    return $pathToProductInfoDir;
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_product_info($parserName, $brandPageUrl)
{
    $pathToProductInfo = get_path_to_product_info_dir($parserName, $brandPageUrl)
        . DIRECTORY_SEPARATOR . 'product-info.csv';
    
    return $pathToProductInfo;
}

/**
 * @return string
 */
function get_compatibility_charts_info_dir_name()
{
    return 'compatibility-charts-info';
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_compatibility_charts_info_dir($parserName, $brandPageUrl)
{
    $pathToProductInfoDir = var_path() .
        DIRECTORY_SEPARATOR . $parserName .
        DIRECTORY_SEPARATOR . 'tmp' .
        DIRECTORY_SEPARATOR . ltrim($brandPageUrl, '/') .
        DIRECTORY_SEPARATOR . get_compatibility_charts_info_dir_name();
    
    return $pathToProductInfoDir;
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_compatibility_charts_info($parserName, $brandPageUrl)
{
    $pathToCompatibilityChartsInfo = get_path_to_compatibility_charts_info_dir($parserName, $brandPageUrl)
        . DIRECTORY_SEPARATOR . 'compatibility-charts-info.csv';
    
    return $pathToCompatibilityChartsInfo;
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_compatibility_charts_dir($parserName, $brandPageUrl)
{
    $pathToCompatibilityChartsDir = var_path() .
        DIRECTORY_SEPARATOR . $parserName .
        DIRECTORY_SEPARATOR . 'compatibility-charts' .
        DIRECTORY_SEPARATOR . ltrim($brandPageUrl, '/');
    
    return $pathToCompatibilityChartsDir;
}

/**
 * @param string $parserName
 * @param string $brandPageUrl
 *
 * @return string
 */
function get_path_to_compatibility_charts($parserName, $brandPageUrl)
{
    $pathToCompatibilityCharts = get_path_to_compatibility_charts_dir($parserName, $brandPageUrl) .
        DIRECTORY_SEPARATOR . 'compatibility-charts.csv';
    
    return $pathToCompatibilityCharts;
}
