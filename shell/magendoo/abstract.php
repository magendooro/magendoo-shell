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
 * @copyright   Copyright (c) Magendoo Interactive <http://magendoo.ro>, <https://github.com/magendooro>
 * @license     http://opensource.org/licenses/mit-license.php MIT License
 *
 */


require_once dirname(__FILE__).'/../abstract.php';

/**
 * Magendoo Abstract Shell Script
 *
 * @category    Magendoo
 * @package     Magendoo_Shell
 * @author      Emil [carco] Sirbu <emil.sirbu+magento[at]gmail[dot]com>
 */

class Magendoo_Shell_Abstract extends Mage_Shell_Abstract
{

    /**
     * Run script
     *
     */
    public function run()
    {
        $this->croak('This file cannot be run');
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

