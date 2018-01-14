<?php
/**
 * Emagedev extension for Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/BSD-3-Clause
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@emagedev.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade
 * the Omedrec Welcome module to newer versions in the future.
 * If you wish to customize the Omedrec Welcome module for your needs
 * please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright  Copyright (C) Emagedev, LLC (https://www.emagedev.com/)
 * @license    https://opensource.org/licenses/BSD-3-Clause     New BSD License
 */

/**
 * @category   Emagedev
 * @package    Emagedev_CacheControl
 * @subpackage Model
 * @author     Dmitry Burlakov <dantaeusb@icloud.com>
 */

/**
 * Class Emagedev_CacheControl_Block_Html_Head
 *
 * Append crc hashsum codes to scripts and styles to prevent customer browser caching
 */
class Emagedev_CacheControl_Block_Html_Head extends Mage_Page_Block_Html_Head
{
    /**
     * Append hash like %path%/%file%.%hash%.%ext%
     */
    const METHOD_VERSION = 'version';

    /**
     * Append hash like %path%/%file%.%ext%?%hash%
     */
    const METHOD_QUERY = 'query';

    /**
     * @var bool Check is cache for this block is enabled
     */
    protected $_cacheEnabled;

    /**
     * Version method recommended by RFC
     *
     * @var string Method which used to append hash
     */
    protected $method = self::METHOD_VERSION;

    /**
     * Get magento cache instance
     *
     * @return Mage_Core_Model_Cache
     */
    protected function _getMagentoCache()
    {
        return Mage::app()->getCache();
    }

    /**
     * Get cache key for hashed url
     *
     * @param string $name
     * @param string $type
     *
     * @return string
     */
    protected function _getCacheName($name, $type = 'tag')
    {
        return implode('_', array('head_tag', $type, $name));
    }

    protected function _isBlockCacheEnabled()
    {
        if (is_null($this->_cacheEnabled)) {
            $this->_cacheEnabled = Mage::app()->useCache(
                Mage_Core_Block_Abstract::CACHE_GROUP
            );
        }
        return $this->_cacheEnabled;
    }

    /**
     * Merge static and skin files of the same format into 1 set of HEAD directives or even into 1 directive
     *
     * Will attempt to merge into 1 directive, if merging callback is provided. In this case it will generate
     * filenames, rather than render urls.
     * The merger callback is responsible for checking whether files exist, merging them and giving result URL
     *
     * @param string   $format      - HTML element format for sprintf('<element src="%s"%s />', $src, $params)
     * @param array    $staticItems - array of relative names of static items to be grabbed from js/ folder
     * @param array    $skinItems   - array of relative names of skin items to be found in skins according to design config
     * @param callback $mergeCallback
     *
     * @return string
     */
    protected function &_prepareStaticAndSkinElements(
        $format, array $staticItems, array $skinItems,
        $mergeCallback = null
    ) {
        $designPackage = Mage::getDesign();
        $baseJsUrl = Mage::getBaseUrl('js');
        $items = array();
        if ($mergeCallback && !is_callable($mergeCallback)) {
            $mergeCallback = null;
        }

        // get static files from the js folder, no need in lookups
        foreach ($staticItems as $params => $rows) {
            foreach ($rows as $name) {
                $items[$params][] = $mergeCallback ?
                    Mage::getBaseDir() . DS . 'js' . DS . $name
                    : $baseJsUrl . $this->_appendJsFileHashsum($name);
            }
        }

        // lookup each file basing on current theme configuration
        foreach ($skinItems as $params => $rows) {
            foreach ($rows as $name) {
                $items[$params][] = $mergeCallback
                    ? $designPackage->getFilename(
                        $name, array('_type' => 'skin')
                    )
                    : $this->_appendSkinFileHashsum($name);
            }
        }

        $html = '';
        foreach ($items as $params => $rows) {
            // attempt to merge
            $mergedUrl = false;
            if ($mergeCallback) {
                $mergedUrl = call_user_func($mergeCallback, $rows);
                $mergedUrl = $this->_appendHashToMergedFile(
                    $mergedUrl, $rows, $mergeCallback[1]
                );
            }
            // render elements
            $params = trim($params);
            $params = $params ? ' ' . $params : '';
            if ($mergedUrl) {
                $html .= sprintf($format, $mergedUrl, $params);
            } else {
                foreach ($rows as $src) {
                    $html .= sprintf($format, $src, $params);
                }
            }
        }
        return $html;
    }

