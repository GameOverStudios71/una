<?php defined('BX_DOL') or die('hack attempt');
/**
 * Copyright (c) UNA, Inc - https://una.io
 * MIT License - https://opensource.org/licenses/MIT
 *
 * @defgroup    Profiler Profiler
 * @ingroup     UnaModules
 *
 * @{
 */

define ('BX_PROFILER', true);
define ('BX_PROFILER_DISPLAY', 1);

require_once(BX_DIRECTORY_PATH_MODULES . 'boonex/profiler/install/config.php');
$GLOBALS['bx_profiler_module'] = array (
    'type' => BX_DOL_MODULE_TYPE_MODULE,
    'name' => 'bx_profiler',
    'title' => $aConfig['title'],
    'vendor' => $aConfig['vendor'],
    'path' => $aConfig['home_dir'],
    'uri' => $aConfig['home_uri'],
    'class_prefix' => $aConfig['class_prefix'],
    'db_prefix' => $aConfig['db_prefix'],
);

bx_import('Template', $GLOBALS['bx_profiler_module']);
bx_import('Config', $GLOBALS['bx_profiler_module']);

class BxProfiler extends BxDol
{
    protected $oConfig, $oTemplate, $oTemplateAdmin;

    protected $aConf = [];

    protected $_iTimeStart = 0;

    protected $_aQueries = [];
    protected $_sQueryIndex = 0;

    protected $_aModules = [];
    protected $_aModulesNames = [];
    protected $_aModulesLevel = 0;

    protected $_aAlerts = [];
    protected $_iAlertsLevel = 0;

    protected $_aInjections = [];
    protected $_sInjectionIndex = 0;

    protected $_sLogDateFormat = "Y-m-d H:i:s";
    protected $_sLogMaxArgLength = 64;
    protected $_sLogFilename = 64;

    protected $_aTemplates = [];
    protected $_aTemplateIndexes = [];

    protected $_aPages = [];
    protected $_sPageIndex = '';
    protected $_sPageBlockIndex = 0;
    protected $_aPagesBlocks = [];
    
    protected $_aMenus = [];   

    function __construct($iTimeStart)
    {
        parent::__construct();

        $this->oConfig = new BxProfilerConfig ($GLOBALS['bx_profiler_module']);
        $this->oTemplate = new BxProfilerTemplate ($this->oConfig);
        $this->oTemplateAdmin = BxDolStudioTemplate::getInstance();

        if (getParam ('bx_profiler_long_sql_queries_log'))
            $this->aConf['long_query'] = getParam ('bx_profiler_long_sql_queries_time');
        if (getParam ('bx_profiler_long_module_query_log'))
            $this->aConf['long_module'] = getParam ('bx_profiler_long_module_query_time');
        if (getParam ('bx_profiler_long_page_log'))
            $this->aConf['long_page'] = getParam ('bx_profiler_long_page_time');

        $this->_iTimeStart = $iTimeStart;
    }

    // output profiler debug panel
    function output ()
    {
        $iPageTime = $this->_getCurrentDelay ();
        if (isset($this->aConf['long_page']) && $iPageTime > $this->aConf['long_page'])
            $this->logPageOpen ($iPageTime);

        switch (getParam('bx_profiler_show_debug_panel')) {
        case 'all':
            break;
        case 'admins':
            if (!isset($GLOBALS['logged']) || !$GLOBALS['logged']['admin'])
                return;
            break;
        case 'none':
        default:
            return;
        }

        $aCss = array (
			'profiler.css',
            BX_DIRECTORY_PATH_PLUGINS_PUBLIC . 'jush/|jush.css',
        );
        $aJs = array (
            'jquery.tablesorter.min.js',
            'profiler.js',
            'jush/jush.js',
        );

        $sJsCss = '';
        foreach ($aCss as $sCssPath)
            $sJsCss .= $this->oTemplate->addCss($sCssPath, 1);
        foreach ($aJs as $sJsPath)
            $sJsCss .= $this->oTemplate->addJs($sJsPath, 1);

        $sPlanks  = $this->_plankMain ();
        $sPlanks .= $this->_plankMenus ();
        $sPlanks .= $this->_plankTemplates ();
        $sPlanks .= $this->_plankInjections ();
        $sPlanks .= $this->_plankPagesBlocks ();
        $sPlanks .= $this->_plankSql ();
        $sPlanks .= $this->_plankModules ();
        $sPlanks .= $this->_plankAlerts ();

        echo ob_get_clean();

        $sContentType = $this->oTemplate->getHeaderContentType();
        if ('text/html' == $sContentType) {            
            echo $sJsCss . $sPlanks;
        } 
        else if ('application/json' == $sContentType) {
            echo $sPlanks;
        }
    }

