<?php

declare(strict_types=1);

namespace Redbird\vlib5\Original;

use Redbird\vlib5\vlibIni;

// +------------------------------------------------------------------------+
// | PHP version 5.x, tested with 5.1.4, 5.1.6, 5.2.6                       |
// +------------------------------------------------------------------------+
// | Copyright (c) 2002-2008 Kelvin Jones, Claus van Beek, Stefan Deussen   |
// +------------------------------------------------------------------------+
// | Authors: Kelvin Jones, Claus van Beek, Stefan Deussen                  |
// +------------------------------------------------------------------------+


//// check to avoid multiple including of class
//if (!defined('vlibTemplateClassLoaded'))
//{
//	define('vlibTemplateClassLoaded', 1);
//
//	/**
//	 * vlibTemplate is a class used to seperate PHP and HTML.
//	 *
//	 * @since 2002-03-07
//	 * @package vLIB
//	 * @access public
//	 */

/**
 * vLibTemplate class modified to support PHP8
 */
class vlibTemplate
{
    /*-----------------------------------------------------------------------------\
    | ATTENTION: Do not touch the following variables. vlibTemplate                |
    | will not work otherwise.                                                     |
    \-----------------------------------------------------------------------------*/

    public array $OPTIONS = [
        'MAX_INCLUDES' => 2,
        'TEMPLATE_DIR' => null,
        'GLOBAL_VARS' => null,
        'GLOBAL_CONTEXT_VARS' => null,
        'LOOP_CONTEXT_VARS' => null,
        'SET_LOOP_VAR' => null,
        'DEFAULT_ESCAPE' => null,
        'STRICT' => null,
        'CASELESS' => null,
        'UNKNOWNS' => null,
        'TIME_PARSE' => null,
        'ENABLE_PHPINCLUDE' => FALSE,
        'ENABLE_SHORTTAGS' => null,
        'INCLUDE_PATHS' => [],
        'CACHE_DIRECTORY' => null,
        'CACHE_LIFETIME' => null,
        'CACHE_EXTENSION' => null,
        'DEBUG_WITHOUT_JAVASCRIPT' => 0,
    ];

    /** open and close tags used for escaping */
    public array $ESCAPE_TAGS = [
        'html' => ['open' => 'htmlspecialchars(', 'close' => ', ENT_QUOTES)'],
        'url' => ['open' => 'urlencode(', 'close' => ')'],
        'rawurl' => ['open' => 'rawurlencode(', 'close' => ')'],
        'sq' => ['open' => 'addcslashes(', 'close' => ", \"'\")"],
        'dq' => ['open' => 'addcslashes(', 'close' => ", '\"')"],
        '1' => ['open' => 'htmlspecialchars(', 'close' => ', ENT_QUOTES)'],
        '0' => ['open' => '', 'close' => ''],
        'none' => ['open' => '', 'close' => ''],
        'hex' => ['open' => '$this->_escape_hex(', 'close' => ', false)'],
        'hexentity' => ['open' => '$this->_escape_hex(', 'close' => ', true)']
    ];

    /** open and close tags used for formatting */
    public array $FORMAT_TAGS = [
        'uc' => ['open' => 'strtoupper(', 'close' => ')'],
        'lc' => ['open' => 'strtolower(', 'close' => ')'],
        'ucfirst' => ['open' => 'ucfirst(', 'close' => ')'],
        'lcucfirst' => ['open' => 'ucfirst(strtolower(', 'close' => '))'],
        'ucwords' => ['open' => 'ucwords(', 'close' => ')'],
        'lcucwords' => ['open' => 'ucwords(strtolower(', 'close' => '))']
    ];

    /** operators allowed when using extended TMPL_IF syntax */
    public array $allowed_if_ops = ['==', '!=', '<>', '<', '>', '<=', '>='];

    /** dbs allowed by vlibTemplate::setDbLoop(). */
    public array $allowed_loop_dbs = [
        'MYSQL',
        'POSTGRESQL',
        //'INFORMIX',
        'INTERBASE',
        //'INGRES',
        'MSSQL',
        //'MSQL',
        //'OCI8',
        //'ORACLE',
        //'OVRIMOS',
        //'SYBASE'
    ];

    /** root directory of vlibTemplate automagically filled in */
    public ? string $VLIBTEMPLATE_ROOT = null;

    /** contains current directory used when doing recursive include */
    public array $_currentincludedir = [];

    /** current depth of includes */
    public int $_includedepth = 0;

    /** full path to tmpl file */
    public ? string $_tmplfilename = null;

    /** file data before it's parsed */
    public $_tmplfile = null;

    /** parsed version of file, ready for eval()ing */
    public $_tmplfilep = null;

    /** eval()ed version ready for printing or whatever */
    public $_tmploutput = null;

    /** array for variables to be kept */
    public array $_vars = [];

    /** array where loop variables are kept */
    public array $_arrvars = [];

    /** array which holds the current namespace during parse */
    public array $_namespace = [];

    /** variable is set to true once the template is parsed, to save re-parsing everything */
    public bool $_parsed = false;

    /** array holds all unknowns vars */
    public array $_unknowns = [];

    /** microtime when template parsing began */
    public ? float $_firstparsetime = null;

    /** total time taken to parse template */
    public ? float $_totalparsetime = null;

    /** name of current loop being passed in */
    public ? array $_currloopname = null;

    /** rows with the above loop */
    public array $_currloop = [];

    /** define vars to avoid warnings */
    public bool $_debug = false;
    public bool $_cache = false;


    /*-----------------------------------------------------------------------------\
    |                               public methods                                 |
    \-----------------------------------------------------------------------------*/

    /**
     * METHOD: newTemplate
     *
     * Usually called by the class constructor.
     * Stores the filename in $this->_tmplfilename.
     * Raises an error if the template file is not found.
     *
     * @param string $tmplfile full path to template file
     * @return bool true
     * @access public
     */
    public function newTemplate(string $tmplfile): bool
    {
        if (!$tfile = $this->_fileSearch($tmplfile)) {
            vlibTemplateError::raiseError('VT_ERROR_NOFILE', KILL, $tmplfile);
        }

        // make sure that any parsing vars are cleared for the new template
        $this->_tmplfile = null;
        $this->_tmplfilep = null;
        $this->_tmploutput = null;
        $this->_parsed = false;
        $this->_unknowns = [];
        $this->_firstparsetime = null;
        $this->_totalparsetime = null;

        // reset debug module
        if ($this->_debug) {
            $this->_debugReset();
        }

        $this->_tmplfilename = $tfile;

        return true;
    }

