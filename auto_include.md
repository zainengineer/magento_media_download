    cd zain_custom
    mkdir -p project_custom/auto_include
    cp /var/www/vhosts/exp/gits/magento_media_download/auto_include_config_sample.php project_custom/media_download_config.php
    cd project_custom/auto_include
    ln -s /var/www/vhosts/exp/gits/magento_media_download/auto_include_media_download.php MediaCustomDownload.php
    vi ../media_download_config.php