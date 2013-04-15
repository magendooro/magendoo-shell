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
 * Magendoo Delete website/group/store script
 *
 * @category    Magendoo
 * @package     Magendoo_Shell
 * @author      Emil [carco] Sirbu <emil.sirbu+magento[at]gmail[dot]com>
 */

class Magendoo_Shell_Delete extends  Magendoo_Shell_Abstract
{
    /**
     * Run script
     *
     */
    public function run()
    {

        $doneSomething = false;

        if($this->getArg('list') || empty($this->_args)) {
                $doneSomething = true;
                $collection = Mage::getModel('core/website')
                    ->getCollection()
                    ->joinGroupAndStore();

                $oldWebsite = null;

                echo PHP_EOL,'Website (#id,code)',PHP_EOL;
                echo '  ',str_pad('Group (#id)',30,' ',STR_PAD_RIGHT),'Store (Website view) (#id)',PHP_EOL;
                echo str_repeat('-',60),PHP_EOL;

                foreach($collection as $item) {
                    if($item->getWebsiteId() != $oldWebsite) {
                        echo PHP_EOL,$item->getName(),' (#',$item->getWebsiteId(),', ',$item->getCode(),')',PHP_EOL;
                        $oldWebsite = $item->getWebsiteId();
                    }
                    if($item->getGroupId()) {
                        echo '  ',str_pad($item->getGroupTitle().' (#'.$item->getGroupId().')',30,' ',STR_PAD_RIGHT);
                    } else {
                        echo '  ',str_pad('No store groups',30,' ',STR_PAD_RIGHT);
                    }
                    if($item->getStoreId()) {
                        echo $item->getStoreTitle(),' (#'.$item->getStoreId(),')';
                    }
                    echo PHP_EOL;
                }

                if(empty($this->_args)) {
                    echo PHP_EOL,PHP_EOL,$this->usageHelp(),PHP_EOL;

                }
                exit(0);
        }


        $cnt = 0;
        $type   = null;
        $title  = null;
        $itemId = null;
        if($this->getArg('website'))    { $cnt++; $type = 'website'; $title = 'Website'; $itemId = $this->getArg('website'); $modelName = 'core/website';}
        if($this->getArg('group'))      { $cnt++; $type = 'group';   $title = 'Group';   $itemId = $this->getArg('group');   $modelName = 'core/store_group';}
        if($this->getArg('store'))      { $cnt++; $type = 'store';   $title = 'Store';   $itemId = $this->getArg('store');   $modelName = 'core/store';}


        if($cnt > 1) {
            $this->croak(Mage::helper('core')->__('Use only ONE type: --store <id> or --website <id> or --group <id>'));
        }

        if($type) {
            if(!is_numeric($itemId) || $itemId<=0) {
                $this->croak(Mage::helper('core')->__('Invalid id given, use --%s <id>, where id is positive integer',$type));
            }
            $model = Mage::getModel($modelName)->load($itemId);
            if(!$model || !$model->getId()) {
                $this->croak(Mage::helper('core')->__('Invalid id given, %s #%s does not exists. Use --list to view all websites/stores',$title,$itemId));
            }
            if(!$model->isCanDelete()) {
                $this->croak(Mage::helper('core')->__('%s #%s cannot be deleted',$title,$itemId));
            }
        }


        if($this->getArg('backupdb') || $this->getArg('backup') || $this->getArg('backup-db')) {

            if(!$this->getArg('force')) {
                $this->croak(Mage::helper('core')->__('Mage::Backup is very slow, please use mysqldump or backup.sh script or add --force argument'));
            }

            $doneSomething = true;
            $path = Mage::getBaseDir('var') . DS . 'backups';
            $time = time();
            echo Mage::helper('core')->__('Backup database into %s...',$path.'/'.$time.'_db.gz');
            try {
                $backupDb = Mage::getModel('backup/db');
                $backup   = Mage::getModel('backup/backup')
                    ->setTime($time)
                    ->setType('db')
                    ->setPath($path);
                $backupDb->createBackup($backup);
                echo 'DONE, '.Mage::helper('backup')->__('Database was successfuly backed up.').PHP_EOL;
            } catch (Exception $e) {
                    $this->croak('FAIL!'.PHP_EOL.$e->getMessage());
            }
        }


        if($model) {
            $doneSomething = true;
            echo Mage::helper('core')->__('Delete %s #%s...',$title,$model->getId());
            try {
                $model->delete();
                echo 'DONE, '.Mage::helper('core')->__('The %s has been deleted. (use --list to view remaining stores)',$title).PHP_EOL;
                echo Mage::helper('core')->__('Now, use php cleanup.php to remove orphan catalog_(category|product)_flat tables / catalogsearch_fulltext records').PHP_EOL;
            } catch (Exception $e) {
                $this->croak('FAIL!'.PHP_EOL.$e->getMessage());
            }
        }

        if($this->getArg('cleanup') || $this->getArg('clean') || $this->getArg('clean-up')) {
            echo Mage::helper('core')->__("cleanup is moved into cleanup.php script, use php cleanup.php for this"),PHP_EOL;
        }

        if(!$doneSomething) {
            $this->croak(Mage::helper('core')->__('No valid argument found').PHP_EOL.$this->usageHelp());
        }

        return $this;

    }

    protected function croak($msg,$errCode = 255) {
        fwrite(STDERR, $msg."\n");
        $errCode = intval($errCode);
        if($errCode<=0) {
            $errCode = 255;
        }
        exit($errCode);
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:
    php -f delete.php --list --(website|group|store) <id> --backupdb --cleanup

        --list                  - Show all websites/stores
        --(website|group|store) - Delete store OR website OR website group with <id> ID (exclusive OR)
        --backupdb [--force]    - Backup database before delete store,website or group
                                  !!! Very slow operation, use mysqldump instead of Mage::Backup.

All args are optional. <id> is required when website/group/store is specified.

USAGE;
    }
}

$shell = new Magendoo_Shell_Delete();
$shell->run();
