<?php
/**
 * Magendoo_Shell extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the MIT License available through the world-wide-web at this URL:
 * http://opensource.org/licenses/mit-license.php
 *
 * @category    Magendoo
 * @package     Magendoo_Shell
 * @copyright   Copyright (c) Magendoo Interactive <http://magendoo.ro>,<https://github.com/magendooro>
 * @license     http://opensource.org/licenses/mit-license.php MIT License
 *
 */

require_once 'abstract.php';

/**
 * Remove / export products 
 *
 * @category    Magendoo
 * @package     Magendoo_Shell
 * @author      Emil [carco] Sirbu <emil.sirbu+magento[at]gmail[dot]com>
 */

class Magendoo_Shell_Exportskus extends Mage_Shell_Abstract
{


    protected $_batchSize = 100;

    /**
     * Run script
     *
     */
    public function run()
    {
    
            if(empty($this->_args)) {
                $this->croak($this->usageHelp());
            }
            
            $media = $this->getArg('images');
            $sku   = $this->getArg('skus');
            
            if(!$media && !$sku) {
               $this->croak($this->usageHelp());
            }
            unset($this->_args['images']);
            unset($this->_args['skus']);

    
            
            $fields = array();
          
            foreach($this->_args as $key=>$value) {
                $fields[$key] = $value;
            }

           
            Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
            $collection =  Mage::getResourceModel('catalog/product_collection');
            $collection->addAttributeToSelect('name');
                
            if($fields) {
                foreach($fields as $field=>$val) {
                    if(strpos($val,'%') !== false) {
                        $collection->addAttributeToFilter($field,array('like'=>$val));
                    } else {
                        $collection->addAttributeToFilter($field,$val);
                    } 
                }
            }            
                        
            $offsetProducts = 0;
            
            if(!$media) {
                fputcsv(STDOUT,array('sku','name'));
            } 
            
            $export = array();
            
            while (true) {
            
                ++$offsetProducts;
                
                $collection->setPage($offsetProducts, $this->_batchSize);
               
                if ($collection->getCurPage() < $offsetProducts) {
                    break;
                }
                $collection->load();
                                
                //echo $collection->getSelect(),PHP_EOL;
                if ($collection->count() == 0) {
                    break;
                }             
                if($media) {
                    $this->addMediaGallery($collection);
                }
                
                foreach($collection as $product) {
                    if($media) {
                        $images = (array)$product->getImages();
                        foreach($images as $image) {
                            echo $image,PHP_EOL;
                        }
                    } else {
                        $row = array($product->getSku(),$product->getName());
                        fputcsv(STDOUT,$row);
                    } 
                }
                $collection->clear();
            }
            return $this;
    }
    
    
    protected function addMediaGallery($productCollection) {
    
        //$ids = $productCollection->getAllIds(); //this will load all ids (need limit/offset to be set)
        $ids = array(); 
        $products = array();
        foreach($productCollection as $product) {
            $ids[] = $product->getId();
            $products[$product->getId()] = $product;
        }
        if(!$ids) return false;
        
       
        $table   = $productCollection->getTable(Mage_Catalog_Model_Resource_Product_Attribute_Backend_Media::GALLERY_TABLE);
        $adapter = $productCollection->getConnection();
        
        $select =  $adapter->select()->from($table)->where('entity_id IN (?)',$ids);
        $rows = $adapter->query($select)->fetchAll();

        foreach($rows as $row) {
            $product = $products[$row['entity_id']];
            
            $images = (array)$product->getData('images');
            if($row['value']) {
                $images[] = $row['value'];
            }
            $product->setData('images',$images);
        }
        //echo $select.PHP_EOL;
        
        
        
        
    }


    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:
    php -f export.php [--attr1 val1 --attr2 val2] [--images|--skus]
        --skus           Export sku,name csv (could be used in Import to delete products)
        --images         Export images
The script export sku,name (if --sku) or images (if --media) for products with attr1 = val1 AND attr2 = val2; If you want to use LIKE condition, put % in val

Example
    To get a list with images:
        php exportskus.php --name 'My%' --images | sort -u 
    To get a csv with sku,name:
        php exportskus.php --name 'My%' --skus > skutodelete.csv


USAGE;
    }
    
    
    protected function croak($msg,$errCode = 255) {
        fwrite(STDERR, $msg."\n");
        $errCode = intval($errCode);
        if($errCode<=0) {
            $errCode = 255;
        }
        exit($errCode);
    }
}

$shell = new Magendoo_Shell_Exportskus();
$shell->run();
