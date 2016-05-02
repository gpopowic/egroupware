<?php
/**
 * EGroupware autloader
 *
 * Normally included by either:
 * - header.inc.php
 * - api/src/loader.php
 * - api/src/loader/common.php
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @version $Id$
 */

// this is only neccessary, if header.inc.php is not included, but api/src/autoload.php directly
if (!defined('EGW_SERVER_ROOT'))
{
	define('EGW_SERVER_ROOT', dirname(dirname(__DIR__)));
	define('EGW_INCLUDE_ROOT', EGW_SERVER_ROOT);
	define('EGW_API_INC', __DIR__);
}

/**
 * New PSR-4 autoloader for EGroupware
 *
 * class_exists('\\EGroupware\\Api\\Vfs');                           // /api/src/Vfs.php
 * class_exists('\\EGroupware\\Api\\Vfs\\Dav\\Directory');           // /api/src/Vfs/Dav/Directory.php
 * class_exists('\\EGroupware\\Api\\Vfs\\Sqlfs\\StreamWrapper');     // /api/src/Vfs/Sqlfs/StreamWrapper.php
 * class_exists('\\EGroupware\\Api\\Vfs\\Sqlfs\\Utils');             // /api/src/Vfs/Sqlfs/Utils.php
 * class_exists('\\EGroupware\\Stylite\\Versioning\\StreamWrapper'); // /stylite/src/Versioning/StreamWrapper.php
 * class_exists('\\EGroupware\\Calendar\\Ui');                       // /calendar/src/Ui.php
 * class_exists('\\EGroupware\\Calendar\\Ui\\Lists');                // /calendar/src/Ui/Lists.php
 * class_exists('\\EGroupware\\Calendar\\Ui\\Views');                // /calendar/src/Ui/Views.php
 */
spl_autoload_register(function($class)
{
	$parts = explode('\\', $class);
	if (array_shift($parts) != 'EGroupware') return;	// not our prefix

	$app = lcfirst(array_shift($parts));
	$base = EGW_INCLUDE_ROOT.'/'.$app.'/src/';
	$path = $base.implode('/', $parts).'.php';

	if (file_exists($path))
	{
		require_once $path;
		//error_log("PSR4_autoload('$class') --> require_once($path) --> class_exists('$class')=".array2string(class_exists($class,false)));
	}
});

/**
 * Old autoloader for EGroupware understanding the following naming schema:
 *
 *	1. new (prefered) nameing schema: app_class_something loading app/inc/class.class_something.inc.php
 *	2. API classes: classname loading phpgwapi/inc/class.classname.inc.php
 *  2a.API classes containing multiple classes per file eg. egw_exception* in class.egw_exception.inc.php
 *	3. eTemplate classes: classname loading etemplate/inc/class.classname.inc.php
 *
 * @param string $class name of class to load
 */
spl_autoload_register(function($class)
{
	// fixing warnings generated by php 5.3.8 is_a($obj) trying to autoload huge strings
	if (strlen($class) > 64 || strpos($class, '.') !== false) return;

	$components = explode('_',$class);
	$app = array_shift($components);
	// classes using the new naming schema app_class_name, eg. admin_cmd
	if (file_exists($file = EGW_INCLUDE_ROOT.'/'.$app.'/inc/class.'.$class.'.inc.php') ||
		// compatibility class in new eGW api dir, eg. exceptions
		file_exists($file = EGW_INCLUDE_ROOT.'/api/inc/class.'.$class.'.inc.php') ||
		// classes using the new naming schema app_class_name, eg. admin_cmd
		isset($components[0]) && file_exists($file = EGW_INCLUDE_ROOT.'/'.$app.'/inc/class.'.$app.'_'.$components[0].'.inc.php') ||
		// classes with an underscore in their name
		!isset($GLOBALS['egw_info']['apps'][$app]) && isset($GLOBALS['egw_info']['apps'][$app . '_' . $components[0]]) &&
			file_exists($file = EGW_INCLUDE_ROOT.'/'.$app.'_'.$components[0].'/inc/class.'.$class.'.inc.php') ||
		// eGW api classes using the old naming schema, eg. html
		file_exists($file = EGW_API_INC.'/class.'.$class.'.inc.php') ||
		// eGW api classes containing multiple classes in on file, eg. egw_exception
		isset($components[0]) && file_exists($file = EGW_API_INC.'/class.'.$app.'_'.$components[0].'.inc.php') ||
		// eGW eTemplate classes using the old naming schema, eg. etemplate
		file_exists($file = EGW_INCLUDE_ROOT.'/etemplate/inc/class.'.$class.'.inc.php') ||
		// include PEAR and PSR0 classes from include_path
		// need to use include (not include_once) as eg. a previous included EGW_API_INC/horde/Horde/String.php causes
		// include_once('Horde/String.php') to return true, even if the former was included with an absolute path
		// only use include_path, if no Composer vendor directory exists
		!isset($GLOBALS['egw_info']['apps'][$app]) && !file_exists(EGW_SERVER_ROOT.'/vendor') &&
			@include($file = strtr($class, array('_'=>'/','\\'=>'/')).'.php'))
	{
		include_once($file);
		//if (!class_exists($class, false) && !interface_exists($class, false)) error_log("autoloading class $class by include_once($file) failed!");
	}
	// allow apps to define an own autoload method
	elseif (isset($GLOBALS['egw_info']['flags']['autoload']) && is_callable($GLOBALS['egw_info']['flags']['autoload']))
	{
		call_user_func($GLOBALS['egw_info']['flags']['autoload'],$class);
	}
});

// if we have a Composer vendor directory, also load it's autoloader, to allow manage our requirements with Composer
if (file_exists(EGW_SERVER_ROOT.'/vendor'))
{
	require_once EGW_SERVER_ROOT.'/vendor/autoload.php';
}