    function _logBegin ($s)
    {
        $userId = function_exists('getLoggedId') ? getLoggedId() : '';
        $userRole = isset($GLOBALS['logged']) ? ($GLOBALS['logged']['admin'] ? 'admin' : ($GLOBALS['logged']['member'] ? 'member' : 'guest')) : 'unknown';
        //$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'Unknown URL';
        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'Unknown Method';
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'Unknown Script';
        $queryString = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : 'No Query String';
        return "---------------------------------------------------------------------------------------------\n" . " " . $s . "\n" .
        ($userId ? "User ID: " . $userId  . "\n" : '') .
        "User role: " . $userRole . "\n" .
        //"Request URL: " . $requestUri . "\n" .
        "Request Method: " . $requestMethod . "\n" .
        "Script Name: " . $scriptName . "\n" .
        "Query String: " . $queryString . "\n";
    }

    function _logEnd ()
    {
        return "\n";
    }

    function _appendToLog ($s)
    {
		bx_log('bx_profiler', $s);
    }

    protected function _logSqlQuery($sQuery)
    {
        $sLogFile = 'C:\\xampp\\htdocs\\una\\logs.txt'; // Nome do arquivo de log

        $sLogMessage = $this->_logBegin("");
        $sLogMessage .= "Query: " . $sQuery . "\n";

        $backtrace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10);
        // Inverter a ordem do backtrace para mostrar da chamada mais externa para a mais interna
        $backtrace = array_reverse($backtrace);
        foreach ($backtrace as $index => $trace) {
            if($index < count($backtrace) - 3) { // Ajustar para ignorar os últimos 3 itens que são internos ao profiler
                $file = isset($trace['file']) ? explode('/', str_replace('\\', '/', $trace['file'])) : array();
                $fileName = !empty($file) ? str_replace('.php', '', end($file)) : 'unknown';
                // Adicionar caminho abreviado (últimos 2 diretórios, se disponíveis)
                $filePath = count($file) > 2 ? implode('/', array_slice($file, -3, 2)) . '/' . $fileName : (count($file) > 1 ? implode('/', array_slice($file, -2)) : $fileName);
                $line = isset($trace['line']) ? $trace['line'] : 'unknown';
                // Indicador visual de profundidade
                $depthIndicator = str_repeat('  ', $index) . '-> ';
                $sLogMessage .= "\n" . $depthIndicator . $filePath;
                $sLogMessage .= " (Line: " . $line . ")";
                $sLogMessage .= " :: " . (isset($trace['class']) ? $trace['class'] : 'unknown');
                // Adicionar tipo de chamada (:: ou ->)
                $callType = isset($trace['type']) ? $trace['type'] : '';
                $sLogMessage .= " " . $callType . " " . (isset($trace['function']) ? $trace['function'] : 'unknown');
                if (isset($trace['args']) && !empty($trace['args'])) {
                    $sLogMessage .= " :: Args: ";
                    $args = array();
                    foreach ($trace['args'] as $arg) {
                        if (is_array($arg)) {
                            $args[] = 'array(' . $this->_formatArray($arg, 2) . ')';
                        } elseif (is_object($arg)) {
                            $args[] = 'object(' . get_class($arg) . ') ' . $this->_formatObject($arg, 2);
                        } else {
                            $args[] = var_export($arg, true);
                        }
                    }
                    $sLogMessage .= implode(', ', $args);
                }
            }
        }
        $sLogMessage .= "\n" . $this->_logEnd();
        file_put_contents($sLogFile, $sLogMessage, FILE_APPEND);
    }

    function logSqlQuery ($iTime, $aSqlQuery, &$oStmt)
    {
        $s  = $this->_logBegin ('LONG SQL QUERY: ' . $aSqlQuery['time']);
        $s .= "Rows: " . $aSqlQuery['rows'] . "\n";
        $s .= "Affected: " . $aSqlQuery['affected'] . "\n";
        $s .= "Query: " . $aSqlQuery['sql'] . "\n";
        if (getParam('bx_profiler_long_sql_queries_debug'))
            $s .= "Backtrace: \n" . $this->_debugBackTrace (3);
        $s .= $this->_logEnd();
        $this->_appendToLog ($s);
    }

    function logModuleQuery ($iTime, $aModuleQuery)
    {
        $s  = $this->_logBegin ('LONG MODULE QUERY: ' . $aModuleQuery['time']);
        $s .= "Module name: " . $aModuleQuery['name'] . "\n";
        $s .= "Query type: " . $aModuleQuery['type'] . "\n";
        $s .= "Class/file: " . $aModuleQuery['class/file'] . "\n";
        $s .= "Method: " . $aModuleQuery['method'] . "\n";
        if (getParam('bx_profiler_long_module_query_debug'))
            $s .= "Backtrace: \n" . $this->_debugBackTrace (3);
        $s .= $this->_logEnd();
        $this->_appendToLog ($s);
    }

    function logPageOpen ($iTime)
    {
        $s  = $this->_logBegin ('LONG PAGE OPEN: ' . $this->_formatTime($iTime));
        $s .= "Request method: " . $_SERVER['REQUEST_METHOD'] . "\n";
        $s .= "Query string: " . $_SERVER['QUERY_STRING'] . "\n";
        $s .= "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";
        $s .= "Script name: " . $_SERVER['SCRIPT_NAME'] . "\n";
        $s .= "PHP self: " . $_SERVER['PHP_SELF'] . "\n";
        if (getParam('bx_profiler_long_page_debug'))
            $s .= "All server vars: \n" . print_r ($_SERVER, true);
        $s .= $this->_logEnd();
        $this->_appendToLog ($s);
    }

    function beginModule($sType, $sHash, &$aModule, $sClassFile, $sMethod = '' )
    {
        ++$this->_aModulesLevel;
        $this->_aModulesNames[$aModule['title']] = isset($this->_aModulesNames[$aModule['title']]) ? $this->_aModulesNames[$aModule['title']] + 1 : 1;
        $this->_aModules[$sHash] = array (
            'name' => str_repeat("--", $this->_aModulesLevel-1) . $aModule['title'],
            'type' => $sType,
            'class/file' => $sClassFile,
            'method' => $sMethod,
            'begin' => microtime (),
            'time' => -1,
        );
    }

    function endModule($sType, $sHash)
    {
        --$this->_aModulesLevel;
        $iTime = $this->_calcTime ($this->_aModules[$sHash]['begin']);
        unset ($this->_aModules[$sHash]['begin']);
        $this->_aModules[$sHash]['time'] = $this->_formatTime($iTime);
        $this->_aModules[$sHash]['raw_time'] = $iTime;
        if (isset($this->aConf['long_module']) && $iTime > $this->aConf['long_module'])
            $this->logModuleQuery ($iTime, $this->_aModules[$sHash]);
    }

    function beginAlert($sUnit, $sAction, $sHandler)
    {
        $sHash = md5($sUnit . $sAction . $sHandler . $this->_iAlertsLevel);
        ++$this->_iAlertsLevel;
        $this->_aAlerts[$sHash] = array (
            'name' => str_repeat("--", $this->_iAlertsLevel-1) . $sHandler,
            'unit' => $sUnit,
            'action' => $sAction,
            'handler' => $sHandler,
            'begin' => microtime (),
            'time' => -1,
        );
    }

    function endAlert($sUnit, $sAction, $sHandler)
    {
        --$this->_iAlertsLevel;
        $sHash = md5($sUnit . $sAction . $sHandler . $this->_iAlertsLevel);
        $iTime = $this->_calcTime ($this->_aAlerts[$sHash]['begin']);
        unset ($this->_aAlerts[$sHash]['begin']);
        $this->_aAlerts[$sHash]['time'] = $this->_formatTime($iTime);
        $this->_aAlerts[$sHash]['raw_time'] = $iTime;
    }

    function beginInjection ($sId)
    {
        $this->_sInjectionIndex = $sId;
        $this->_aInjections[$sId]['begin'] = microtime ();
    }

    function endInjection ($sId, $aInjection)
    {
        if (!isset($this->_aInjections[$sId]))
            return;
        $iTime = $this->_calcTime ($this->_aInjections[$sId]['begin']);
        unset ($this->_aInjections[$sId]['begin']);
        $this->_aInjections[$sId]['name'] = isset($aInjection['name']) ? $aInjection['name'] : 'no-name';
        $this->_aInjections[$sId]['key'] = $aInjection['key'];
        $this->_aInjections[$sId]['replace'] = $aInjection['replace'] ? 'yes' : 'no';
        $this->_aInjections[$sId]['time'] = $this->_formatTime($iTime);
        $this->_aInjections[$sId]['raw_time'] = $iTime;
    }

    function beginPageBlock ($sName, $iBlockId)
    {
        $this->_sPageBlockIndex = $iBlockId;
        $this->_aPagesBlocks[$this->_sPageBlockIndex]['name'] = $sName;
        $this->_aPagesBlocks[$this->_sPageBlockIndex]['begin'] = microtime ();
    }

    function endPageBlock ($iBlockId, $isEmpty, $isCached)
    {
        if (!$this->_sPageBlockIndex)
            return;
        $iTime = $this->_calcTime ($this->_aPagesBlocks[$this->_sPageBlockIndex]['begin']);
        unset ($this->_aPagesBlocks[$this->_sPageBlockIndex]['begin']);
        $this->_aPagesBlocks[$this->_sPageBlockIndex]['cached'] = $isCached ? 'yes' : 'no';
        $this->_aPagesBlocks[$this->_sPageBlockIndex]['empty'] = $isEmpty ? 'yes' : 'no';
        $this->_aPagesBlocks[$this->_sPageBlockIndex]['time'] = $this->_formatTime($iTime);
        $this->_aPagesBlocks[$this->_sPageBlockIndex]['raw_time'] = $iTime;
    }

    function beginPage ($sName)
    {
        $this->_sPageIndex = md5 ($sName.time().rand());
        $this->_aPages[$this->_sPageIndex]['name'] = $sName;
        $this->_aPages[$this->_sPageIndex]['begin'] = microtime ();
    }

    function endPage (&$sContent)
    {
        if (!$this->_sPageIndex || !isset($this->_aPages[$this->_sPageIndex]['begin']))
            return;
        $iTime = $this->_calcTime ($this->_aPages[$this->_sPageIndex]['begin']);
        unset ($this->_aPages[$this->_sPageIndex]['begin']);
        $this->_aPages[$this->_sPageIndex]['time'] = $this->_formatTime($iTime);
        $this->_aPages[$this->_sPageIndex]['raw_time'] = $iTime;
    }

    function beginTemplate ($sName, $sRand)
    {
        $this->_aTemplateIndexes[$sName.$sRand] = 1;
        $this->_aTemplates[$sName.$sRand]['name'] = $sName;
        $this->_aTemplates[$sName.$sRand]['begin'] = microtime ();
    }

    function endTemplate ($sName, $sRand, &$sContent, $isCached)
    {
        if (!isset($this->_aTemplateIndexes[$sName.$sRand]))
            return;
        $iTime = $this->_calcTime ($this->_aTemplates[$sName.$sRand]['begin']);
        unset ($this->_aTemplates[$sName.$sRand]['begin']);
        $this->_aTemplates[$sName.$sRand]['cached'] = $isCached ? 'yes' : 'no';
        $this->_aTemplates[$sName.$sRand]['time'] = $this->_formatTime($iTime);
        $this->_aTemplates[$sName.$sRand]['raw_time'] = $iTime;
    }

    function beginQuery ($sSql)
    {
        $this->_sQueryIndex = md5 ($sSql.time().rand());
        $this->_aQueries[$this->_sQueryIndex]['sql'] = $sSql;
        $this->_aQueries[$this->_sQueryIndex]['begin'] = microtime ();
    }

    function endQuery (&$oStmt)
    {
        if (!$this->_sQueryIndex)
            return;
        $iTime = $this->_calcTime ($this->_aQueries[$this->_sQueryIndex]['begin']);
        unset ($this->_aQueries[$this->_sQueryIndex]['begin']);
        $this->_aQueries[$this->_sQueryIndex]['time'] = $this->_formatTime($iTime);
        $this->_aQueries[$this->_sQueryIndex]['raw_time'] = $iTime;
        $this->_aQueries[$this->_sQueryIndex]['rows'] = $oStmt ? BxDolDb::getInstance()->getNumRows($oStmt) : '';
        $this->_aQueries[$this->_sQueryIndex]['affected'] = $oStmt ? BxDolDb::getInstance()->getAffectedRows($oStmt) : '';
        if (isset($this->aConf['long_query']) && $iTime > $this->aConf['long_query'])
            $this->logSqlQuery ($iTime, $this->_aQueries[$this->_sQueryIndex], $oStmt);
        
        $this->_logSqlQuery($this->_aQueries[$this->_sQueryIndex]['sql']);
    }

    function beginMenu ($sName)
    {
        $this->_aMenus[$sName]['name'] = $sName;
        $this->_aMenus[$sName]['begin'] = microtime ();
    }

    function endMenu ($sName)
    {
        if (!isset($this->_aMenus[$sName]))
            return;
        $iTime = $this->_calcTime ($this->_aMenus[$sName]['begin']);
        unset ($this->_aMenus[$sName]['begin']);
        $this->_aMenus[$sName]['time'] = $this->_formatTime($iTime);
        $this->_aMenus[$sName]['raw_time'] = $iTime;
    }

    function _getCurrentDelay ()
    {
        $i1 = explode(' ', microtime ());
        $i2 = explode(' ', $this->_iTimeStart);
        return ($i1[0]+$i1[1]) - ($i2[0]+$i2[1]);
    }

    function _plankMain ()
    {
        $sTime = $this->_formatTime($this->_getCurrentDelay (), 3);
        if (function_exists('memory_get_usage'))
            $sMemory = $this->_formatBytes(memory_get_usage(true)) . ' of ' . ini_get('memory_limit') . ' allowed';

        return $this->oTemplate->plank(
            $this->oTemplate->nameValue('Time:', $sTime) .
            (function_exists('memory_get_usage') ? $this->oTemplate->nameValue('Memory:', $sMemory) : '') .
            $this->oTemplate->nameValue('PHP:', phpversion()) .
            $this->oTemplate->nameValue('SAPI:', php_sapi_name()) .
            $this->oTemplate->nameValue('OS:', php_uname('s r m'))
        );
    }

    function _plankTemplates ()
    {
        if (empty($GLOBALS['bx_profiler']->_aPages) && empty($GLOBALS['bx_profiler']->_aTemplates))
            return;

        $sPages = '';
        if (!empty($GLOBALS['bx_profiler']->_aPages)) {
            $iTimePages = 0;
            foreach ($GLOBALS['bx_profiler']->_aPages as $k => $r) {
                if (!isset($r['raw_time']))
                    continue;
                $iTimePages += $r['raw_time'];
                unset ($GLOBALS['bx_profiler']->_aPages[$k]['raw_time']);
            }
            $sPages = count($GLOBALS['bx_profiler']->_aPages) . ' (' . $this->_formatTime($iTimePages, 3) . ')';
        }

        $sTemplatesCached = '';
        $sTemplatesNotCached = '';
        if ($GLOBALS['bx_profiler']->_aTemplates) {
            $iTimeTemplatesCached = 0;
            $iTimeTemplatesNotCached = 0;
            $sTemplatesCached = 0;
            $sTemplatesNotCached = 0;
            foreach ($GLOBALS['bx_profiler']->_aTemplates as $k => $r) {
                if (!isset($r['raw_time']))
                    continue;
                if ('yes' == $r['cached']) {
                    $iTimeTemplatesCached += $r['raw_time'];
                    ++$sTemplatesCached;
                } else {
                    $iTimeTemplatesNotCached += $r['raw_time'];
                    ++$sTemplatesNotCached;
                }
                unset ($GLOBALS['bx_profiler']->_aTemplates[$k]['raw_time']);
            }
            if ($sTemplatesCached)
                $sTemplatesCached .= ' (' . $this->_formatTime($iTimeTemplatesCached, 3) . ')';
            if ($sTemplatesNotCached)
                $sTemplatesNotCached .= ' (' . $this->_formatTime($iTimeTemplatesNotCached, 3) . ')';
        }

        return $this->oTemplate->plank(
            ($sPages ? $this->oTemplate->nameValue('Pages:', $sPages) : '') .
            ($sTemplatesCached ? $this->oTemplate->nameValue('Templates Cached:', $sTemplatesCached) : '') .
            ($sTemplatesNotCached ? $this->oTemplate->nameValue('Templates Not Cached:', $sTemplatesNotCached) : ''),
            $this->oTemplate->table($GLOBALS['bx_profiler']->_aTemplates)
        );
    }

    function _plankInjections ()
    {
        if (!$GLOBALS['bx_profiler']->_aInjections)
            return;

        $iTimeInjections = 0;
        foreach ($GLOBALS['bx_profiler']->_aInjections as $k => $r) {
            if (!isset($r['raw_time']))
                continue;
            $iTimeInjections += $r['raw_time'];
            unset ($GLOBALS['bx_profiler']->_aInjections[$k]['raw_time']);
        }

        $sInjections = count($GLOBALS['bx_profiler']->_aInjections) . ' injection (' . $this->_formatTime($iTimeInjections, 3) . ')';

        return $this->oTemplate->plank(
            $this->oTemplate->nameValue('Injections:', $sInjections),
            $this->oTemplate->table($GLOBALS['bx_profiler']->_aInjections)
        );
    }

    function _plankPagesBlocks ()
    {
        if (empty($GLOBALS['bx_profiler']->_aPagesBlocks))
            return;

        $iTimeBlocks = 0;
        $iTimeBlocksCached = 0;
        $iTimeBlocksNotCached = 0;
        $iTimeBlocksEmpty = 0;
        $iTimeBlocksNotEmpty = 0;

        $iCountBlocksCached = 0;
        $iCountBlocksNotCached = 0;
        $iCountBlocksEmpty = 0;
        $iCountBlocksNotEmpty = 0;

        foreach ($GLOBALS['bx_profiler']->_aPagesBlocks as $k => $r) {
            if (!isset($r['raw_time']))
                continue;
            $iTimeBlocks += $r['raw_time'];
            if ($r['cached'] == 'yes') {
                $iTimeBlocksCached += $r['raw_time'];
                ++$iCountBlocksCached;
            } else {
                $iTimeBlocksNotCached += $r['raw_time'];
                ++$iCountBlocksNotCached;
            }
            if ($r['empty'] == 'yes') {
                $iTimeBlocksEmpty += $r['raw_time'];
                ++$iCountBlocksEmpty;
            } else {
                $iTimeBlocksNotEmpty += $r['raw_time'];
                ++$iCountBlocksNotEmpty;
            }
            unset ($GLOBALS['bx_profiler']->_aPagesBlocks[$k]['raw_time']);
        }

        $sBlocks = count($GLOBALS['bx_profiler']->_aPagesBlocks) . ' (' . $this->_formatTime($iTimeBlocks, 3) . ')';
        if ($iCountBlocksCached)
            $sBlocksCached = $iCountBlocksCached . ' (' . $this->_formatTime($iTimeBlocksCached, 3) . ')';
        if ($iCountBlocksNotCached)
            $sBlocksNotCached = $iCountBlocksNotCached . ' (' . $this->_formatTime($iTimeBlocksNotCached, 3) . ')';
        if ($iCountBlocksEmpty)
            $sBlocksEmpty = $iCountBlocksEmpty . ' (' . $this->_formatTime($iCountBlocksEmpty, 3) . ')';
        if ($iCountBlocksNotEmpty)
            $sBlocksNotEmpty = $iCountBlocksNotEmpty . ' (' . $this->_formatTime($iCountBlocksNotEmpty, 3) . ')';

        return $this->oTemplate->plank(
            $this->oTemplate->nameValue('Pages Blocks:', $sBlocks) .
            ($iCountBlocksCached ? $this->oTemplate->nameValue('Cached:', $sBlocksCached) : '') .
            ($iCountBlocksNotCached ? $this->oTemplate->nameValue('Not Cached:', $sBlocksNotCached) : '') .
            ($iCountBlocksEmpty ? $this->oTemplate->nameValue('Empty:', $sBlocksEmpty) : '') .
            ($iCountBlocksNotEmpty ? $this->oTemplate->nameValue('Not Empty:', $sBlocksNotEmpty) : ''),
            $this->oTemplate->table($GLOBALS['bx_profiler']->_aPagesBlocks)
        );
    }

    function _plankMenus ()
    {
        if (empty($GLOBALS['bx_profiler']->_aMenus))
            return;

        $iTimeMenus = 0;
        foreach ($GLOBALS['bx_profiler']->_aMenus as $k => $r) {
            if (!isset($r['raw_time']))
                continue;
            $iTimeMenus += $r['raw_time'];
            unset ($GLOBALS['bx_profiler']->_aMenus[$k]['raw_time']);
        }

        $sMenus = count($GLOBALS['bx_profiler']->_aMenus) . ' menus (' . $this->_formatTime($iTimeMenus, 3) . ')';

        return $this->oTemplate->plank(
            $this->oTemplate->nameValue('Menus:', $sMenus),
            $this->oTemplate->table($GLOBALS['bx_profiler']->_aMenus)
        );
    }

    function _plankSql ()
    {
        if (empty($GLOBALS['bx_profiler']->_aQueries))
            return;

        $iTimeQueries = 0;
        foreach ($GLOBALS['bx_profiler']->_aQueries as $k => $r) {
            if (!isset($r['raw_time']))
                continue;
            $iTimeQueries += $r['raw_time'];
            unset ($GLOBALS['bx_profiler']->_aQueries[$k]['raw_time']);
        }

        $sQueries = count($GLOBALS['bx_profiler']->_aQueries) . ' queries (' . $this->_formatTime($iTimeQueries, 3) . ')';

        return $this->oTemplate->plank(
            $this->oTemplate->nameValue('SQL:', $sQueries),
            $this->oTemplate->table($GLOBALS['bx_profiler']->_aQueries, 'sql')
        );
    }

    function _plankModules ()
    {
        if (empty($GLOBALS['bx_profiler']->_aModules))
            return;

        $iTimeModules = 0;
        foreach ($GLOBALS['bx_profiler']->_aModules as $k => $r) {
            if (!isset($r['raw_time']))
                continue;
            $iTimeModules += $r['raw_time'];
            unset ($GLOBALS['bx_profiler']->_aModules[$k]['raw_time']);
        }

        $sModules = count($GLOBALS['bx_profiler']->_aModulesNames) . ' modules loaded';
        $sModulesQueries = count($GLOBALS['bx_profiler']->_aModules) . ' modules queries (' . $this->_formatTime($iTimeModules, 3) . ')';

        return $this->oTemplate->plank(
            $this->oTemplate->nameValue('Modules:', $sModules) .
            $this->oTemplate->nameValue('Modules Queries:', $sModulesQueries),
            $this->oTemplate->table($GLOBALS['bx_profiler']->_aModules)
        );
    }

    function _plankAlerts ()
    {
        if (empty($GLOBALS['bx_profiler']->_aAlerts))
            return;

        $iTimeAlerts = 0;
        foreach ($GLOBALS['bx_profiler']->_aAlerts as $k => $r) {
            if (!isset($r['raw_time']))
                continue;
            $iTimeAlerts += $r['raw_time'];
            unset ($GLOBALS['bx_profiler']->_aAlerts[$k]['raw_time']);
        }

        $sAlerts = count($GLOBALS['bx_profiler']->_aAlerts) . ' alerts responses (' . $this->_formatTime($iTimeAlerts, 3) . ')';

        return $this->oTemplate->plank(
            $this->oTemplate->nameValue('Alerts:', $sAlerts),
            $this->oTemplate->table($GLOBALS['bx_profiler']->_aAlerts)
        );
    }

    function _formatTime ($i, $iPrecision = 4)
    {
        return round($i, $iPrecision) . ' sec';
    }

    function _formatBytes ($i)
    {
        if ($i > 1024*1024)
            return round($i/1024/1024, 1) . 'M';
        elseif ($i > 1024)
            return round($i/1024, 1) . 'K';
        else
            return $i . 'B';
    }

    function _isProfilerDisabled()
    {
        if (isset($GLOBALS['bx_profiler_disable']) || isset($_GET['bx_profiler_disable']))
            return true;

        return false;
    }

    function _calcTime ($begin)
    {
        $i1 = explode(' ', microtime ());
        $i2 = explode(' ', $begin);
        return ($i1[0]+$i1[1]) - ($i2[0]+$i2[1]);
    }

    function _debugPrintArray ($mixed)
    {
        $sArgs .= 'Array(';
        foreach ($mixed as $mixed2)
            $sArgs .= (is_object($mixed2) ? $this->_debugPrintObject($mixed2) : $this->_debugPrintAny($mixed2)) . ',';
        $sArgs = substr($sArgs, 0, -1);
        $sArgs .= ')';
        return $sArgs;
    }

    function _debugPrintObject ($mixed)
    {
        return get_class($mixed) . ' instance';
    }

    function _debugPrintAny ($mixed)
    {
        if (is_string)
            return "'" . (strlen($mixed) > $this->_sLogMaxArgLength ? substr($mixed, 0, $this->_sLogMaxArgLength) . '...' : $mixed) . "'";
        else
            return $mixed;
    }

    function _debugBackTrace ($iShifts = 0)
    {
        $a = debug_backtrace();
        while (--$iShifts > -1)
            array_shift($a);

        $s = '';
        foreach ($a as $r) {

            $sArgs = '';
            foreach ($r['args'] as $mixed) {
                switch (true) {
                case is_array($mixed):
                    $sArgs .= $this->_debugPrintArray($mixed);
                    break;
                case is_object($mixed):
                    $sArgs .= $this->_debugPrintObject($mixed);
                    break;
                default:
                    $sArgs .= $this->_debugPrintAny($mixed);
                }
                $sArgs .= ',';
            }
            $sArgs = substr($sArgs, 0, -1);

            $s .= "--------------------------------------\n";
            $s .= "{$r['line']} {$r['file']}\n";
            $s .= "{$r['class']}{$r['type']}{$r['function']} ({$sArgs});\n";
        }
        return $s;
    }

    // Função auxiliar para formatar arrays com limite de profundidade
    private function _formatArray($array, $depth = 2, $currentDepth = 0)
    {
        if ($currentDepth >= $depth || !is_array($array)) {
            return '...';
        }
        $items = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $items[] = $key . ' => array(' . $this->_formatArray($value, $depth, $currentDepth + 1) . ')';
            } elseif (is_object($value)) {
                $items[] = $key . ' => object(' . get_class($value) . ') ' . $this->_formatObject($value, $depth, $currentDepth + 1);
            } else {
                $items[] = $key . ' => ' . var_export($value, true);
            }
        }
        return implode(', ', $items);
    }
    
    // Função auxiliar para formatar objetos com limite de profundidade
    private function _formatObject($object, $depth = 2, $currentDepth = 0)
    {
        if ($currentDepth >= $depth) {
            return '...';
        }
        $reflection = new ReflectionObject($object);
        $properties = $reflection->getProperties();
        $props = array();
        foreach ($properties as $prop) {
            $prop->setAccessible(true);
            $value = $prop->getValue($object);
            $name = $prop->getName();
            if (is_array($value)) {
                $props[] = $name . ' => array(' . $this->_formatArray($value, $depth, $currentDepth + 1) . ')';
            } elseif (is_object($value)) {
                $props[] = $name . ' => object(' . get_class($value) . ') ' . $this->_formatObject($value, $depth, $currentDepth + 1);
            } else {
                $props[] = $name . ' => ' . var_export($value, true);
            }
        }
        return '{' . implode(', ', $props) . '}';
    }
}

$GLOBALS['bx_profiler'] = new BxProfiler(BX_DOL_START);
if (!$GLOBALS['bx_profiler']->_isProfilerDisabled())
    register_shutdown_function (array ($GLOBALS['bx_profiler'], 'output'));

/** @} */
