<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 28.06.15
 * Time: 16:18
 */
namespace Entities;

/**
 * Class Image
 *
 * @package Entities
 */
class Image
{
    const FILE_NAME_ORDER_SEPARATOR = '[|]'; 
    
    /**
     * @var string
     */
    private $sku;
    
    /**
     * @var string
     */
    private $path;
    
    /**
     * @var int
     */
    private $order;
    
    /**
     * @var bool
     */
    private $isRepresentative;
    
    /**
     * @var string
     */
    private $productUrl;

    /**
     * @param string $sku
     * @param string $path
     * @param int $order
     * @param bool $isRepresentative
     * @param string $productUrl
     */
    public function __construct($sku, $path, $order, $isRepresentative, $productUrl)
    {
        $this->sku = $sku;
        $this->path = $path;
        $this->order = $order;
        $this->isRepresentative = $isRepresentative;
        $this->productUrl = $productUrl;
    }

    /**
     * @return string
     */
    public function getSku()
    {
        return $this->sku;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return boolean
     */
    public function isIsRepresentative()
    {
        return $this->isRepresentative;
    }

    /**
     * @return string
     */
    public function getProductUrl()
    {
        return $this->productUrl;
    }

    /**
     * @return string
     */
    public function getImageFileName()
    {
        $fileName = $this->getSku();
        
        if ($this->isIsRepresentative()) {
            $fileName .= '-representative-photo';
        }
        
        if ($this->getOrder() > 0) {
            $fileName .= self::FILE_NAME_ORDER_SEPARATOR . $this->getOrder();
        }
        
        $pathInfo = pathinfo($this->getPath());

        $extension = 'jpg';
        
        if (isset($pathInfo['extension']) && false === empty($pathInfo['extension'])) {
            $extension = $pathInfo['extension'];
        }

        return $fileName . '.' . $extension;
    }
    
    /**
     * @param array $img
     *
     * @return self
     */
    public static function fromArray(array $img)
    {
        $sku = $img['sku'];
        $src = $img['src'];
        $order = (int)$img['order'];
        $isRepresentative = (bool)$img['representative-photo'];
        $productUrl = $img['product-url'];
        
        return new Image($sku, $src, $order, $isRepresentative, $productUrl);
    }
}
