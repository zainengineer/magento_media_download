<?php

class MediaDownload
{
    protected $_productCollection;
    protected $_categoryCollection;
    protected $_vDefaultStoreCode = 'default';
    protected $_bOnlyInStock = true;
    protected $_bOnlyEnabled = true;
    protected $_bUseAria2c = true;
    protected $_vFullMagentoPath = '';
    protected $_vRemoteBaseUrl = 'http://www.example.com/media';
    protected $_iImageCount = 0;
    protected $_bLimitProducts = true;

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
            if ($this->_vFullMagentoPath){
                require_once $this->_vFullMagentoPath;
            }
            else{
                require_once 'app/Mage.php';
            }
            Mage::app();
            Mage::setIsDeveloperMode(true);

        }
        Mage::app()->setCurrentStore($this->getDefaultStore()->getCode());
    }

    protected function getProductCollection()
    {
        if (is_null($this->_productCollection)) {
            $this->_productCollection = Mage::getResourceModel('catalog/product_collection')
                ->addAttributeToSelect($this->getImageAttributes());
            if ($this->_bOnlyEnabled){
                $this->_productCollection
                    ->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));
            }
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
        from('catalog_product_entity_media_gallery', 'value');
        if ($this->_bLimitProducts){
            $oSelect->where('entity_id IN (?)', $aIds);
        }

        $aImages = $oSelect->getAdapter()->fetchCol($oSelect);
        $aImages = array_unique($aImages);

        $oSelect = $cProducts->getSelect()->getAdapter()->select();
        $oSelect->
        from('catalog_product_entity_varchar', 'value')
            ->where("((value like '/%') AND ((value like '%.png') OR (value like '%.jpg') OR (value like '%.gif')))");
        if ($this->_bLimitProducts){
            $oSelect->where('entity_id IN (?)', $aIds);
        }
        $aProductImages = $oSelect->getAdapter()->fetchCol($oSelect);
        $aProductImages = array_unique($aProductImages);
        $aImages = array_merge($aProductImages,$aImages);
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
        $vRelativePath = "/catalog/$vTarget";
        if ($vTarget=='product'){
            $aImages = $this->getImages();
        }
        elseif($vTarget=='category'){
            $aImages = $this->getCategoryImagesList();
        }
        elseif($vTarget=='aligent_blog'){
            $aImages = $this->getAligentBlogImages();
            $vRelativePath = "";
        }
        elseif ($vTarget == 'fish_pig_banner') {
            $aImages = $this->getFishPigBannerImages();
            $vRelativePath = "";
        }
        elseif($vTarget=='widget_instances'){
            $aImages = $this->getWidgetImages();
            $vRelativePath = "";
        }
        else{
            throw new Exception('target not correct: ' . $vTarget);
        }

        $vBasePath = Mage::getBaseDir('media') . '/' . ltrim("$vRelativePath",'/');
        $vBasePath = rtrim($vBasePath,'/');
        $aMissingImages = $aExistingList;
        if (!$aImages){
            return $aMissingImages;
        }
        foreach ($aImages as $vImage) {
            $vImage = '/' . ltrim($vImage,'/');
            if (in_array($vImage, ['/no_selection', '/'])) {
                continue;
            }
            $vFullPath = $vBasePath . $vImage;
            if (!file_exists($vFullPath)) {
                $this->_iImageCount++;
                if ($this->_bUseAria2c) {
                    $vRelativeLTrim = ltrim($vRelativePath,'/');
                    $aMissingImages[] = $this->_vRemoteBaseUrl . $vRelativePath . $vImage;
                    $aMissingImages[] = "   out=" . trim("media/" . $vRelativeLTrim,'/') . $vImage;
                }
                else {
                    $aMissingImages[] = "/media/$vRelativePath" . $vImage;
                }
            }
        }
        return $aMissingImages;
    }
    protected function getAligentBlogImages()
    {
        try{
            $aImage = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchCol("select value from mageblog_post_varchar where value like 'wysiwyg/%'");
        }
        catch(Exception $e){
            return[];
        }
        $aImage = array_unique($aImage);
        return $aImage;
    }

    protected function getFishPigBannerImages()
    {
        try {
            $aImageName = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchCol("select distinct image from ibanners_banner WHERE is_enabled=1");
            $aImageName2 = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchCol("select distinct image2 from ibanners_banner WHERE is_enabled=1 ");
            $aImageName = array_merge($aImageName, $aImageName2);
            $aImageName = array_unique($aImageName);
            //remove empty value
            $aImageName = array_filter($aImageName);
            $aImage = [];
            foreach ($aImageName as $imagePath) {
                foreach (['std', 'retina', 'mobile'] as $vImageType) {
                    $aImage[] = "ibanners/$vImageType{$imagePath}";
                }
            }
        } catch (Exception $e) {
            return [];
        }
        $aImage = array_unique($aImage);
        return $aImage;
    }
    protected function getWidgetImages()
    {
        $aImages = [];
        $aWidgetParameters = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchCol("select widget_parameters from widget_instance where widget_parameters like '%wysiwyg/%'");
        foreach ($aWidgetParameters as $vParameters) {
            $aParams = unserialize($vParameters);
            if (!empty($aParams['image'])){
                $aImages[] = $aParams['image'];
            }
        }
        return $aImages;
    }

    public function downloadImages()
    {
        $aImages = $this->getMissingImages('product',[]);
        $aImages = $this->getMissingImages('category',$aImages);
        $aImages = $this->getMissingImages('aligent_blog',$aImages);
        $aImages = $this->getMissingImages('widget_instances',$aImages);
        $aImages = $this->getMissingImages('fish_pig_banner', $aImages);

        if (!$aImages){
            echo "no missing images \n";
            return ;
        }
        echo $this->_iImageCount  . " missing images to download\n";
        $vPath = $this->writeImages($aImages);
        $vRemoteBaseUrl = $this->_vRemoteBaseUrl;


        if ($this->_bUseAria2c) {
            $vCommand = "aria2c  -i $vPath --max-concurrent-downloads=10";
        }
        else {
            $vCommand = "wget -P . -r -i $vPath --cut-dirs=1 --base=$vRemoteBaseUrl";
        }


        echo "execute following:\n";
        if (realpath(getcwd()) !=
            ($vBaseDir = realpath(Mage::getBaseDir()))){
            echo "cd $vBaseDir\n";
        }
        echo "$vCommand\n";
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