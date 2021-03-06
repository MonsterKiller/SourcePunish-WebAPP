<?php
/*--------------------------------------------------------+
| SourcePunish WebApp                                     |
| Copyright (C) 2015 https://sourcepunish.net             |
+---------------------------------------------------------+
| This program is free software and is released under     |
| the terms of the GNU Affero General Public License      |
| version 3 as published by the Free Software Foundation. |
| You can redistribute it and/or modify it under the      |
| terms of this license, which is included with this      |
| software as agpl-3.0.txt or viewable at                 |
| http://www.gnu.org/licenses/agpl-3.0.html               |
+--------------------------------------------------------*/
if(!defined('IN_SP')) die('Access Denied!');
/* TODO:
    - Better integration with sm_admins DB, can use new steamid? look at groups?
    - Validate admin sql settings in core if needed
    - Create & code for admin table where not using sm_admins
    - Check new and old SteamID's
*/
class Auth {
    private $OpenIDURL = 'https://steamcommunity.com/openid/login';
    private $UserID = null;
    private $UserName = '';
    private $UserAdmin = false;
    private $UserAdminFlags = array();

	public function __construct() {
        if(!isset($GLOBALS['sql']))
            die('Error: SQL class not initiated in class.auth, cannot continue!');
        if(!isset($GLOBALS['steam']))
            die('Steam class not initiated in class.auth, cannot continue!');
        if(!defined('SQL_SESSIONS'))
            die('Missing definitions in class.auth, cannot continue!');
    }