    /**
     * METHOD: setVar
     *
     * Sets variables to be used by the template
     * If $k is an array, then it will treat it as an associative array
     * using the keys as variable names and the values as variable values.
     *
     * @param mixed $k key to define variable name
     * @param mixed|null $v variable to assign to $k
     * @return bool true/false
     * @access public
     */
    public function setVar(mixed $k, mixed $v = null): bool
    {
        if (is_array($k)) {
            foreach ($k as $key => $value) {
                $key = ($this->OPTIONS['CASELESS']) ? strtolower(trim($key)) : trim($key);
                if (preg_match('/^[A-Za-z_]+[A-Za-z0-9_]*$/', $key) && $value !== null) {
                    $this->_vars[$key] = $value;
                }
            }
            return true;
        }
        elseif (preg_match('/^[A-Za-z_]+[A-Za-z0-9_]*$/', $k) && $v !== null) {

            if ($this->OPTIONS['CASELESS']) $k = strtolower($k);
            {
                $this->_vars[trim($k)] = $v;
                return true;
            }
        }

        return false;
    }

    /**
     * METHOD: unsetVar
     *
     * Unsets a variable which has already been set
     * Parse in all vars wanted for deletion in seperate parametres
     *
     * @param string ...$varNames var name to remove use: vlibTemplate::unsetVar(var[, var..])
     * @return bool true/false returns true unless called with 0 params
     * @access public
     */
    public function unsetVar(string ...$varNames) : bool
    {
        if (is_array($varNames) && count($varNames) > 0) {
            foreach ($varNames as $var) {
                if ($this->OPTIONS['CASELESS']) {
                    $var = strtolower($var);
                }
                if (!preg_match('/^[A-Za-z_]+[A-Za-z0-9_]*$/', $var)) continue;
                unset($this->_vars[$var]);
            }
        }
        return true;
    }

    /**
     * METHOD: getVars
     *
     * Gets all vars currently set in global namespace.
     *
     * @return array|null
     * @access public
     */
    public function getVars() : ? array
    {
        return (!empty($this->_vars)) ? $this->_vars : null;
    }

    /**
     * METHOD: getVar
     *
     * Gets a single var from the global namespace
     *
     * @access public
     */
    public function getVar($var) : mixed
    {
        if ($this->OPTIONS['CASELESS']) $var = strtolower($var);
        if (empty($var) || !isset($this->_vars[$var])) return false;
        return $this->_vars[$var];
    }

    /**
     * METHOD: setContextVars
     *
     * sets the GLOBAL_CONTEXT_VARS
     *
     * @return true
     * @access public
     */
    public function setContextVars() : bool
    {
        $_phpself = @$GLOBALS['HTTP_SERVER_VARS']['PHP_SELF'];
        $_pathinfo = @$GLOBALS['HTTP_SERVER_VARS']['PATH_INFO'];
        $_request_uri = @$GLOBALS['HTTP_SERVER_VARS']['REQUEST_URI'];
        $_qs = @$GLOBALS['HTTP_SERVER_VARS']['QUERY_STRING'];

        // the following fixes bug of $PHP_SELF on Win32 CGI and IIS.
        $_self = (!empty($_pathinfo)) ? $_pathinfo : $_phpself;
        $_uri = (!empty($_request_uri)) ? $_request_uri : $_self . '?' . $_qs;

        $this->setVar('__SELF__', $_self);
        $this->setVar('__REQUEST_URI__', $_uri);
        return true;
    }

    /**
     * METHOD: setLoop
     *
     * Builds the loop construct for use with <TMPL_LOOP>.
     *
     * @param string $k string to define loop name
     * @param array $v array to assign to $k
     * @return bool true/false
     * @access public
     */
    public function setLoop($k, $v) : bool
    {
        if (is_array($v) && preg_match('/^[A-Za-z_]+[A-Za-z0-9_]*$/', $k)) {
            $k = ($this->OPTIONS['CASELESS']) ? strtolower(trim($k)) : trim($k);
            $this->_arrvars[$k] = [];
            if ($this->OPTIONS['SET_LOOP_VAR'] && !empty($v)) $this->setVar($k, 1);
            if (($this->_arrvars[$k] = $this->_arrayBuild($v)) == false) {
                vlibTemplateError::raiseError('VT_WARNING_INVALID_ARR', WARNING, $k);
            }
        }
        return true;
    }

    /**
     * METHOD: setDbLoop [** EXPERIMENTAL **]
     *
     * Function to create a loop from a Db result resource link.
     *
     * @param string $loopname to commit loop. If not set, will use last loopname set using newLoop()
     * @param string $result link to a Db result resource or to a Db result object
     * @param string $db_type , type of db that the result resource belongs to.
     * @return bool true/false
     * @access public
     */
    public function setDbLoop(string $loopname, mixed $result, string $db_type = 'MYSQL') : bool
    {
        $db_type = strtoupper($db_type);
        if (!in_array($db_type, $this->allowed_loop_dbs)) {
            vlibTemplateError::raiseError('VT_WARNING_INVALID_LOOP_DB', WARNING, $db_type);
            return false;
        }

        $loop_arr = [];
        switch ($db_type) {

            case 'MYSQL':
                if (is_object($result)) {
                    if (get_class($result) != 'mysqli_result') {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_CLASS', WARNING, $db_type);
                        return false;
                    }
                    while ($r = $result->fetch_assoc()) {
                        $loop_arr[] = $r;
                    }
                } else {
                    if (get_resource_type($result) != 'mysql result') {
                        vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                        return false;
                    }
                    while ($r = mysqli_fetch_assoc($result)) {
                        $loop_arr[] = $r;
                    }
                }
                break;

            case 'POSTGRESQL':
                if (get_resource_type($result) != 'pgsql result') {
                    vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                    return false;
                }

                $nr = (function_exists('pg_num_rows')) ? pg_num_rows($result) : pg_numrows($result);

                for ($i = 0; $i < $nr; $i++) {
                    $loop_arr[] = pg_fetch_array($result, $i, PGSQL_ASSOC);
                }
                break;



            case 'INTERBASE':
                if (get_resource_type($result) != 'interbase result') {
                    vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                    return false;
                }
                while ($r = ibase_fetch_row($result)) {
                    $loop_arr[] = $r;
                }
                break;

            case 'MSSQL':
                if (get_resource_type($result) != 'mssql result') {
                    vlibTemplateError::raiseError('VT_WARNING_INVALID_RESOURCE', WARNING, $db_type);
                    return false;
                }
                while ($r = sqlsrv_fetch_array($result)) {
                    $loop_arr[] = $r;
                }
                break;

            case 'INFORMIX':
            case 'INGRES':
            case 'MSQL':
            case 'OCI8':
            case 'ORACLE':
            case 'OVRIMOS':
            case 'SYBASE':
                    throw new \Exception('This db connection ('.$db_type.') is not supported in this vLib modification for now', E_ERROR);
        }
        $this->setLoop($loopname, $loop_arr);
        return true;
    }

