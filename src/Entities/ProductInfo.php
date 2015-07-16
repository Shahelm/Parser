<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 07.07.15
 * Time: 22:08
 */
namespace Entities;

/**
 * Class ProductInfo
 *
 * @package Entities
 */
class ProductInfo
{
    /**
     * @var string
     */
    private $asin;

    /**
     * @var string
     */
    private $productName;

    /**
     * @var string
     */
    private $brand;

    /**
     * @var string
     */
    private $manufacturerPartNumber;

    /**
     * @var string
     */
    private $productDescription;

    /**
     * @var string
     */
    private $features;
    
    /**
     * @var Image[]
     */
    private $images = array();

    /**
     * @param string $asin
     * @param string $productName
     * @param string $brand
     * @param string $manufacturerPartNumber
     * @param string $productDescription
     * @param string $features
     * @param Image[] $images
     */
    public function __construct(
        $asin,
        $productName,
        $brand,
        $manufacturerPartNumber,
        $productDescription,
        $features,
        $images
    ) {
        $this->asin = $asin;
        $this->productName = $productName;
        $this->brand = $brand;
        $this->manufacturerPartNumber = $manufacturerPartNumber;
        $this->productDescription = $productDescription;
        $this->features = $features;
        $this->images = $images;
    }

    /**
     * @return string
     */
    public function getAsin()
    {
        return $this->asin;
    }

    /**
     * @return string
     */
    public function getProductName()
    {
        return $this->productName;
    }

    /**
     * @return string
     */
    public function getBrand()
    {
        return $this->brand;
    }

    /**
     * @return string
     */
    public function getManufacturerPartNumber()
    {
        return $this->manufacturerPartNumber;
    }

    /**
     * @return string
     */
    public function getProductDescription()
    {
        return $this->productDescription;
    }

    /**
     * @return string
     */
    public function getFeatures()
    {
        return $this->features;
    }

    /**
     * @return Image[]
     */
    public function getImages()
    {
        return $this->images;
    }
}
