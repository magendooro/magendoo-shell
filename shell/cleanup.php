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

require_once dirname(__FILE__).'/abstract.php';

/**
 * Magendoo Cleanup Catalog Flat / CatalogSearch fulltext tables
 *
 * @category    Magendoo
 * @package     Magendoo_Shell
 * @author      Emil [carco] Sirbu <emil.sirbu+magento[at]gmail[dot]com>
 */

class Magendoo_Shell_Cleanup extends Magendoo_Shell_Abstract
{

    /**
     * Run script
     *
     */
    public function run()
    {

            if($this->getArg('info') || $this->getArg('dry-run')) {
                $doCleanup = false;
            } elseif($this->getArg('cleanup')) {
                $doCleanup = true;
            } else {
                $this->croak($this->usageHelp());
            }


            $storeIDs = array_keys(Mage::app()->getStores($withDefault=false,$codekey=false));

            echo Mage::helper('core')->__('Cleanup flat/catalogsearch tables, valid stores: %s',implode(', ',$storeIDs)),PHP_EOL;

            if(!$doCleanup) {
                echo Mage::helper('core')->__('DRY-RUN/INFO MODE, nothing will be removed, use --cleanup for this.'),PHP_EOL;
            }

            //found name of flat tables
            $templateProduct  = str_replace('999','([0-9]+)',Mage::getResourceModel('catalog/product_flat')->getFlatTableName('999'));
            $templateCategory = str_replace('999','([0-9]+)',Mage::getResourceModel('catalog/category_flat')->getMainStoreTable('999'));

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');

            $tables     = $adapter->listTables();
            $flatTables = array();
            $toRemove   = array();

            foreach($tables as $table) {
                if($templateProduct && preg_match('/'.$templateProduct.'$/i',$table,$match)) {
                    $flatTables[$table]=$match[1];
                    if(!in_array($match[1],$storeIDs)) {
                        $toRemove[] = $table;
                    }
                }
                if($templateCategory && preg_match('/'.$templateCategory.'$/i',$table,$match)) {
                    $flatTables[$table]=$match[1];
                    if(!in_array($match[1],$storeIDs)) {
                        $toRemove[] = $table;
                    }
                }
            }

            if($flatTables) {
                echo Mage::helper('core')->__('Found flat tables: %s',implode(', ',array_keys($flatTables)));
                if($toRemove) {
                    echo PHP_EOL,Mage::helper('core')->__('Remove %s tables... ',implode(', ',$toRemove));

                    if($doCleanup) {
                        try {
                            foreach($toRemove as $table) {
                                $adapter->dropTable($table);
                            }
                            echo 'DONE!';
                        } catch(Exception $e) {
                            $this->croak(', FAIL!'.PHP_EOL.$e->getMessage());
                        }
                    } else {
                        echo Mage::helper('core')->__('Nothing removed!');
                    }
                } else {
                    echo Mage::helper('core')->__(', Nothing to remove!');
                }
            } else {
                echo Mage::helper('core')->__('No  catalog_(category|product)_flat tables found, nothing to remove!');
            }
            echo PHP_EOL;

            //cleanup catalogsearch_fulltext table (this is MyISAM and does not have foreign keys)
            //If use this module*, this is not required because catalogsearch_fulltext table will be truncated before reindexAll
            //*https://github.com/magendooro/magento-fulltext-reindex

            $engine = Mage::helper('catalogsearch')->getEngine();
            echo Mage::helper('core')->__('Clean catalogsearch/fulltext table');
            if($engine && ($engine instanceof Mage_CatalogSearch_Model_Resource_Fulltext_Engine)) {

                try {
                    $searchTable = $engine->getMainTable();
                    $where = $adapter->quoteInto('store_id NOT IN (?)', $storeIDs);
                    echo Mage::helper('core')->__(', delete all records where %s...',$where);
                    if($doCleanup) {
                        $adapter->delete($searchTable,$where);
                        echo 'DONE!';
                    } else {
                        $cnt = $adapter->fetchOne("SELECT COUNT(*) FROM `$searchTable` WHERE $where");
                        echo Mage::helper('core')->__('%s records to delete. Nothing deleted!',$cnt);
                    }
                } catch(Exception $e) {
                    $this->croak(', FAIL!'.PHP_EOL.$e->getMessage());
                }
                //echo get_class($engine->getWriteAdapter());
            } else {
                echo Mage::helper('core')->__('... NOT Mage_CatalogSearch_Model_Resource_Fulltext_Engine, do nothing!');
            }
            echo PHP_EOL;

            return $this;
    }


    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:
    php -f cleanup.php [--info] [--cleanup]
        --info | --dry-run    - List not (more) used catalog_(category|store)_flat tables,
        --cleanup             - Cleanup tables:
                                    drop not (more) used catalog_(category|store)_flat tables,
                                    delete orphan records from catalogsearch_(fulltext|results)

To reindex catalog_(category|product)_flat and catalogsearch_fulltext, use Magento indexer.php script:
    php indexer.php --reindex catalog_category_flat
    php indexer.php --reindex catalog_product_flat
    php indexer.php --reindex catalogsearch_fulltext


USAGE;
    }
}

$shell = new Magendoo_Shell_Cleanup();
$shell->run();