    /**
     * Append crc32 hashsum to js files
     *
     * @param string $name
     *
     * @return string
     */
    protected function _appendJsFileHashsum($name)
    {
        $cache = $this->_getMagentoCache();
        $cacheKey = $this->_getCacheName('js', $name);

        if ($this->_isBlockCacheEnabled()) {
            $url = $cache->load($cacheKey);
            if ($url) {
                return $url;
            }
        }

        $baseJsDir = Mage::getBaseDir() . DS . 'js';
        $file = $baseJsDir . DS . $name;
        $url = $this->_appendFileHashToUrl($name, $file);

        if ($this->_isBlockCacheEnabled()) {
            $cache->save(
                $url, $cacheKey, array(Mage_Core_Block_Abstract::CACHE_GROUP)
            );
        }

        return $url;
    }

    /**
     * Append crc32 hashsum to skin js/css
     *
     * @param string $name
     *
     * @return string
     */
    protected function _appendSkinFileHashsum($name)
    {
        $cache = $this->_getMagentoCache();
        $cacheKey = $this->_getCacheName('skin', $name);

        if ($this->_isBlockCacheEnabled()) {
            $url = $cache->load($cacheKey);
            if ($url) {
                return $url;
            }
        }

        $designPackage = Mage::getDesign();
        $url = $designPackage->getSkinUrl($name);
        $file = $designPackage->getFilename(
            trim($name, DS), array('_type' => 'skin')
        );
        $url = $this->_appendFileHashToUrl($url, $file);

        if ($this->_isBlockCacheEnabled()) {
            $cache->save(
                $url, $cacheKey, array(Mage_Core_Block_Abstract::CACHE_GROUP)
            );
        }

        return $url;
    }

    /**
     * Get merged file, append crc32 hashsum to url
     *
     * @param string $url   merged file url
     * @param string $files files to merge
     * @param string $mergeCallbackFunction
     *
     * @return string
     */
    protected function _appendHashToMergedFile(
        $url, $files, $mergeCallbackFunction
    ) {
        $cache = $this->_getMagentoCache();
        $cacheKey = $this->_getCacheName('url', $url);

        if ($this->_isBlockCacheEnabled()) {
            $hashedUrl = $cache->load($cacheKey);
            if ($hashedUrl) {
                return $hashedUrl;
            }
        }

        if (strpos($mergeCallbackFunction, 'Js') != false) {
            $type = 'js';
            $extension = '.js';
        } else {
            if (strpos($mergeCallbackFunction, 'Css') != false) {
                $isSecure = Mage::app()->getRequest()->isSecure();
                $type = $isSecure ? 'css_secure' : 'css';
                $extension = '.css';
            } else {
                return $url;
            }
        }

        $baseMediaUrl = Mage::getBaseUrl('media', $isSecure);
        $hostname = parse_url($baseMediaUrl, PHP_URL_HOST);
        $port = parse_url($baseMediaUrl, PHP_URL_PORT);

        $targetFilename
            = md5(implode(',', $files) . "|{$hostname}|{$port}") . $extension;
        $file = Mage::getBaseDir('media') . DS . $type . DS . $targetFilename;
        $hashedUrl = $this->_appendFileHashToUrl($url, $file);

        if ($this->_isBlockCacheEnabled()) {
            $cache->save(
                $hashedUrl, $cacheKey,
                array(Mage_Core_Block_Abstract::CACHE_GROUP)
            );
        }

        return $hashedUrl;
    }

    /**
     * Append crc32 hash of $file as parameter to $url
     *
     * @param string $url
     * @param string $file
     *
     * @return string
     */
    protected function _appendFileHashToUrl($url, $file)
    {
        if (!file_exists($file)) {
            return $url;
        }

        $hash = hash_file('crc32', $file);
        return $this->_appendHashToUrl($url, $hash);
    }

    /**
     * Append $hash as parameter to $url
     *
     * @param $url
     * @param $hash
     *
     * @return string
     */
    protected function _appendHashToUrl($url, $hash)
    {
        if ($this->method == self::METHOD_QUERY) {
            return $hash ? sprintf('%s?%s', $url, $hash) : $url;
        } else {
            $matches = array();
            preg_match('/^(?<uri>.*)(?<ext>\.[\d\w]{2,5})(?<query>$|\?.*)$/i', $url, $matches);
            return $matches['uri'] . '.' . $hash . $matches['ext'] . $matches['query'];
        }
    }
} 