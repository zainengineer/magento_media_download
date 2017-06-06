<?php
require_once dirname(__FILE__). "/MediaDownload.php";
class MediaCustomDownload extends MediaDownload
{
    protected $_vFullMagentoPath = 'your_mage_path';
    protected $_vRemoteBaseUrl = 'http://www.example.com/media';
}

if (($_SERVER['SCRIPT_FILENAME'] === __FILE__) ||
    (strpos(__FILE__, $_SERVER['SCRIPT_FILENAME']))
) {
    $mediaDownload = new MediaCustomDownload();
    $mediaDownload->downloadImages();
}