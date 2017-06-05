<?php

class MediaDownload
{
    protected $_productCollection;
    protected $_categoryCollection;
    protected $_vDefaultStoreCode = 'default';
    protected $_bOnlyInStock = true;
    protected $_bUseAria2c = true;
    protected $_vRemoteBaseUrl = 'http://www.example.com/media';

    public function __construct()
    {
        if (is_callable('parent::__construct')) {
            parent::__construct();
        }
        $this->checkBaseUrl();
        $this->initializeMagento();
    }

    protected function checkBaseUrl()
    {
        if (in_array(strtolower(parse_url($this->_vRemoteBaseUrl, PHP_URL_HOST)),
            ['example.com', 'www.example.com'])) {
            throw new Exception('Please set base url ');
        }
    }

    protected function initializeMagento()
    {
        if (!@class_exists('Mage', false)) {
            ini_set('display_errors', 1);
            require_once 'app/Mage.php';
            Mage::app();
            Mage::setIsDeveloperMode(true);

        }
        Mage::app()->setCurrentStore($this->getDefaultStore()->getCode());
    }

    protected function getProductCollection()
    {
        if (is_null($this->_productCollection)) {
            $this->_productCollection = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToSelect($this->getImageAttributes())
                ->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));
            if ($this->_bOnlyInStock) {
                Mage::getSingleton('cataloginventory/stock')
                    ->addInStockFilterToCollection($this->_productCollection);
            }
        }
        return $this->_productCollection;
    }

    protected function getDefaultStore()
    {
        $aStores = Mage::app()->getStores(false, true);
        if (isset($aStores[$this->_vDefaultStoreCode])) {
            return $aStores[$this->_vDefaultStoreCode];
        }
        return current($aStores);
    }

    protected function getImageAttributes()
    {
        return 'image';
    }

    protected function getImages()
    {
        $cProducts = $this->getProductCollection();
        $aIds = $cProducts->getAllIds();
        $oSelect = $cProducts->getSelect()->getAdapter()->select();
        $oSelect->
        from('catalog_product_entity_media_gallery', 'value')
            ->where('entity_id IN (?)', $aIds);
        $aImages = $oSelect->getAdapter()->fetchCol($oSelect);
        $aImages = array_unique($aImages);
        return $aImages;
    }

    /**
     * @return Mage_Catalog_Model_Resource_Category_Collection
     */
    protected function getCategoryCollection()
    {
        if (is_null($this->_categoryCollection)) {
            $this->_categoryCollection = Mage::getModel('catalog/category')->getCollection()
                ->addAttributeToSelect('image');
        }
        return $this->_categoryCollection;
    }
    protected function getCategoryImagesList()
    {
        $cCategory = $this->getCategoryCollection();
        $aImage =  $cCategory->getColumnValues('image');
        $aImage = array_unique($aImage);
        return $aImage;
    }

    protected function getMissingImages($vTarget,$aExistingList)
    {
        if ($vTarget=='product'){
            $aImages = $this->getImages();
        }
        elseif($vTarget='category'){
            $aImages = $this->getCategoryImagesList();
        }
        else{
            throw new Exception('target not correct: ' . $vTarget);
        }

        $vBasePath = Mage::getBaseDir('media') . "/catalog/$vTarget";
        $aMissingImages = $aExistingList;
        foreach ($aImages as $vImage) {
            if ($vImage=='no_selection'){
                continue;
            }
            $vImage = '/' . ltrim($vImage,'/');
            $vFullPath = $vBasePath . $vImage;
            if (!file_exists($vFullPath)) {
                if ($this->_bUseAria2c) {
                    $aMissingImages[] = $this->_vRemoteBaseUrl . "/catalog/$vTarget" . $vImage;
                    $aMissingImages[] = "   out=" . "media/catalog/$vTarget" . $vImage;
                }
                else {
                    $aMissingImages[] = "/media/catalog/$vTarget" . $vImage;
                }
            }
        }
        return $aMissingImages;
    }

    public function downloadImages()
    {
        $aImages = $this->getMissingImages('product',[]);
        $aImages = $this->getMissingImages('category',$aImages);

        if (!$aImages){
            echo "no missing images \n";
            return ;
        }
        $vPath = $this->writeImages($aImages);
        $vRemoteBaseUrl = $this->_vRemoteBaseUrl;


        if ($this->_bUseAria2c) {
            $vCommand = "aria2c  -i $vPath --max-concurrent-downloads=10";
        }
        else {
            $vCommand = "wget -P . -r -i $vPath --cut-dirs=1 --base=$vRemoteBaseUrl";
        }


        echo "execute following:\n";
        echo "$vCommand\n";;
    }

    protected function writeImages($aImages)
    {
        $vImages = implode("\n", $aImages);
        $vPath = Mage::getBaseDir('var') . '/image_list.txt';
        file_put_contents($vPath, $vImages);
        @chmod($vPath, 0777);
        return $vPath;
    }
}

if (($_SERVER['SCRIPT_FILENAME'] === __FILE__) ||
    (strpos(__FILE__, $_SERVER['SCRIPT_FILENAME']))
) {
    $mediaDownload = new MediaDownload();
    $mediaDownload->downloadImages();
}