    public function GetLoginURL() {
        PrintDebug('Called Auth->GetLoginURL', 2);
        $OpenIDParams = array(
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => (IsSSL()?'https':'http').'://'.$_SERVER['HTTP_HOST'].ParseURL('^login'),
            'openid.realm' => (IsSSL()?'https':'http').'://'.$_SERVER['HTTP_HOST'],
            'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
            'openid.claimed_id'	=> 'http://specs.openid.net/auth/2.0/identifier_select',
        );
        return $this->OpenIDURL.'?'.http_build_query($OpenIDParams, '', '&amp;');
    }
    public function ValidateLogin() {
        PrintDebug('Called Auth->ValidateLogin', 2);
        $OpenIDParams = array(
            'openid.assoc_handle' => $_GET['openid_assoc_handle'],
            'openid.signed' => $_GET['openid_signed'],
            'openid.sig' => $_GET['openid_sig'],
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
        );
        $SignedArray = explode(',', $_GET['openid_signed']);
        foreach($SignedArray as $Signed) {
            $SignedValue = $_GET['openid_'.str_replace('.', '_', $Signed)];
            $OpenIDParams['openid.'.$Signed] = get_magic_quotes_gpc()?stripslashes($SignedValue):$SignedValue; 
        }
        unset($SignedArray);
        $OpenIDParams['openid.mode'] = 'check_authentication';
        $HTTPQuery = http_build_query($OpenIDParams);
        unset($OpenIDParams);
        $Stream = stream_context_create(array(
            'http' => array(
                'method' => 'POST',
                'header' => "Accept-language: en\r\nContent-type: application/x-www-form-urlencoded\r\nContent-Length: ".strlen($HTTPQuery)."\r\nConnection: close\r\n",
                'content' => $HTTPQuery,
            ),
        ));
        unset($HTTPQuery);
        $GetResponse = file_get_contents($this->OpenIDURL, false, $Stream);
        unset($Stream);
        if($GetResponse === false)
            return false;
        if(preg_match('#^http://steamcommunity.com/openid/id/([0-9]{17,20})#', $_GET['openid_claimed_id'], $Matches)) {
            if(count($Matches) == 2 && preg_match('/is_valid\s*:\s*true/i', $GetResponse) == 1) {
                return (string)$Matches[1];
            }
        }
        return false;
    }
    public function SetSession($Steam64) {
        PrintDebug('Called Auth->SetSession with \''.$Steam64.'\'', 2);
        $Time = time();
        $SessionID = sha1($Steam64.':'.$Time.':'.USER_ADDRESS);
        $SteamName = $GLOBALS['steam']->GetSteamName($Steam64);
        if($SteamName !== false)
            $SteamName = $GLOBALS['sql']->Escape($SteamName);
        else
            $SteamName = $GLOBALS['sql']->Escape($Steam64);;
        $SessionID = $GLOBALS['sql']->Escape($SessionID);
        $Steam64 = $GLOBALS['sql']->Escape($Steam64);
        $UserIP = $GLOBALS['sql']->Escape(USER_ADDRESS);
        if($GLOBALS['sql']->Query_Rows('SELECT session_id FROM '.SQL_SESSIONS.' WHERE session_user=\''.$Steam64.'\' LIMIT 1') == 1)
            $GLOBALS['sql']->Query('UPDATE '.SQL_SESSIONS.' SET session_id=\''.$SessionID.'\', session_time=\''.$Time.'\', session_user_name=\''.$SteamName.'\', session_user_ip=\''.$UserIP.'\' WHERE session_user=\''.$Steam64.'\' LIMIT 1');
        else
            $GLOBALS['sql']->Query('INSERT INTO '.SQL_SESSIONS.' (session_id, session_user, session_user_name, session_user_ip, session_time) VALUES (\''.$SessionID.'\', \''.$Steam64.'\', \''.$SteamName.'\', \''.$UserIP.'\', \''.$Time.'\')');
        setcookie('SP_SESSION_ID', $SessionID, (int)$GLOBALS['settings']['site_session_timeout']);
        return true;
    }
    public function ValidateSession() {
        PrintDebug('Called Auth->ValidateSession', 2);
        if(isset($_COOKIE['SP_SESSION_ID']) && strlen($_COOKIE['SP_SESSION_ID']) == 40) {
            $SessionID = $GLOBALS['sql']->Escape($_COOKIE['SP_SESSION_ID']);
            $SessionQuery = $GLOBALS['sql']->Query('SELECT session_user, session_user_name, session_user_ip FROM '.SQL_SESSIONS.' WHERE session_id=\''.$SessionID.'\' LIMIT 1');
            if($GLOBALS['sql']->Rows($SessionQuery) == 1) {
                $SessionArray = $GLOBALS['sql']->FetchArray($SessionQuery);
                if(USER_ADDRESS == $SessionArray['session_user_ip']) {
                    $this->UserID = $SessionArray['session_user'];
                    $this->UserName = $SessionArray['session_user_name'];
                    $this->CheckAdmin();
                    $GLOBALS['sql']->Free($SessionQuery);
                    return true;
                } else
                    $this->EndSession();
            }
            $GLOBALS['sql']->Free($SessionQuery);
        }
        return false;
    }
    public function EndSession() {
        PrintDebug('Called Auth->EndSession', 2);
        setcookie('SP_SESSION_ID', '', time()-3600);
        /* Should we redirect ? */
    }
    private function CheckAdmin($Steam64 = null) {
        PrintDebug('Called Auth->CheckAdmin with \''.$Steam64.'\'', 2);
        if($Steam64 == null && $this->UserID == null)
            return false;
        if($Steam64 == null && $this->UserID != null)
            $Steam64 = $this->UserID;
        $SteamID = $GLOBALS['steam']->Steam64ToID($Steam64, false);
        $SteamID3 = $GLOBALS['steam']->Steam64ToID($Steam64, true);
        $AdminArray = array();
        if($GLOBALS['config']['admins']['useexisting'] == true) {
            if(!$GLOBALS['config']['admins']['differentdb']) {
                $AdminTable = $GLOBALS['sql']->Escape($GLOBALS['config']['admins']['table']);
                $SteamID = $GLOBALS['sql']->Escape($SteamID);
                $SteamID3 = $GLOBALS['sql']->Escape($SteamID3);
                $AdminQuery = $GLOBALS['sql']->Query('SELECT flags FROM '.$AdminTable.' WHERE authtype=\'steam\' AND (identity=\''.$SteamID.'\' OR identity=\''.$SteamID3.'\') LIMIT 1');
                if($GLOBALS['sql']->Rows($AdminQuery) == 1)
                    $AdminArray = $GLOBALS['sql']->FetchArray($AdminQuery);
                $GLOBALS['sql']->Free($AdminQuery);
            } else {
                $AdminSQL = new SQL($GLOBALS['config']['admins']['host'], $GLOBALS['config']['admins']['username'], $GLOBALS['config']['admins']['password'], $GLOBALS['config']['admins']['database']);
                $AdminTable = $AdminSQL->Escape($GLOBALS['config']['admins']['table']);
                $SteamID = $AdminSQL->Escape($SteamID);
                $SteamID3 = $AdminSQL->Escape($SteamID3);
                $AdminQuery = $AdminSQL->Query('SELECT flags FROM '.$AdminTable.' WHERE authtype=\'steam\' AND (identity=\''.$SteamID.'\' OR identity=\''.$SteamID3.'\') LIMIT 1');
                if($AdminSQL->Rows($AdminQuery) == 1)
                    $AdminArray = $AdminSQL->FetchArray($AdminQuery);
                $AdminSQL->Free($AdminQuery);
                $AdminSQL->Close();
            }
        } else {
            /* Use built-in DB */
            /* TODO Add */
        }
        if(!empty($AdminArray)) {
            $this->UserAdminFlags = str_split($AdminArray['flags']);
            if($this->HasAdminFlag('b')) {
                $this->UserAdmin = true;
                return true;
            } else
                $this->UserAdminFlags = array();
        }
        return false;
    }
    public function HasAdminFlag($Flag) {
        PrintDebug('Called Auth->AdminHasFlag with \''.$Flag.'\'', 2);
        if(strlen($Flag) > 1) {
            $Flags = str_split($Flag);
            foreach($Flags as $LoopFlag) {
                if($this->HasAdminFlag($LoopFlag))
                    return true;
            }
            return false;
        } else {
            if(in_array('z', $this->UserAdminFlags) || in_array($Flag, $this->UserAdminFlags))
                return true;
            else
                return false;
        }
    }
    public function GetName() {
        PrintDebug('Called Auth->GetName', 2);
        if($this->UserName)
            return $this->UserName;
        else
            return false;
    }
    public function GetUser64() {
        PrintDebug('Called Auth->GetUser64', 2);
        if($this->UserID != null)
            return $this->UserID;
        else
            return false;
    }
    public function IsAdmin() {
        PrintDebug('Called Auth->IsAdmin', 2);
        return $this->UserAdmin;
    }
}
