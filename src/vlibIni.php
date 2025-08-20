<?php

declare(strict_types=1);

namespace Redbird\vlib5;

// +------------------------------------------------------------------------+
// | PHP version 5.x, tested with 5.1.4, 5.1.6, 5.2.6                       |
// +------------------------------------------------------------------------+
// | Copyright (c) 2002-2008 Kelvin Jones, Claus van Beek, Stefan Deussen   |
// +------------------------------------------------------------------------+
// | Authors: Kelvin Jones, Claus van Beek, Stefan Deussen                  |
// +------------------------------------------------------------------------+

/*
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
; This file contains configuration parametres for use  ;
; with the vLIB library.                               ;
;                                                      ;
; vLIB uses this file so that for future releases, you ;
; will not have to delve through all the php script    ;
; again to set your specific variable/properties .etc  ;
;                                                      ;
; ---------------------------------------------------- ;
; ATTENTION: Do NOT remove any variable given in the   ;
; configurations below as they will probably still be  ;
; needed by vLIB. If you do not need a variable simply ;
; let it be.                                           ;
;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;
*/

//if (!defined('vlibIniClassLoaded'))
//{
//	define('vlibIniClassLoaded', 1);
//
//	/**
//	 * vlibIni is a class used to store configuration parameters
//	 * for the vLIB library.
//	 *
//	 * @since 2002-07-21
//	 * @package vLIB
//	 * @access private
//	 */

/**
 *
 * 2025 Edgars Strods
 *
 * This class is a highly modified version of the original.
 * Now this acts as a singleton object class that sets global
 * vlibTamplate configuration.
 *
 * Call `vlibIni::setup(['TEMPLATE_DIR'=>'/path/to/tmpl/dir',...]);`
 * before any `new vlibTemplate('tmpl.html')` or `vlibTemplateCache('tmpl.html');`
 *
 *  In this case you do not have to call shared options for every template like
 *  `new vlibTemplate('template.html',['TEMPLATE_DIR'=>'/path/to/tmpl/dir',...]);`
 * but can use simple `new vlibTemplate('template.html');`
 *
 *  Of course any setting can be overridden in a single template call:
 *  `new vlibTemplateCache('template.html',['TEMPLATE_DIR'=>'/different/tmpl/dir','CACHE_LIFETIME'=>20]);`
 *
 *  If any global vlibIni values need to be updated later call
 * `vlibIni::setConfig([...])`.
 *
 */
final class vlibIni
{
    protected static ? self $instance = null;

    /**
     *
     * Main function that creates this singleton object
     * Sets configuration
     *
     * @param array $data
     * @return self|null
     * @throws \Exception
     */
    public static function setup(array $data): ?vlibIni
    {
        //Bacwards compatibality
        if (!defined('vlibIniClassLoaded')) {define('vlibIniClassLoaded', 1);}

        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        self::setConfig($data);

        return self::$instance;
    }

    protected array $config = [];

    /**
     * Default vlib settings
     * @return array
     */
    public static function getDefaultSettings() : array {

        return [

            'TEMPLATE_DIR' => null, // Default directory for your template files (full path) leave the '/' or '\' off the end of the directory.

            'MAX_INCLUDES' => 2, // Drill depth for tmpl_include's

            'GLOBAL_VARS' => 1, // if set to 1, any variables not found in a loop will search for a global var as well

            'GLOBAL_CONTEXT_VARS' => 1, // if set to 1, vlibTemplate will add global vars (__SELF__, __REQUEST_URI__, __PARSE_TIME__) reflecting the environment.

            'LOOP_CONTEXT_VARS' => 1, // if set to 1, vlibTemplate will add loop specific vars (see dokumentation) on each row of the loop.

            'SET_LOOP_VAR' => 1, // Sets a global variable for each top level loops

            'DEFAULT_ESCAPE' => 'html', // 1 of the following: html, url, sq, dq, none

            'STRICT' => 0, // Dies when encountering an incorrect tmpl_* style tags i.e. tmpl_vae

            'CASELESS' => 0, // Removes case sensitivity on all variables

            'UNKNOWNS' => 'print', // How to handle unknown variables.
            // One of the following: ignore, remove, leave, print, comment

            'TIME_PARSE' => '0', // Will enable you to time how long vlibTemplate takes to parse your template. You then use the function: getParseTime().

            'ENABLE_PHPINCLUDE' => '0', // Will allow template to include a php file using <TMPL_PHPINCLUDE>

            'ENABLE_SHORTTAGS' => '0', // Will allow you to use short tags in your script i.e.: <VAR name="my_var">, <LOOP name="my_loop">...</LOOP>


            /**
             * the following are only used by the vlibTemplateCache class.
             **/

            'CACHE_DIRECTORY' => sys_get_temp_dir(),
            // Directory where the cached filesystem
            // will be set up (full path, and must be writable)
            // '/' or '\' off the end of the directory.

            'CACHE_LIFETIME' => 604800, // Duration until file is re-cached in seconds (604800 = 1 week)
            'CACHE_CHMOD'    => 0775,

            'CACHE_EXTENSION' => 'vtc', // extention to be used by the cached file i.e. index.php will become index.vtc (vlibTemplate Compiled)

            'DEBUG_WITHOUT_JAVASCRIPT' => 0, // if set to 1, the external debug window won't be displayed and the debug output is placed below every template output.


            /**
             * This is added in "Version 5"
             */
            'LOOP_NOT_ARRAY_OVERRIDE' => true, // If not an array is used in ->SetLoop, then empty array is used instead.
        ];
    }

    /**
     * Updates vlibIni configuration
     * @param array $configArray
     */
    public static function setConfig(array $configArray) : void
    {
        self::$instance->config = [
            ...self::getConfig(),
            ...$configArray
        ];
    }

    /**
     * Returns current configuration array
     *
     * @return array
     */
    public static function getConfig(): array
    {
        if(!empty(self::$instance->config)) {
            return self::$instance->config;
        }
        else
        {
            return self::getDefaultSettings();
        }
    }

    /**
     * config vars for vlibTemplate
     *
     * @param array $options
     * @return array
     */
    public static function vlibTemplate(array $options = []): array
    {
        return [ ...self::getConfig(), ...$options ];
    }

}
