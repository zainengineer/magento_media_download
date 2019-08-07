## Magento Media Download

### Installation
* Needs `aria2c`
#### Ubuntu
* On Ubuntu`sudo apt-get install aria2`

#### Centos  
* On centos can install like `sudo yum --enablerepo=rpmforge install aria2 -y`
* Alternatively `wget` can be used but you will need to move files manually and it can't do parallel downloads. So will take much longer
* if rpm repo fails. try following to enable it
    rpm -Uvh http://download.fedoraproject.org/pub/epel/6/x86_64/epel-release-6-8.noarch.rpm
    sudo yum update
       
#### Instructions
* copy `MediaDownload.php` into root of your project or symlink it
* edit `$_vRemoteBaseUrl` to enter full media base url in php variable
* execute `php MediaDownload.php`
* It will generate list and give you command to download files
* Alternatively set `$_vFullMagentoPath` to absolute path of `app/Mage.php` and then run `php MediaDownload.php` 
