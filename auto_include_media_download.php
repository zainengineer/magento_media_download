<?php
/**
 * cd zain_custom/project_custom/auto_include
 * ln -s /var/www/vhosts/exp/gits/magento_media_download/auto_include_media_download.php MediaCustomDownload.php
 *
 * create project_custom/media_download_config.php
 * add content like
 *
 * $this->_vRemoteBaseUrl = 'http://www.example.com/media';
$this->_bOnlyInStock = true;
$this->_bOnlyEnabled = true;
$this->_bLimitProducts = false;
 *
 */
if (!isset($_GET['op']) || ($_GET['op'] != 'media_download')) {
    return;
}

require_once "/var/www/vhosts/exp/gits/magento_media_download/MediaDownload.php";
class MediaCustomDownload extends MediaDownload
{

    protected function initializeMagento()
    {
        \ZainPrePend\MageInclude\includeMage();
    }
    public function __construct()
    {
        /**
         * has content like
         *
         * $this->_vRemoteBaseUrl = 'http://www.project.com/media';
        $this->_bOnlyInStock = false;
        $this->_bOnlyEnabled = false;
        $this->_bLimitProducts = false;
         *
         */
        $vCustomConfig = AUTO_PREPEND_BASE_PATH_Z . '/project_custom/media_download_config.php';
        if (file_exists($vCustomConfig)){
            require_once $vCustomConfig;
        }
        parent::__construct();
    }
}
$mediaDownload = new MediaCustomDownload();
echo "<pre>";
$mediaDownload->downloadImages();
echo "</pre>";

\ZainPrePend\lib\T::printr('completed',true,'');
die;