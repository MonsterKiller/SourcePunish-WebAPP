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

$GLOBALS['theme']->AddTitle($GLOBALS['trans'][1009]);

/* TODO
    - Login form fall-back
    - Text about login (cookies)
    - Move button to theming?
*/

if($_GET['q'] == 'logout') {
    if(USER_LOGGEDIN)
        $GLOBALS['auth']->EndSession();
    Redirect('^');
} else {
    if(USER_LOGGEDIN)
        Redirect('^');
    else {
        if(isset($_GET['openid_signed']) && $_GET['openid_signed'] != '') {
            $TestLogin = $GLOBALS['auth']->ValidateLogin();
            if($TestLogin === FALSE)
                Redirect('^login');
            if(!$GLOBALS['steam']->Valid64($TestLogin))
                Redirect('^login');
            $GLOBALS['auth']->SetSession($TestLogin);
            Redirect('^');
        }
        $Title = $GLOBALS['trans'][3001];
        $GetLoginButton = '<div class="center"><a href="'.$GLOBALS['auth']->GetLoginURL().'" title="'.$Title.'" class="steam-openid-button">';
        $GetLoginButton .= '<img src="'.HTML_IMAGES.'steam_signin_big.png" alt="'.$Title.'" />';
        $GetLoginButton .= '</a></div>';
        $GLOBALS['theme']->AddContent($Title, $GetLoginButton);
        unset($Title);
    }
}
?>