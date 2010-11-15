<?php
/**
 * Copyright 2009, 2010 Yuri Timofeev tim4dev@gmail.com
 * @author Yuri Timofeev <tim4dev@gmail.com>
 * @package webacula
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU Public License
 */
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
error_reporting( E_ALL | E_STRICT );

/* Determine the root and library directories of the application */
$appRoot = realpath(dirname(__FILE__) . '/../..');
$libDir   = "$appRoot/library";
$modelDir = "$appRoot/application/models";
$formDir  = "$appRoot/application/forms";
$path = array( $libDir, $modelDir, $formDir, get_include_path() );
set_include_path(implode(PATH_SEPARATOR, $path));

defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', $appRoot . '/application');
//echo "APPLICATION_PATH = ",APPLICATION_PATH, "\n", 'appRoot = ',$appRoot, "\n", 'libDir = ', $libDir, "\n", var_dump($path), "\n"; exit; // debug !!!

require_once "Zend/Loader/Autoloader.php";
Zend_Loader_Autoloader::getInstance();

/*
 * from index.php
 */
define('WEBACULA_VERSION', '5.5.x, build for tests');
define('BACULA_VERSION', 12); // Bacula Catalog version
define('ROOT_DIR', $appRoot );
define('TMP_DIR',  ROOT_DIR.'/data/tmp' );
define('CACHE_DIR',ROOT_DIR.'/data/cache' );

// load my ACL classes
Zend_Loader::loadClass('MyClass_WebaculaAcl');
Zend_Loader::loadClass('MyClass_ControllerAclAction');
Zend_Loader::loadClass('MyClass_BaculaAcl');
Zend_Loader::loadClass('Wbresources');
Zend_Loader::loadClass('Wbroles');
// other my classes
Zend_Loader::loadClass('MyClass_HomebrewBase64');
Zend_Loader::loadClass('MyClass_GaugeTime');
Zend_Loader::loadClass('Version');

// load configuration
$config = new Zend_Config_Ini($appRoot . '/application/config.ini', 'general');
$config_webacula = new Zend_Config_Ini($appRoot . '/application/config.ini', 'webacula');
$config_layout   = new Zend_Config_Ini($appRoot . '/application/config.ini', 'layout');

$registry = Zend_Registry::getInstance();
// assign the $config object to the registry so that it can be retrieved elsewhere in the application
$registry->set('config', $config);
$registry->set('config_webacula', $config_webacula);
// set timezone
if ( isset($config->def->timezone) )
    date_default_timezone_set($config->def->timezone);
else {
    Zend_Loader::loadClass('Zend_Date');
    $date = new Zend_Date();
    date_default_timezone_set( $date->getTimeZone() );
}

// set self version
Zend_Registry::set('bacula_version',   BACULA_VERSION);
Zend_Registry::set('webacula_version', WEBACULA_VERSION);

// set global const
Zend_Registry::set('UNKNOWN_VOLUME_CAPACITY', -200); // tape drive
Zend_Registry::set('NEW_VOLUME', -100);
Zend_Registry::set('ERR_VOLUME', -1);

/**
 *
 * Database, table, field and columns names in PostgreSQL are case-independent, unless you created them with double-quotes
 * around their name, in which case they are case-sensitive.
 * Note: that PostgreSQL actively converts all non-quoted names to lower case and so returns lower case in query results.
 * In MySQL, table names can be case-sensitive or not, depending on which operating system you are using.
 *
 */

// setup database bacula
Zend_Registry::set('DB_ADAPTER', strtoupper($config->db->adapter) );
$params = $config->db->config->toArray();
// for cross database compatibility with PDO, MySQL, PostgreSQL
$params['options'] = array(Zend_Db::CASE_FOLDING => Zend_Db::CASE_LOWER, Zend_DB::AUTO_QUOTE_IDENTIFIERS => FALSE);
$db_bacula = Zend_Db::factory($config->db->adapter, $params);
Zend_Db_Table::setDefaultAdapter($db_bacula);
Zend_Registry::set('db_bacula', $db_bacula);

unset($params);
// setup database WEbacula
Zend_Registry::set('DB_ADAPTER_WEBACULA', strtoupper($config_webacula->db->adapter) );
$params = $config_webacula->db->config->toArray();
$params['options'] = array(Zend_Db::CASE_FOLDING => Zend_Db::CASE_LOWER, Zend_DB::AUTO_QUOTE_IDENTIFIERS => FALSE);
$db_webacula = Zend_Db::factory($config_webacula->db->adapter, $params);
Zend_Registry::set('db_webacula', $db_webacula);