    /**
     * METHOD: newLoop
     *
     * Sets the name for the curent loop in the 3 step loop process.
     *
     * @param string $name string to define loop name
     * @return bool true/false
     * @access public
     */
    public function newLoop($loopname)
    {
        if (preg_match('/^[a-z_]+[a-z0-9_]*$/i', $loopname)) {
            $this->_currloopname[$loopname] = $loopname;
            $this->_currloop[$loopname] = [];
            return true;
        } else {
            return false;
        }
    }

    /**
     * METHOD: addRow
     *
     * Adds a row to the current loop in the 3 step loop process.
     *
     * @param array $row loop row to add to current loop
     * @param string $loopname loop to which you want to add row, if not set will use last loop set using newLoop().
     * @return bool true/false
     * @access public
     */
    public function addRow($row, $loopname = null)
    {
        if (!$loopname) $loopname = end($this->_currloopname);

        if (!isset($this->_currloop[$loopname]) || empty($this->_currloopname)) {
            vlibTemplateError::raiseError('VT_WARNING_LOOP_NOT_SET', WARNING);
            return false;
        }
        if (is_array($row)) {
            $this->_currloop[$loopname][] = $row;
            return true;
        } else {
            return false;
        }
    }

    /**
     * METHOD: addLoop
     *
     * Completes the 3 step loop process. This assigns the rows and resets
     * the variables used.
     *
     * @param string $loopname to commit loop. If not set, will use last loopname set using newLoop()
     * @return bool true/false
     * @access public
     */
    public function addLoop($loopname = null)
    {
        if ($loopname == null) { // add last loop used
            if (!empty($this->_currloop)) {
                foreach ($this->_currloop as $k => $v) {
                    $this->setLoop($k, $v);
                    unset($this->_currloop[$k]);
                }
                $this->_currloopname = [];
                return true;
            } else {
                return false;
            }
        } elseif (!isset($this->_currloop[$loopname]) || empty($this->_currloopname)) { // newLoop not yet envoked
            vlibTemplateError::raiseError('VT_WARNING_LOOP_NOT_SET', WARNING);
            return false;
        } else { // add a specific loop
            $this->setLoop($loopname, $this->_currloop[$loopname]);
            unset($this->_currloopname[$loopname], $this->_currloop[$loopname]);
        }
        return true;
    }

    /**
     * METHOD: getLoop
     *
     * Use this function to return the loop structure. This is useful in setting
     * inner loops using the 3-step-loop process.
     *
     * @param string $loopname name of loop to get
     * @return bool true/false
     * @access public
     */
    public function getLoop($loopname = null)
    {
        if (!$loopname) $loopname = end($this->_currloopname);

        if (!isset($this->_currloop[$loopname]) || empty($this->_currloopname)) {
            vlibTemplateError::raiseError('VT_WARNING_LOOP_NOT_SET', WARNING);
            return false;
        }

        $loop = $this->_currloop[$loopname];
        unset($this->_currloopname[$loopname], $this->_currloop[$loopname]);
        return $loop;
    }

    /**
     * METHOD: unsetLoop
     *
     * Unsets a loop which has already been set.
     * Can only unset top level loops.
     *
     * @param string loop to remove use: vlibTemplate::unsetLoop(loop[, loop..])
     * @return bool true/false returns true unless called with 0 params
     * @access public
     */
    public function unsetLoop()
    {
        $num_args = func_num_args();
        if ($num_args < 1) return false;

        for ($i = 0; $i < $num_args; $i++) {
            $var = func_get_arg($i);
            if ($this->OPTIONS['CASELESS']) $var = strtolower($var);
            if (!preg_match('/^[A-Za-z_]+[A-Za-z0-9_]*$/', $var)) continue;
            unset($this->_arrvars[$var]);
        }
        return true;
    }


    /**
     * METHOD: reset
     *
     * Resets the vlibTemplate object. After using vlibTemplate::reset() you must
     * use vlibTemplate::newTemplate(tmpl) to reuse, not passing in the options array.
     *
     * @return bool true
     * @access public
     */
    public function reset()
    {
        $this->clearVars();
        $this->clearLoops();
        $this->_tmplfilename = null;
        $this->_tmplfile = null;
        $this->_tmplfilep = null;
        $this->_tmploutput = null;
        $this->_parsed = false;
        $this->_unknowns = [];
        $this->_firstparsetime = null;
        $this->_totalparsetime = null;
        $this->_currloopname = null;
        $this->_currloop = [];
        return true;
    }

    /**
     * METHOD: clearVars
     *
     * Unsets all variables in the template
     *
     * @return bool true
     * @access public
     */
    public function clearVars() : bool
    {
        $this->_vars = [];
        return true;
    }

    /**
     * METHOD: clearLoops
     *
     * Unsets all loops in the template
     *
     * @return bool true
     * @access public
     */
    public function clearLoops() : bool
    {
        $this->_arrvars = [];
        $this->_currloopname = null;
        $this->_currloop = [];
        return true;
    }

    /**
     * METHOD: clearAll
     *
     * Unsets all variables and loops set using setVar/Loop()
     *
     * @return bool true
     * @access public
     */
    public function clearAll() : bool
    {
        $this->clearVars();
        $this->clearLoops();
        return true;
    }

