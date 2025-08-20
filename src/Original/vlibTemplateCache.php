<?php

declare(strict_types=1);

namespace Redbird\vlib5\Original;

/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2002 Active Fish Group                                 |
// +----------------------------------------------------------------------+
// | Authors: Kelvin Jones <kelvin@kelvinjones.co.uk>                     |
// +----------------------------------------------------------------------+
//
// $Id: cache.php,v 1.4 2003/05/15 14:17:41 releasedj Exp $

/**
 * Class uses all of vlibTemplate's functionality but caches the template files.
 * It creates an identical tree structure to your filesystem but with cached files.
 *
 * @author Kelvin Jones <kelvin@kelvinjones.co.uk>
 * @since 22/02/2002
 * @package vLIB
 * @access public
 */
class vlibTemplateCache extends vlibTemplate
{

    /*-----------------------------------------------------------------------------\
    |     DO NOT TOUCH ANYTHING IN THIS CLASS, IT MAY NOT WORK OTHERWISE           |
    \-----------------------------------------------------------------------------*/

    public bool $_cache = true;     // tells vlibTemplate that we're caching
    public ? string $_cachefile = null;     // full path to current cache file (even if it doesn't yet exist)
    public bool $_cacheexists = false;   // has this file been cached before

    //public $_cachefilelocked; // is this file currently locked whilst writing
    //public $_cachefiledir;  // dir of current cache file
    public bool $_clearcache = false;


    /**
     * FUNCTION: clearCache
     * will unset a file, and set $this->_cacheexists to 0.
     *
     * @access public
     * @return bool
     */
    public function clearCache() : bool
    {
        $this->_clearcache = true;
        return true;
    }

    /**
     * FUNCTION: recache
     * alias for clearCache().
     *
     * @access public
     * @return bool
     */
    public function recache() : bool
    {
        return $this->clearCache();
    }

    /**
     * FUNCTION: setCacheLifeTime
     * sets the lifetime of the cached file
     *
     * @param int|null $int $int number of seconds to set lifetime to
     * @return bool
     * @access public
     */
    public function setCacheLifeTime(int $int = null) : bool
    {
        if ($int == null || !is_int($int)) return false;
        if ($int == 0) $int = 60;
        if ($int == -1) $int = 157680000; // set to 5 yrs time
        $this->OPTIONS['CACHE_LIFETIME'] = $int;
        return true;
    }

    /**
     * FUNCTION: setCacheExtension
     * sets the extention of the cache file
     *
     * @param string|null $str |null $str name of new cache extention
     * @return bool
     * @access public
     */
    public function setCacheExtension(string|null $str = null): bool
    {
        if ($str == null || !preg_match('/^[a-z0-9]+$/', strtolower($str))) return false;
        $this->OPTIONS['CACHE_EXTENSION'] = strtolower($str);
        return true;
    }


    /*----------------------------------------\
              Private Functions
    -----------------------------------------*/

    /**
     * FUNCTION: _checkCache
     * checks if there's a cache, if there is then it will read the cache file as the template.
     */
    public function _checkCache(string $tmplfile): bool
    {
        $this->_cachefile = $this->_getFilename($tmplfile);
        if ($this->_clearcache) {
            if (file_exists($this->_cachefile)) unlink($this->_cachefile);
            return false;
        }

        if (file_exists($this->_cachefile)) {
            $this->_cacheexists = true;

            // if it's expired
            if ((filemtime($this->_cachefile) + $this->OPTIONS['CACHE_LIFETIME']) < date('U')
                || filemtime($this->_cachefile) < filemtime($tmplfile)) {
                $this->_cacheexists = false;
                return false; // so that we know to recache
            } else {
                return true;
            }

        } else {
            $this->_cacheexists = false;
            return false;
        }
    }


    /**
     * FUNCTION: _getFilename
     * gets the full pathname for the cached file
     *
     */
    public function _getFilename(string $tmplfile): string
    {
        return $this->OPTIONS['CACHE_DIRECTORY'] . '/' . md5('vlibCachestaR' . realpath($tmplfile)) . '.' . $this->OPTIONS['CACHE_EXTENSION'];
    }

    /**
     * FUNCTION: _createCache
     * creates the cached file
     *
     */
    public function _createCache($data) : bool
    {
        $cache_file = $this->_cachefile;
        if (!$this->_prepareDirs($cache_file)) return false; // prepare all of the directories

        $f = fopen($cache_file, 'w');
        flock($f, 2); // set an EXclusive lock
        if (!$f) vlibTemplateError::raiseError('VT_ERROR_NO_CACHE_WRITE', KILL, $cache_file);
        fputs($f, $data); // write the parsed string from vlibTemplate
        flock($f, 3); // UNlock file
        fclose($f);
        touch($cache_file);
        chmod($cache_file, $this->OPTIONS['CACHE_CHMOD']);

        return true;
    }

    /**
     * FUNCTION: _prepareDirs
     * prepares the directory structure
     *
     */
    public function _prepareDirs($file)
    {
        if (empty($file)) die('no filename'); //do error in future
        $filepath = dirname($file);
        if (is_dir($filepath)) return true;

        $dirs = preg_split('[\\/]', $filepath);
        $currpath = '';
        foreach ($dirs as $dir) {
            $currpath .= $dir . '/';
            $type = @filetype($currpath);

            ($type == 'link') and $type = 'dir';
            if ($type != 'dir' && !empty($type)) {
                vlibTemplateError::raiseError('VT_ERROR_WRONG_CACHE_TYPE', KILL, 'directory: ' . $currpath . ', type: ' . $type);
            }
            if ($type == 'dir') {
                continue;
            } else {
                $s = @mkdir($currpath, $this->OPTIONS['CACHE_CHMOD']);
                chmod($currpath, $this->OPTIONS['CACHE_CHMOD']);
                if (!$s) vlibTemplateError::raiseError('VT_ERROR_CACHE_MKDIR_FAILURE', KILL, 'directory: ' . $currpath);
            }
        }
        return true;
    }

}