// setup controller, exceptions handler
$frontController = Zend_Controller_Front::getInstance();
$frontController->setControllerDirectory($appRoot . '/application/controllers');
if ( $config->debug == 1 ) {
	$frontController->throwExceptions(true);
} else {
	$frontController->throwExceptions(false);
}

// translate
//auto scan lang files may be have bug in ZF ? $translate = new Zend_Translate('gettext', '../languages', null, array('scan' => Zend_Translate::LOCALE_DIRECTORY));
$translate = new Zend_Translate('gettext', '../languages/en/webacula_en.mo', 'en');
// additional languages
// see also http://framework.zend.com/manual/en/zend.locale.appendix.html
$translate->addTranslation('../languages/en/webacula_en.mo', 'en_US');
$translate->addTranslation('../languages/de/webacula_de.mo', 'de');
$translate->addTranslation('../languages/fr/webacula_fr.mo', 'fr');
$translate->addTranslation('../languages/ru/webacula_ru.mo', 'ru');
$translate->addTranslation('../languages/ru/webacula_ru.mo', 'ru_RU');
$translate->addTranslation('../languages/pt/webacula_pt_BR.mo', 'pt_BR');
$translate->addTranslation('../languages/it/webacula_it.mo', 'it');
$translate->addTranslation('../languages/es/webacula_es.mo', 'es');

if ( isset($config->locale) ) {
    // locale is user defined
    $locale = new Zend_Locale(trim($config->locale));
} else {
    // autodetect locale
    // Search order is: given Locale, HTTP Client, Server Environment, Framework Standard
    try {
        $locale = new Zend_Locale('auto');
    } catch (Zend_Locale_Exception $e) {
        $locale = new Zend_Locale('en');
    }
}
if ( $translate->isTranslated('Desktop', false, $locale) ) {
    // can be translated (есть перевод)
    $translate->setLocale($locale);
} else {
    // can't translated (нет перевода)
    // set to English by default
    $translate->setLocale('en');
    $locale = new Zend_Locale('en');
}
// assign the $translate object to the registry so that it can be retrieved elsewhere in the application
$registry->set('translate', $translate);
$registry->set('locale',    $locale);
$registry->set('language',  $locale->getLanguage());

Zend_Layout::startMvc(array(
    'layoutPath' => $appRoot . '/application/layouts/' . $config_layout->path,
    'layout' => 'main'
));

try {
	$db = Zend_Db_Table::getDefaultAdapter();
	$db->getConnection();
} catch (Zend_Db_Adapter_Exception $e) {
	// возможно СУБД не запущена
	throw new Zend_Exception("Fatal error: Can't connect to SQL server");
}
/*
 * Check Bacula Catalog version
 */
$ver = new Version();
if ( !$ver->checkVesion(BACULA_VERSION) )   {
    echo '<pre>';
    throw new Zend_Exception("Version error for Catalog database (wanted ".BACULA_VERSION.",".
            " got ". $ver->getVesion().") ");
}
/*
 * Check TMP_DIR, CACHE_DIR is writable
 */
if ( !is_writable( TMP_DIR ) ) {
    echo '<pre>';
    throw new Zend_Exception('Directory "'.TMP_DIR.'" is not exists or not writable.');
}
if ( !is_writable( CACHE_DIR ) ) {
    echo '<pre>';
    throw new Zend_Exception('Directory "'.CACHE_DIR.'" is not exists or not writable.');
}

Zend_Session::start();

// для подсчета кол-ва неудачных логинов для вывода капчи
$defNamespace = new Zend_Session_Namespace('Default');
if (!isset($defNamespace->numLoginFails))
    $defNamespace->numLoginFails = 0; // начальное значение

/*
 * Zend_Cache
 */
$frontendOptions = array(
    'lifetime' => 3600, // время жизни кэша - 1 час
    'automatic_serialization' => true
);
$backendOptions = array(
    'cache_dir' => CACHE_DIR.'/'      // директория, в которой размещаются файлы кэша
);
// получение объекта Zend_Cache_Core
$cache = Zend_Cache::factory(
    'Core',
    'File',
    $frontendOptions,
    $backendOptions
);
Zend_Registry::set('cache', $cache); // save to Registry


/***************************************
 * end from index.php
 ***************************************/


/* Zend_Application */
require_once 'Zend/Application.php';
require_once 'ControllerTestCase.php';
require_once 'ModelTestCase.php';