    /**
     * METHOD: unknownsExist
     *
     * Returns true if unknowns were found after parsing.
     * Function MUST be called AFTER one of the parsing functions to have any relevance.
     *
     * @return bool true/false
     * @access public
     */
    public function unknownsExist() : bool
    {
        return (!empty($this->_unknowns));
    }

    /**
     * METHOD: unknowns
     *
     * Alias for unknownsExist.
     *
     * @access public
     */
    public function unknowns(): bool
    {
        return $this->unknownsExist();
    }

    /**
     * METHOD: getUnknowns
     *
     * Returns an array of all unknown vars found when parsing.
     * This function is only relevant after parsing a document.
     *
     * @return array
     * @access public
     */
    public function getUnknowns(): array
    {
        return $this->_unknowns;
    }

    /**
     * METHOD: setUnknowns
     *
     * Sets how you want to handle variables that were found in the
     * template but not set in vlibTemplate using vlibTemplate::setVar().
     *
     * @param string $arg ignore, remove, print, leave or comment
     * @return bool
     * @access public
     */
    public function setUnknowns($arg)
    {
        $arg = strtolower(trim($arg));
        if (preg_match('/^ignore|remove|print|leave|comment$/', $arg)) {
            $this->OPTIONS['UNKNOWNS'] = $arg;
            return true;
        }
        return false;
    }

    /**
     * METHOD: setPath
     *
     * function sets the paths to use when including files.
     * Use of this function: vlibTemplate::setPath(string path [, string path, ..]);
     * i.e. if $tmpl is your template object do: $tmpl->setPath('/web/htdocs/templates','/web/htdocs/www');
     * with as many paths as you like.
     * if this function is called without any arguments, it will just delete any previously set paths.
     *
     * @param string path (mulitple)
     * @return bool success
     * @access public
     */
    public function setPath()
    {
        $num_args = func_num_args();
        if ($num_args < 1) {
            $this->OPTIONS['INCLUDE_PATHS'] = [];
            return true;
        }
        for ($i = 0; $i < $num_args; $i++) {
            $thispath = func_get_arg($i);
            $this->OPTIONS['INCLUDE_PATHS'][] = realpath($thispath);
        }
        return true;
    }

    /**
     * METHOD: getParseTime
     *
     * After using one of the parse functions, this will allow you
     * access the time taken to parse the template.
     * see OPTION 'TIME_PARSE'.
     *
     * @return float time taken to parse template
     * @access public
     */
    public function getParseTime()
    {
        if ($this->OPTIONS['TIME_PARSE'] && $this->_parsed) {
            return $this->_totalparsetime;
        }
        return false;
    }


    /**
     * METHOD: fastPrint
     *
     * Identical to pparse() except that it uses output buffering w/ gz compression thus
     * printing the output directly and compressed if poss.
     * Will possibly if parsing a huge template.
     *
     * @access public
     * @return bool true/false
     */
    public function fastPrint()
    {
        $ret = $this->_parse('ob_gzhandler');
        print($this->_tmploutput);
        return $ret;
    }


    /**
     * METHOD: pparse
     *
     * Calls parse, and then prints out $this->_tmploutput
     *
     * @access public
     * @return bool true/false
     */
    public function pparse()
    {
        if (!$this->_parsed) $this->_parse();
        print($this->_tmploutput);
        return true;
    }

    /**
     * METHOD: pprint
     *
     * Alias for pparse()
     *
     * @access public
     */
    public function pprint()
    {
        return $this->pparse();
    }


    /**
     * METHOD: grab
     *
     * Returns the parsed output, ready for printing, passing to mail() ...etc.
     * Invokes $this->_parse() if template has not yet been parsed.
     *
     * @access public
     * @return bool true/false
     */
    public function grab()
    {
        if (!$this->_parsed) $this->_parse();
        return $this->_tmploutput;
    }

    /*-----------------------------------------------------------------------------\
    |						   private functions								  |
    \-----------------------------------------------------------------------------*/

    /**
     * METHOD: PHP5 now "__construct" (PHP4: vlibTemplate)
     *
     * vlibTemplate constructor.
     * if $tmplfile has been passed to it, it will send to $this->newTemplate()
     *
     * @param string|null $tmplfile full path to template file
     * @param array $options see above
     * @access private
     */
    public function __construct(string $tmplfile = null, array $options = [])
    {
        $this->VLIBTEMPLATE_ROOT = dirname(realpath(__FILE__));

        foreach (vlibIni::vlibTemplate($options) as $key => $val) {
            $this->OPTIONS[$key] = $val;
            if (strtoupper($key) == 'PATH') {
                $this->setPath($val);
            }
        }

        if ($tmplfile) {
            $this->newTemplate($tmplfile);
        }

        if ($this->OPTIONS['GLOBAL_CONTEXT_VARS']) {
            $this->setContextVars();
        }
    }

