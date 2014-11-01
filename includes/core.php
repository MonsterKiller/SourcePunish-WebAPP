<?php
/*--------------------------------------------------------+
| SourcePunish WebApp                                     |
| Copyright (C) https://sourcepunish.net                  |
+---------------------------------------------------------+
| This program is free software and is released under     |
| the terms of the GNU Affero General Public License      |
| version 3 as published by the Free Software Foundation. |
| You can redistribute it and/or modify it under the      |
| terms of this license, which is included with this      |
| software as agpl-3.0.txt or viewable at                 |
| http://www.gnu.org/licenses/agpl-3.0.html               |
+--------------------------------------------------------*/
if(preg_match('/core.php/i', $_SERVER['PHP_SELF'])) die('Access Denied!');

/* *TODO*
    - Add system to allow banning users from logging in
    - Custom error reporting
    - Translations
    - Clean code!
*/

$StartTime = microtime(true);
$LastTime = $StartTime;
$StartMem = memory_get_usage();
$LastMem = $StartMem;

define('IN_SP', true);
define('SP_WEB_VERSION', '0.0.3');
date_default_timezone_set('UTC');

/* Show PHP errors for development purposes */
error_reporting(E_ALL);
ini_set('display_errors', '1');

/* Some definitions */
define('DATE_FORMAT', 'H:i jS F Y');
define('DIR_ROOT',    dirname(dirname(__FILE__)).'/');
define('DIR_INCLUDE', DIR_ROOT.'includes/');
define('DIR_THEMES',  DIR_ROOT.'themes/');
$HTMLROOT = dirname($_SERVER['REQUEST_URI']);
$HTMLROOT = str_replace('\\', '/', $HTMLROOT);
if(substr($HTMLROOT, -1, 1) != '/')
    $HTMLROOT .= '/';
if($HTMLROOT == '.')
    $HTMLROOT = '/';
define('HTML_ROOT', $HTMLROOT);
unset($HTMLROOT);
define('HTML_IMAGES', HTML_ROOT.'static/images/');
define('HTML_SCRIPTS', HTML_ROOT.'static/scripts/');
define('HTML_CSS', HTML_ROOT.'static/css/');

