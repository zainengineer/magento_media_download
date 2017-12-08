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
        elseif (method_exists($this, $vTarget)) {
            $aImages = $this->$vTarget();
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
            $vImageFile = $vImage;
            //remove ? param from file path to save
            if ($iPos = strpos($vImageFile, '?')) {
                $vImageFile = substr($vImageFile, 0, $iPos);
            }
            $vFullPath = $vBasePath . $vImageFile;
            if (!file_exists($vFullPath)) {
                $this->_iImageCount++;
                if ($this->_bUseAria2c) {
                    $vRelativeLTrim = ltrim($vRelativePath,'/');
                    $aMissingImages[] = $this->_vRemoteBaseUrl . $vRelativePath . $vImage;
                    $aMissingImages[] = "   out=" . trim("media/" . $vRelativeLTrim, '/') . $vImageFile;
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
        return $this->safeFetchColumn("select value from mageblog_post_varchar where value like 'wysiwyg/%'");
    }
    protected function getPlaceHolderImages()
    {
        return $this->safeFetchColumn("select CONCAT('catalog/product/placeholder/',value) from core_config_data where path ='catalog/placeholder/image_placeholder'");
    }
    protected function multiTypeImage($aImageName, $aTypeList, $vFolder)
    {
        $aImage = [];
        foreach ($aImageName as $imagePath) {
            foreach ($aTypeList as $vImageType) {
                $aImage[] = "$vFolder/$vImageType{$imagePath}";
            }
        }
        return $aImage;
    }
    protected function getAligentLookImages()
    {
        $aImages =  $this->safeFetchColumn("SELECT image FROM collections_look where active=1");
        $aImages = $this->multiTypeImage($aImages,['resized/','large/','original/'],'collections');
        return $aImages;
    }
    protected function safeFetchColumn($vSql)
    {
        try{
            $aImage = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchCol($vSql);
        }
        catch(Exception $e){
            return[];
        }
        $aImage = array_unique($aImage);
        return $aImage;
    }

    protected function getFeatureBanners()
    {
        $aAllRows = $this->safeGetAllRows("select * from net_afeature where active = 1");
        return $this->multipleImageAttribute($aAllRows, [
            'image_url'        => 'afeature/main/',
            'mobile_image_url' => 'afeature/mobile/',
            'retina_image_url' => 'afeature/main/']);
    }

    protected function safeGetAllRows($vSql)
    {
        try {
            $aAllRows = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchAll($vSql);
        } catch (Exception $e) {
            return [];
        }
        return $aAllRows;
    }

    protected function multipleImageAttribute($aAllRows, $aAttributeList)
    {
        $aAllImages = [];
        foreach ($aAllRows as $aSingleRow) {
            foreach ($aAttributeList as $vAttributeName => $vPrefix) {
                if (!empty($aSingleRow[$vAttributeName])) {
                    $aAllImages[] = $vPrefix . $aSingleRow[$vAttributeName];
                }
            }
        }
        return array_unique($aAllImages);
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
    protected function getStaticBlockImages()
    {
        $aContent = $this->safeFetchColumn("select content from cms_block where is_active =1");
        return $this->getImagesFromContentList($aContent);
    }
    protected function getImagesFromContentList($aContent)
    {
        $aImages = [];
        foreach ($aContent as $vContent) {
            $aMatches = [];
            preg_match_all( '@image_url *="([^"]+)"@' , $vContent, $aMatches);
            $aImages = array_merge($aMatches[1], $aImages);
            $aWMatch = array();
            preg_match_all("/wysiwyg(.*?)(.*?)\"/", $vContent, $aWMatch);
            if (!empty($aWMatch[0])) {
                $aActualMatch = $aWMatch[0];
                $aActualMatch = array_map(function ($input) {
                    return trim(trim(trim($input, '"'), "');"));
                }, $aActualMatch);
                $aImages = array_merge($aActualMatch, $aImages);
            }
            $aImages = array_unique($aImages);
        }
        return $aImages;
    }
    protected function getMegaMenuImages()
    {
        $aContent = $this->safeFetchColumn("select header from megamenu where status=1;");
        return $this->getImagesFromContentList($aContent);
    }
    protected function getWidgetImages()
    {
        $aImages = [];
        $aWidgetParameters = Mage::getSingleton('core/resource')->getConnection('core_read')->fetchCol("select widget_parameters from widget_instance where widget_parameters like '%wysiwyg/%'");
        foreach ($aWidgetParameters as $vParameters) {
            $aParams = @unserialize($vParameters) ?: [];
            if (!empty($aParams['image'])){
                $aImages[] = $aParams['image'];
            }
        }
        return $aImages;
    }

    public function downloadImages()
    {
        $aImages = [];
        $aFunctionList = [
            'product',
            'category',
            'aligent_blog',
            'widget_instances',
            'fish_pig_banner',
            'getAligentLookImages',
            'getStaticBlockImages',
            'getMegaMenuImages',
            'getFeatureBanners',
            'getPlaceHolderImages',
        ];
        foreach ($aFunctionList as $vFunction) {
            $aImages = $this->getMissingImages($vFunction,$aImages);
        }

        if (!$aImages){
            echo "no missing images \n";
            $this->writeImages([]);
            return ;
        }
        $vImageCount = number_format($this->_iImageCount);
        echo $vImageCount  . " missing images to download\n";
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