    /** METHOD: _getData
     *
     * function returns the text from the file, or if we're using cache, the text
     * from the cache file. MUST RETURN DATA.
     * @param string $tmplfile contains path to template file
     * @param bool $do_eval used for included files. If set then this function must do the eval()'ing.
     * @access private
     * @return mixed data/string or boolean
     */
    public function _getData(string $tmplfile, bool $do_eval = false): mixed
    {
        // check the current file depth
        if ($this->_includedepth > $this->OPTIONS['MAX_INCLUDES'] || $tmplfile == false) {
            return false;
        } else {
            if ($this->_debug) $this->_debugIncludedfiles[] = $tmplfile;
            if ($do_eval) {
                $this->_currentincludedir[] = dirname($tmplfile);
                $this->_includedepth++;
            }
        }


        if ($this->_cache && $this->_checkCache($tmplfile)) { // cache exists so lets use it
            $data = fread($fp = fopen($this->_cachefile, 'r'), filesize($this->_cachefile));
            fclose($fp);
        } else { // no cache lets parse the file
            $data = fread($fp = fopen($tmplfile, 'r'), filesize($tmplfile));
            fclose($fp);

            // "<?xml" creates "Parse error!"
            $data = str_replace('<?xml', '<div style="margin-top: 20px; margin-bottom: 20px; font-size: 2.5em;"><strong>vLIB:</strong> Use "setVar()" to use &quot;&lt;?xml&quot; ...</div>', $data);

            // check for PHP-Tags
            $data = str_replace('<?php', '<div style="margin-top: 40px; margin-bottom: 40px; font-size: 2.5em;"><strong>vLIB:</strong> PHP is not allowed within the template ...</div>', $data);
            $data = str_replace('<?=', '<div style="margin-top: 40px; margin-bottom: 40px; font-size: 2.5em;"><strong>vLIB:</strong> PHP is not allowed within the template ...</div>', $data);
            $data = str_replace('<?', '<div style="margin-top: 40px; margin-bottom: 40px; font-size: 2.5em;"><strong>vLIB:</strong> PHP is not allowed within the template ...</div>', $data);

            $regex = '/(<|<\/|{|{\/|<!--|<!--\/){1}\s*';
            $regex .= '(?:tmpl_)';
            if ($this->OPTIONS['ENABLE_SHORTTAGS']) $regex .= '?'; // makes the TMPL_ bit optional
            $regex .= '(var|if|elseif|else|endif|unless|endunless|loop|endloop|include|comment|endcomment)\s*';
            $regex .= '(?:';
            $regex .= '(?:';
            $regex .= '(name|format|escape|op|value|file)';
            $regex .= '\s*=\s*';
            $regex .= ')?';
            $regex .= '(?:[\"\'])?';
            $regex .= '((?<=[\"\'])';
            $regex .= '[^\"\']*|[a-z0-9_\.]*)';
            $regex .= '[\"\']?';
            $regex .= ')?\s*';
            $regex .= '(?:';
            $regex .= '(?:';
            $regex .= '(name|format|escape|op|value)';
            $regex .= '\s*=\s*';
            $regex .= ')';
            $regex .= '(?:[\"\'])?';
            $regex .= '((?<=[\"\'])';
            $regex .= '[^\"\']*|[a-z0-9_\.]*)';
            $regex .= '[\"\']?';
            $regex .= ')?\s*';
            $regex .= '(?:';
            $regex .= '(?:';
            $regex .= '(name|format|escape|op|value)';
            $regex .= '\s*=\s*';
            $regex .= ')';
            $regex .= '(?:[\"\'])?';
            $regex .= '((?<=[\"\'])';
            $regex .= '[^\"\']*|[a-z0-9_\.]*)';
            $regex .= '[\"\']?';
            $regex .= ')?\s*';
            $regex .= '(?:>|\/>|}|-->){1}';
            $regex .= '/ie';
            $data = preg_replace_callback(substr($regex,0,strlen($regex)-1),
                function($matches) {
                    return $this->_parseTag($matches);
                },$data);

            if ($this->_cache) { // add cache if need be
                $this->_createCache($data);
            }
        }

        // now we must parse the $data and check for any <tmpl_include>'s
        if ($this->_debug) $this->doDebugWarnings(file($tmplfile), $tmplfile);

        if ($do_eval) {
            $success = @eval('?>' . $data . '<?php return 1;');
            $this->_includedepth--;
            array_pop($this->_currentincludedir);
            return $success;
        } else {
            return $data;
        }

    }

    /**
     * METHOD: _fileSearch
     *
     * Searches for all possible instances of file { $file }
     *
     * @param string $file path of file we're looking for
     * @access private
     * @return mixed fullpath to file or boolean false
     */
    public function _fileSearch($file)
    {
        $filename = basename($file);
        $filepath = dirname($file);

        // check fullpath first..
        $fullpath = $filepath . '/' . $filename;
        if (is_file($fullpath)) return $fullpath;

        // ..then check for relative path for current directory..
        if (!empty($this->_currentincludedir)) {
            $currdir = $this->_currentincludedir[(count($this->_currentincludedir) - 1)];
            $relativepath = realpath($currdir . '/' . $filepath . '/' . $filename);
            if ($relativepath && is_file($relativepath)) {
                $this->_currentincludedir[] = dirname($relativepath);
                return $relativepath;
            }
        }

        // ..then check for relative path for all additional given paths..
        if (!empty($this->OPTIONS['INCLUDE_PATHS'])) {
            foreach ($this->OPTIONS['INCLUDE_PATHS'] as $currdir) {
                $relativepath = realpath($currdir . '/' . $filepath . '/' . $filename);
                if (is_file($relativepath)) {
                    return $relativepath;
                }
            }
        }

        // ..then check path from TEMPLATE_DIR..
        if (!empty($this->OPTIONS['TEMPLATE_DIR'])) {
            $fullpath = realpath($this->OPTIONS['TEMPLATE_DIR'] . '/' . $filepath . '/' . $filename);
            if (is_file($fullpath)) return $fullpath;
        }

        // ..then check relative path from executing php script..
        $fullpath = realpath($filepath . '/' . $filename);
        if ($fullpath && is_file($fullpath)) return $fullpath;

        // ..then check path from template file.
        if (!empty($this->VLIBTEMPLATE_ROOT)) {
            $fullpath = realpath($this->VLIBTEMPLATE_ROOT . '/' . $filepath . '/' . $filename);
            if (is_file($fullpath)) return $fullpath;
        }

        return false; // uh oh, file not found
    }

    /**
     * METHOD: _arrayBuild
     *
     * Modifies the array $arr to add Template variables, __FIRST__, __LAST__ ..etc
     * if $this->OPTIONS['LOOP_CONTEXT_VARS'] is true.
     * Used by $this->setloop().
     *
     * @param array $arr
     * @return array|bool new look array
     * @access private
     */
    public function _arrayBuild(array $arr) : array|bool
    {
        if (is_array($arr) && !empty($arr)) {
            $arr = array_values($arr); // to prevent problems w/ non sequential arrays
            for ($i = 0; $i < count($arr); $i++) {
                if (!is_array($arr[$i])) return false;
                foreach ($arr[$i] as $k => $v) {
                    unset($arr[$i][$k]);
                    if ($this->OPTIONS['CASELESS']) $k = strtolower($k);
                    if (preg_match('/^[0-9]+$/', (string) $k)) $k = '_' . $k;

                    if (is_array($v)) {
                        if (($arr[$i][$k] = $this->_arrayBuild($v)) == false) return false;
                    } else { // reinsert the var
                        $arr[$i][$k] = $v;
                    }
                }
                if ($this->OPTIONS['LOOP_CONTEXT_VARS']) {
                    $_first = ($this->OPTIONS['CASELESS']) ? '__first__' : '__FIRST__';
                    $_last = ($this->OPTIONS['CASELESS']) ? '__last__' : '__LAST__';
                    $_inner = ($this->OPTIONS['CASELESS']) ? '__inner__' : '__INNER__';
                    $_even = ($this->OPTIONS['CASELESS']) ? '__even__' : '__EVEN__';
                    $_odd = ($this->OPTIONS['CASELESS']) ? '__odd__' : '__ODD__';
                    $_rownum = ($this->OPTIONS['CASELESS']) ? '__rownum__' : '__ROWNUM__';

                    if ($i == 0) $arr[$i][$_first] = true;
                    if (($i + 1) == count($arr)) $arr[$i][$_last] = true;
                    if ($i != 0 && (($i + 1) < count($arr))) $arr[$i][$_inner] = true;
                    if (is_int(($i + 1) / 2)) $arr[$i][$_even] = true;
                    if (!is_int(($i + 1) / 2)) $arr[$i][$_odd] = true;
                    $arr[$i][$_rownum] = ($i + 1);
                }
            }
            return $arr;
        }

        return true;
    }