/* Get the best IP address for the user */
$IPAddress = '';
if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $Explode = array_values(array_filter(explode(',',$_SERVER['HTTP_X_FORWARDED_FOR'])));
    $IPAddress = end($Explode);
} else if(isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']))
    $IPAddress = $_SERVER['REMOTE_ADDR'];
else if(isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']))
    $IPAddress = $_SERVER['HTTP_CLIENT_IP'];

if($IPAddress != '' && IsValidIP($IPAddress))
    define('USER_ADDRESS', $IPAddress);
else
    define('USER_ADDRESS', 'UNKNOWN');
unset($IPAddress);

/* Load the configuration file */
require_once(DIR_INCLUDE.'config.php');
if(!isset($GLOBALS['config']))
    die("Error: Configuration(s) missing in file config.php");

/* Debugging message function */
function PrintDebug($Text) {
    global $StartTime, $LastTime, $StartMem, $LastMem;
    if(isset($GLOBALS['config']['system']['printdebug']) && $GLOBALS['config']['system']['printdebug']) {
        $Time = microtime(true);
        $Mem = memory_get_usage();
        echo '<!-- DEBUG "'.$Text.'" TIME:'.number_format($Time-$StartTime, 11).'/'.number_format($Time-$LastTime, 11).' |  MEM:'.number_format($Mem).'B/'.number_format($Mem-$LastMem).'B -->'."\n";
        $LastTime = $Time;
        $LastMem = $Mem;
    }
}
PrintDebug('Config Loaded');

/* Load & connect to MySQL */
if(!isset($GLOBALS['config']['sql'], $GLOBALS['config']['sql']['host'], $GLOBALS['config']['sql']['username'], $GLOBALS['config']['sql']['password'], $GLOBALS['config']['sql']['database'], $GLOBALS['config']['sql']['prefix']))
    die("Error: SQL configuration(s) missing in file config.php"); 
require_once(DIR_INCLUDE.'class.sql.php');
$GLOBALS['sql'] = new SQL($GLOBALS['config']['sql']['host'], $GLOBALS['config']['sql']['username'], $GLOBALS['config']['sql']['password'], $GLOBALS['config']['sql']['database']);
PrintDebug('SQL Connected');
define('SQL_PREFIX', $GLOBALS['sql']->Escape($GLOBALS['config']['sql']['prefix']));
unset($GLOBALS['config']['sql']);

/* Load settings */
$GLOBALS['settings'] = array();
$SettingsQuery = $GLOBALS['sql']->Query('SELECT * FROM '.SQL_PREFIX.'settings');
while($Row = $GLOBALS['sql']->FetchArray($SettingsQuery)) {
    $GLOBALS['settings'][$Row['setting_name']] = $Row['setting_value'];
    switch($Row['setting_value']) {
        case 'true': $GLOBALS['settings'][$Row['setting_name']] = true; break;
        case 'false': $GLOBALS['settings'][$Row['setting_name']] = false; break;
    }
}
$GLOBALS['sql']->Free($SettingsQuery);
PrintDebug('Settings Loaded');

/* Steam */
require_once(DIR_INCLUDE.'class.steam.php');
$GLOBALS['steam'] = new Steam();
PrintDebug('Steam Class Loaded');

/* Session/Auth */
require_once(DIR_INCLUDE.'class.auth.php');
$GLOBALS['auth'] = new Auth();
$IsValidSession = $GLOBALS['auth']->ValidateSession();
if($IsValidSession) {
    if($GLOBALS['auth']->IsAdmin()) {
        define('USER_ADMIN', true);
        if($GLOBALS['auth']->HasAdminFlag($GLOBALS['settings']['superadmin_flag']))
            define('USER_SUPERADMIN', true);
    }
    define('USER_LOGGEDIN', true);
}
if(!defined('USER_ADMIN')) define('USER_ADMIN', false);
if(!defined('USER_SUPERADMIN')) define('USER_SUPERADMIN', false);
if(!defined('USER_LOGGEDIN')) define('USER_LOGGEDIN', false);
PrintDebug('Auth Class Loaded & Checked');

/* Theming */
$ThemeName = $GLOBALS['settings']['site_theme'];
if(!file_exists(DIR_THEMES.$ThemeName.'/theme.php')) {
    /* Log as an error ? */
    if(!file_exists(DIR_THEMES.'SourcePunish/theme.php'))
        die("ERROR! Cannot find user or default theme file.");
    else
        $ThemeName = 'SourcePunish';
}
define('THEME_CURRENT', $ThemeName); 
unset($ThemeName);
define('THEME_PATH', DIR_THEMES.THEME_CURRENT.'/');
define('HTML_THEME_PATH', HTML_ROOT.'themes/'.THEME_CURRENT.'/');

require_once(DIR_INCLUDE.'class.theming.php');

/* Functions */
function IsSSL() {
    if(isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS']) == 'on' || $_SERVER['HTTPS'] == '1')) {
        return true;
    } else if(isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443') {
        return true;
    }
    return false;
}
function IsValidIP($IPAddress, $Type = 'both') {
    if($Type == 'ipv4' || $Type == 'both') {
        if(preg_match('/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/', $IPAddress))
            return true;
    }
    if($Type == 'ipv6' || $Type == 'both') {
        if(preg_match('/^(((?=.*(::))(?!.*\3.+\3))\3?|([\dA-F]{1,4}(\3|:\b|$)|\2))(?4){5}((?4){2}|(((2[0-4]|1\d|[1-9])?\d|25[0-5])\.?\b){4})\z$/i', $IPAddress))
            return true;
    }
    return false;
}
?>