    /**
     * METHOD: _parseIf
     * returns a string used for parsing in tmpl_if statements.
     *
     * @param string $varname
     * @param string $value
     * @param string|null $op
     * @param string|null $namespace current namespace
     * @access private
     * @return string used for eval'ing
     */
    public function _parseIf(string $varname, mixed $value = null, string $op = null, string $namespace = null) : string
    {
        if (isset($namespace)) $namespace = substr($namespace, 0, -1);
        $comp_str = ''; // used for extended if statements

        // work out what to put on the end id value="whatever" is used
        if (isset($value)) {

            // add the correct operator depending on whether it's been specified or not
            if (!empty($op)) {
                if (in_array($op, $this->allowed_if_ops)) {
                    $comp_str .= $op;
                } else {
                    vlibTemplateError::raiseError('VT_WARNING_INVALID_IF_OP', WARNING, $op);
                }
            } else {
                $comp_str .= '==';
            }

            // now we add the value, if it's numeric, then we leave the quotes off
            if (is_numeric($value)) {
                $comp_str .= $value;
            } else {
                $comp_str .= '\'' . $value . '\'';
            }
        }

        if (count($this->_namespace) == 0 || $namespace == 'global') return '$this->_vars[\'' . $varname . '\']' . $comp_str;
        $retstr = '$this->_arrvars';
        $numnamespaces = count($this->_namespace);
        for ($i = 0; $i < $numnamespaces; $i++) {
            if ($this->_namespace[$i] == $namespace || (($i + 1) == $numnamespaces && !empty($namespace))) {
                $retstr .= "['" . $namespace . "'][\$_" . $i . ']';
                break;
            } else {
                $retstr .= "['" . $this->_namespace[$i] . "'][\$_" . $i . ']';
            }
        }

        if ($this->OPTIONS['GLOBAL_VARS'] && empty($namespace)) {
            return '((' . $retstr . '[\'' . $varname . '\'] !== null) ? ' . $retstr . '[\'' . $varname . '\'] : $this->_vars[\'' . $varname . '\'])' . $comp_str;
        } else {
            return $retstr . "['" . $varname . "']" . $comp_str;
        }
    }


    /**
     * METHOD: _parseLoop
     * returns a string used for parsing in tmpl_loop statements.
     *
     * @param string $varname
     * @access private
     * @return string used for eval'ing
     */
    public function _parseLoop($varname)
    {
        $this->_namespace[] = $varname;
        $tempvar = count($this->_namespace) - 1;
        /*$retstr = '$row_count_' . $tempvar . '=count($this->_arrvars';
        for ($i = 0; $i < count($this->_namespace); $i++) {
            $retstr .= "['" . $this->_namespace[$i] . "']";
            if ($this->_namespace[$i] != $varname) $retstr .= "[\$_" . $i . ']';
        }
        $retstr .= '); for ($_' . $tempvar . '=0 ; $_' . $tempvar . '<$row_count_' . $tempvar . '; $_' . $tempvar . '++) {';
        return $retstr;
        */




        $varTitle = '$this->_arrvars';
        for ($i = 0; $i < count($this->_namespace); $i++) {
            $varTitle .= "['" . $this->_namespace[$i] . "']";
            if ($this->_namespace[$i] != $varname) $varTitle .= "[\$_" . $i . ']';
        }

        $retstr = '$row_count_' . $tempvar . '=is_countable(' . $varTitle . ')?count(' . $varTitle . '):0; ';

        $retstr .= 'for ($_' . $tempvar . '=0 ; $_' . $tempvar . '<$row_count_' . $tempvar . '; $_' . $tempvar . '++) {';
        return $retstr;
    }

    /**
     * METHOD: _parseVar
     *
     * returns a string used for parsing in tmpl_var statements.
     *
     * @param string $wholetag
     * @param string $varname
     * @param string $escape
     * @param string|null $format
     * @param string|null $namespace
     * @return string used for eval'ing
     * @access private
     */
    public function _parseVar(string $wholetag, string $varname, ? string $escape, ? string $format = '', ? string $namespace = '') : string
    {
        if (!empty($namespace)) $namespace = substr($namespace, 0, -1);
        $wholetag = stripslashes($wholetag);

        if (count($this->_namespace) == 0 || $namespace == 'global') {
            $var1 = '$this->_vars[\'' . $varname . '\']';
        } else {
            $var1build = "\$this->_arrvars";
            $numnamespaces = count($this->_namespace);
            for ($i = 0; $i < $numnamespaces; $i++) {
                if ($this->_namespace[$i] == $namespace || (($i + 1) == $numnamespaces && !empty($namespace))) {
                    $var1build .= "['" . $namespace . "'][\$_" . $i . ']';
                    break;
                } else {
                    $var1build .= "['" . $this->_namespace[$i] . "'][\$_" . $i . ']';
                }
            }
            $var1 = $var1build . '[\'' . $varname . '\']';

            if ($this->OPTIONS['GLOBAL_VARS'] && empty($namespace)) {
                $var2 = '$this->_vars[\'' . $varname . '\']';
            }
        }

        $beforevar = '';
        $aftervar = '';
        if (!empty($escape) && isset($this->ESCAPE_TAGS[$escape])) {
            $beforevar .= $this->ESCAPE_TAGS[$escape]['open'];
            $aftervar = $this->ESCAPE_TAGS[$escape]['close'] . $aftervar;
        }

        if (!empty($format)) {
            if (isset($this->FORMAT_TAGS[$format])) {
                $beforevar .= $this->FORMAT_TAGS[$format]['open'];
                $aftervar = $this->FORMAT_TAGS[$format]['close'] . $aftervar;
            } elseif (function_exists($format)) {
                $beforevar .= $format . '(';
                $aftervar = ')' . $aftervar;
            }
        }

        // build return values
        $retstr = 'if (' . $var1 . ' !== null) { ';
        $retstr .= 'print(' . $beforevar . $var1 . $aftervar . '); ';
        $retstr .= '}';

        if (@$var2) {
            $retstr .= ' elseif (' . $var2 . ' !== null) { ';
            $retstr .= 'print(' . $beforevar . $var2 . $aftervar . '); ';
            $retstr .= '}';
        }

        switch (strtolower($this->OPTIONS['UNKNOWNS'])) {
            case 'comment':
                $comment = addcslashes('<!-- unknown variable ' . preg_replace('/<!--|-->/', '', $wholetag) . '//-->', '"');
                $retstr .= ' else { print("' . $comment . '"); $this->_setUnknown("' . $varname . '"); }';
                return $retstr;

            case 'leave':
                $retstr .= ' else { print("' . addcslashes($wholetag, '"') . '"); $this->_setUnknown("' . $varname . '"); }';
                return $retstr;

            case 'print':
                $retstr .= ' else { print("' . htmlspecialchars($wholetag, ENT_QUOTES) . '"); $this->_setUnknown("' . $varname . '"); }';
                return $retstr;


            case 'ignore':
                return $retstr;

            case 'remove':
            default:
                $retstr .= ' else { $this->_setUnknown("' . $varname . '"); }';
                return $retstr;

        }
    }

    /**
     * METHOD: _parseIncludeFile
     * parses a string in an include tag, i.e.:
     *  <TMPL_INCLUDE FILE="footer_{var:footer_number}.html" />
     *
     * @param string $file name
     */
    public function _parseIncludeFile(string $file) : string
    {
        return preg_replace('/\{var:([^\}]+)\}/i', "'.\$this->_vars['\\1'].'", $file);
    }


    /**
     * METHOD: _parseTag
     * takes values from preg_replace in $this->_intparse() and determines
     * the replace string.
     *
     * @param array $args array of all matches found by preg_replace
     * @access private
     * @return string replace values
     */
    public function _parseTag(array $args) : string
    {
        $wholetag = $args[0];
        $openclose = $args[1];
        $tag = strtolower($args[2]);

        if ($tag == 'else') return '<?php } else { ?>';

        if (preg_match('/^<\/|{\/|<!--\/$/s', $openclose) || preg_match('/^end[if|loop|unless|comment]$/', $tag)) {
            if ($tag == 'loop' || $tag == 'endloop') array_pop($this->_namespace);
            if ($tag == 'comment' || $tag == 'endcomment') {
                return '<?php */ ?>';
            } else {
                return '<?php } ?>';
            }
        }

        // arrange attributes
        for ($i = 3; $i < 8; $i = ($i + 2)) {
            if (empty($args[$i]) && empty($args[($i + 1)])) break;
            $key = (empty($args[$i])) ? 'name' : strtolower($args[$i]);
            if ($key == 'name' && preg_match('/^(php)?include$/', $tag)) $key = 'file';
            $$key = $args[($i + 1)];
        }


        if (isset($name)) {
            $var = ($this->OPTIONS['CASELESS']) ? strtolower($name) : $name;

            if ($this->_debug && !empty($var)) {
                if (preg_match('/^global\.([A-Za-z_]+[_A-Za-z0-9]*)$/', $var, $matches)) $var2 = $matches[1];
                if (empty($this->_debugTemplatevars[$tag])) $this->_debugTemplatevars[$tag] = [];
                if (!isset($var2)) $var2 = $var;
                if (!in_array($var2, $this->_debugTemplatevars[$tag])) $this->_debugTemplatevars[$tag][] = $var2;
            }

            if (preg_match('/^([A-Za-z_]+[_A-Za-z0-9]*(\.)+)?([A-Za-z_]+[_A-Za-z0-9]*)$/', $var, $matches)) {
                $var = $matches[3];
                $namespace = $matches[1];
            }
        }


        // return correct string (tag dependent)
        switch ($tag) {
            case 'var':
                $escape = (empty($escape) && !empty($this->OPTIONS['DEFAULT_ESCAPE']) && strtolower($this->OPTIONS['DEFAULT_ESCAPE']) != 'none') ? strtolower($this->OPTIONS['DEFAULT_ESCAPE']) : null;
                return '<?php ' . $this->_parseVar($wholetag, ($var??null), $escape, ($format ?? null), ($namespace??null) ). ' ?>';

            case 'if':
                return '<?php if (' . $this->_parseIf($var, ($value??null), ($op??null), ($namespace??null)) . ') { ?>';

            case 'unless':
                return '<?php if (!' . $this->_parseIf($var, ($value??null), ($op??null), ($namespace??null)) . ') { ?>';

            case 'elseif':
                return '<?php } elseif (' . $this->_parseIf($var, ($value??null), ($op??null), ($namespace??null)) . ') { ?>';

            case 'loop':
                return '<?php ' . $this->_parseLoop($var) . '?>';

            case 'comment':
                if (empty($var)) { // full open/close style comment
                    return '<?php /* ?>';
                } else { // just ignore tag if it was a one line comment
                    return '';
                }

            case 'phpinclude':
                if ($this->OPTIONS['ENABLE_PHPINCLUDE']) {
                    return '<?php include(\'' . $file . '\'); ?>';
                }
                break;

            case 'include':
                return '<?php $this->_getData($this->_fileSearch(\'' . $this->_parseIncludeFile($file) . '\'), 1); ?>';

            default:
                if ($this->OPTIONS['STRICT']) vlibTemplateError::raiseError('VT_ERROR_INVALID_TAG', KILL, htmlspecialchars($wholetag, ENT_QUOTES));

        }

        return '';
    }

    /**
     * METHOD: _intParse
     *
     * Parses $this->_tmplfile into correct format for eval() to work
     * Called by $this->_parse(), or $this->fastPrint, this replaces all <tmpl_*> references
     * with their correct php representation, i.e. <tmpl_var title> becomes $this->vars['title']
     * Sets final parsed file to $this->_tmplfilep.
     *
     * @access private
     * @return bool true/false
     */
    public function _intParse (): bool
    {
        $this->_tmplfilep = '?>'.$this->_getData($this->_tmplfilename).'<?php return true;';
        return true;
    }

    /**
     * METHOD: _parse
     *
     * Calls _intParse, and eval()s $this->tmplfilep
     * and outputs the results to $this->tmploutput
     *
     * @param string|null $compress whether to compress contents
     * @return bool true/false
     * @access private
     */
    public function _parse(string|null $compress = null): bool
    {
        if (!$this->_parsed) {
            if ($this->OPTIONS['TIME_PARSE']) $this->_firstparsetime = $this->_getMicrotime();

            $this->_intParse();
            $this->_parsed = true;

            if ($this->OPTIONS['TIME_PARSE']) $this->_totalparsetime = ($this->_getMicrotime() - $this->_firstparsetime);
            if ($this->OPTIONS['TIME_PARSE'] && $this->OPTIONS['GLOBAL_CONTEXT_VARS']) $this->setVar('__PARSE_TIME__', $this->getParseTime());
        }

        ob_start($compress);

        $this->_currentincludedir[] = dirname($this->_tmplfilename);
        $this->_includedepth++;
        $success = @eval($this->_tmplfilep);
        $this->_includedepth--;
        array_pop($this->_currentincludedir);

        if ($this->_debug) $this->doDebug();
        if (!$success) vlibTemplateError::raiseError('VT_ERROR_PARSE', FATAL);
        $this->_tmploutput .= ob_get_contents();
        ob_end_clean();

        return true;
    }

    /**
     * METHOD: _setOption
     *
     * Sets one or more of the boolean options 1/0, that control certain actions in the template.
     * Use of this function:
     * either: vlibTemplate::_setOptions(string option_name, bool option_val [, string option_name, bool option_val ..]);
     * or      vlibTemplate::_setOptions(array);
     *          with an associative array where the key is the option_name
     *          and the value is the option_value.
     *
     * @param mixed (mulitple)
     * @return bool true/false
     * @access private
     */
    public function _setOption(): bool
    {
        $numargs = func_num_args();
        if ($numargs < 1) {
            vlibTemplateError::raiseError('VT_ERROR_WRONG_NO_PARAMS', null, '_setOption()');
            return false;
        }

        if ($numargs == 1) {
            $options = func_get_arg(1);
            if (is_array($options)) {
                foreach ($options as $k => $v) {
                    if ($v != null) {
                        if (in_array($k, array_keys($this->OPTIONS))) $this->OPTIONS[$k] = $v;
                    }
                }
            } else {
                vlibTemplateError::raiseError('VT_ERROR_WRONG_NO_PARAMS', null, '_setOption()');
                return false;
            }
        } elseif (is_int($numargs / 2)) {
            for ($i = 0; $i < $numargs; $i = ($i + 2)) {
                $k = func_get_arg($i);
                $v = func_get_arg(($i + 1));
                if ($v != null) {
                    if (in_array($k, array_keys($this->OPTIONS))) $this->OPTIONS[$k] = $v;
                }
            }
        } else {
            vlibTemplateError::raiseError('VT_ERROR_WRONG_NO_PARAMS', null, '_setOption()');
            return false;
        }
        return true;
    }

    /**
     * METHOD: _setUnknown
     *
     * Used during parsing, this function sets an unknown var checking to see if it
     * has been previously set.
     *
     * @param string var
     * @access private
     */
    public function _setUnknown($var) : void
    {
        if (!in_array($var, $this->_unknowns)) $this->_unknowns[] = $var;
    }

    /**
     * METHOD: _getMicrotime
     * Returns microtime as a float number
     *
     * @return float microtime
     * @access private
     */
    public function _getMicrotime() : float
    {
        list($msec, $sec) = explode(' ', microtime());
        return ((float)$msec + (float)$sec);
    }

    /**
     * METHOD: _escape_hex
     * Returns str encoded to hex code.
     *
     * @param string str to be encoded
     * @param bool true/false specify whether to use hex_entity
     * @return string encoded in hex
     * @access private
     */
    public function _escape_hex($str = '', $entity = false)
    {
        $prestr = $entity ? '&#x' : '%';
        $poststr = $entity ? ';' : '';
        for ($i = 0; $i < strlen($str); $i++) {
            $return .= $prestr . bin2hex($str[$i]) . $poststr;
        }
        return $return;
    }


    /**
     * The following function have no use and are included just so that if the user
     * is making use of vlibTemplateCache functions, this doesn't crash when changed to
     * vlibTemplate if the user is quickly bypassing the vlibTemplateCache class.
     * @return void
     */
    public function clearCache(): bool
    {
        vlibTemplateError::raiseError('VT_WARNING_NOT_CACHE_OBJ', WARNING, 'clearCache()');
    }

    /**
     *  The following function have no use and are included just so that if the user
     *  is making use of vlibTemplateCache functions, this doesn't crash when changed to
     *  vlibTemplate if the user is quickly bypassing the vlibTemplateCache class.
     * @return void
     */
    public function recache(): bool
    {
        vlibTemplateError::raiseError('VT_WARNING_NOT_CACHE_OBJ', WARNING, 'recache()');
    }

    /**
     *  The following function have no use and are included just so that if the user
     *  is making use of vlibTemplateCache functions, this doesn't crash when changed to
     *  vlibTemplate if the user is quickly bypassing the vlibTemplateCache class.
     * @return void
     */
    public function setCacheLifeTime(): bool
    {
        vlibTemplateError::raiseError('VT_WARNING_NOT_CACHE_OBJ', WARNING, 'setCacheLifeTime()');
    }

    /**
     *  The following function have no use and are included just so that if the user
     *  is making use of vlibTemplateCache functions, this doesn't crash when changed to
     *  vlibTemplate if the user is quickly bypassing the vlibTemplateCache class.
     * @return void
     */
    public function setCacheExtension(): bool
    {
        vlibTemplateError::raiseError('VT_WARNING_NOT_CACHE_OBJ', WARNING, 'setCacheExtension()');
    }
}


