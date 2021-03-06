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

/* TODO
    - Active menu items
    - Tables: Instead if 'custom', 'id', 'class' have 'attr' array
*/

class Theming {
    private $Title = '';
    private $Headers = '';
    private $Styles = '';
    private $StylesRaw = '';
    private $Scripts = '';
    private $ScriptsRaw = '';
    private $Stats = '';
    private $Content = '';

    public function __construct() {
        if(!isset($GLOBALS['sql']))
            die('Error: SQL class not initiated in class.theming, cannot continue!');
        if(!defined('SQL_NAVIGATION'))
            die('Missing definitions in class.theming, cannot continue!');

        if(isset($GLOBALS['settings']['site_title']) && $GLOBALS['settings']['site_title'] != '')
            $this->Title = htmlspecialchars($GLOBALS['settings']['site_title']);
        else
            $this->Title = SP_WEB_NAME;
    }

    public function AddTitle($Text) {
        PrintDebug('Called Theming->AddTitle', 2);
        if($Text == '')
            return;
        $this->Title = htmlspecialchars(substr($Text, 0, 20)).' | '.$this->Title;
    }
    public function AddHeader($Content) {
        PrintDebug('Called Theming->AddHeader', 2);
        if($Content == '')
            return;
        $this->Headers .= $Content."\n";
    }
    public function AddStyle($Sheet, $Media = 'all', $If = '') {
        PrintDebug('Called Theming->AddStyle', 2);
        if($Sheet == '')
            return;
        $this->Styles .= (($If!='')?'<!--['.$If.']>':'').'<link href="'.$Sheet.'" rel="stylesheet" type="text/css" '.(($Media!='')?'media="'.$Media.'"':'').'/>'.(($If!='')?'<![endif]-->':'')."\n";
    }
    public function AddStyleRaw($Content, $Media = 'all', $If = '') {
        PrintDebug('Called Theming->AddStyleRaw', 2);
        if($Content == '')
            return;
        $this->StylesRaw .= (($If!='')?'<!--['.$If.']>':'').'<style'.(($Media!='')?' media="'.$Media.'"':'').'>'.$Content.'</style>'.(($If!='')?'<![endif]-->':'')."\n";
    }
    public function AddScript($URL, $If = '') {
        PrintDebug('Called Theming->AddScript', 2);
        if($URL == '')
            return;
        $this->Scripts .= (($If!='')?'<!--['.$If.']>':'').'<script src="'.$URL.'" type="text/javascript"></script>'.(($If!='')?'<![endif]-->':'')."\n";
    }
    public function AddScriptRaw($Content, $If = '') {
        PrintDebug('Called Theming->AddScriptRaw', 2);
        if($Content == '')
            return;
        $this->ScriptsRaw .= (($If!='')?'<!--['.$If.']>':'').'<script type="text/javascript">'.$Content.'</script>'.(($If!='')?'<![endif]-->':'')."\n";
    }
    private function BuildHead() {
        PrintDebug('Called Theming->BuildHead', 2);
        $this->AddHeader('<title>'.$this->Title.'</title>');
        unset($this->Title);
        $this->AddHeader('<link rel="icon" href="'.HTML_ROOT.'favicon.ico" type="image/x-icon">');
        $this->AddHeader('<meta name="description" content="'.$GLOBALS['settings']['site_description'].'" />');
        $this->AddHeader('<meta name="keywords" content="'.$GLOBALS['settings']['site_keywords'].'" />');
        $this->AddHeader('<meta name="generator" content="'.SP_WEB_NAME.' '.SP_WEB_VERSION.'" />');
        $Header = $this->Headers.$this->Styles.$this->StylesRaw.$this->Scripts.$this->ScriptsRaw;
        unset($this->Headers, $this->Styles, $this->StylesRaw, $this->Scripts, $this->ScriptsRaw);
        return $Header;
    }
    public function BuildHeader() {
        PrintDebug('Called Theming->BuildHeader', 2);
        return '<img src="'.HTML_IMAGES.'logo.png" alt="'.SP_WEB_NAME.'">';
    }
    public function BuildFooter() {
        PrintDebug('Called Theming->BuildFooter', 2);
        return 'Powered by <a title="Visit SourcePunish.net" href="http://SourcePunish.net">'.SP_WEB_NAME.' '.SP_WEB_VERSION.'</a>';
    }
    /* Page theming */
    public function AddHeaderStat($Text) {
        if(function_exists('Subtheme_HeaderStat'))
            $this->Stats .= Subtheme_HeaderStat($Text);
        else if(function_exists('Theme_HeaderStat'))
            $this->Stats .= Theme_HeaderStat($Text);
        else
            $this->Stats .= DefaultTheme_HeaderStat($Text);
    }
    private function BuildNavs() {
        PrintDebug('Called Theming->BuildNavs', 2);
        $AuthTypes = 'nav_auth=\'0\'';
        if(USER_LOGGEDIN) {
            $AuthTypes .= ' OR nav_auth=\'2\'';
            if(USER_ADMIN) {
                $AuthTypes .= ' OR nav_auth=\'3\'';
                if(USER_SUPERADMIN) {
                    $AuthTypes .= ' OR nav_auth=\'4\'';
                }
            }
        } else
            $AuthTypes .= ' OR nav_auth=\'1\'';
        $GetNavs = $GLOBALS['sql']->Query('SELECT * FROM '.SQL_NAVIGATION.' WHERE nav_showing=\'1\' AND ('.$AuthTypes.') ORDER BY nav_position ASC, nav_order ASC');
        $Navs = array(1=>array(), 0=>array());
        while($Row = $GLOBALS['sql']->FetchArray($GetNavs)) {
            $NavItem = array('title'=>ParseText($Row['nav_title']),'url'=>ParseURL($Row['nav_url']));
            if($Row['nav_target'] == 1)
                $NavItem['target'] = '_blank';
            if(function_exists('Subtheme_NavMenuLink'))
                $Link = Subtheme_NavMenuLink($NavItem);
            else if(function_exists('Theme_NavMenuLink'))
                $Link = Theme_NavMenuLink($NavItem);
            else
                $Link = DefaultTheme_NavMenuLink($NavItem);
            unset($NavItem);
            if($Row['nav_parent'] != 0)
                $Navs[$Row['nav_position']][$Row['nav_parent']]['children'][$Row['nav_id']]['link'] = $Link;
            else {
                if(isset($Navs[$Row['nav_position']][$Row['nav_id']]['children']))
                    $Link['children'] = $Navs[$Row['nav_position']][$Row['id']]['children'];
                $Navs[$Row['nav_position']][$Row['nav_id']]['link'] = $Link;
            }
        }
        $GLOBALS['sql']->Free($GetNavs);
        $BuildNav = array(0=>array(), 1=>array());
        if(function_exists('Subtheme_NavMenu')) {
            $BuildNav[0] = Subtheme_NavMenu($Navs[0], 0);
            $BuildNav[1] = Subtheme_NavMenu($Navs[1], 1);
        } else if(function_exists('Theme_NavMenu')) {
            $BuildNav[0] = Theme_NavMenu($Navs[0], 0);
            $BuildNav[1] = Theme_NavMenu($Navs[1], 1);
        } else {
            $BuildNav[0] = DefaultTheme_NavMenu($Navs[0], 0);
            $BuildNav[1] = DefaultTheme_NavMenu($Navs[1], 1);
        }
        unset($Navs);
        return $BuildNav;
    }
    public function AddContent($Title = '', $Content, $Classes = '', $ID = '') {
        PrintDebug('Called Theming->AddContent', 2);
        $Array = array('content'=>$Content);
        if($Title != '') $Array['title'] = $Title;
        if($Classes != '') $Array['class'] = $Classes;
        if($ID != '') $Array['id'] = $ID;
        if(function_exists('Subtheme_AddContent'))
            $this->Content .= Subtheme_AddContent($Array);
        else if(function_exists('Theme_AddContent'))
            $this->Content .= Theme_AddContent($Array);
        else
            $this->Content .= DefaultTheme_AddContent($Array);
    }
    public function FormatContent($Title = '', $Content, $Classes = '', $ID = '') {
        PrintDebug('Called Theming->FormatContent', 2);
        $Array = array('content'=>$Content);
        if($Title != '') $Array['title'] = $Title;
        if($Classes != '') $Array['class'] = $Classes;
        if($ID != '') $Array['id'] = $ID;
        $Content = '';
        if(function_exists('Subtheme_AddContent'))
            $Content .= Subtheme_AddContent($Array);
        else if(function_exists('Theme_AddContent'))
            $Content .= Theme_AddContent($Array);
        else
            $Content .= DefaultTheme_AddContent($Array);
        return $Content;
    }
    public function AddContentRaw($Content) {
        PrintDebug('Called Theming->AddContentRaw', 2);
        $this->Content .= $Content;
    }
    public function BuildTable($Array) {
        $Build = array();
        if(isset($Array['id']) && $Array['id'] != '')
            $Build['id'] = $Array['id'];
        if(isset($Array['class']) && $Array['class'] != '')
            $Build['class'] = $Array['class'];
        if(isset($Array['headings'])) {
            $Build['headings'] = array('content'=>'');
            foreach($Array['headings'] as $Key => $Heading) {
                $Array['headings'][$Key]['heading'] = true;
            }
            if(function_exists('Subtheme_TableCell')) {
                foreach($Array['headings'] as $Heading) {
                    $Build['headings']['content'] .= Subtheme_TableCell($Heading);
                }
            } else if(function_exists('Theme_TableCell')) {
                foreach($Array['headings'] as $Heading) {
                    $Build['headings']['content'] .= Theme_TableCell($Heading);
                }
            } else {
                foreach($Array['headings'] as $Heading) {
                    $Build['headings']['content'] .= DefaultTheme_TableCell($Heading);
                }
            }
            unset($Array['headings']);
            if(function_exists('Subtheme_TableRow'))
                $Build['headings'] = Subtheme_TableRow($Build['headings']);
            else if(function_exists('Theme_TableRow'))
                $Build['headings'] = Theme_TableRow($Build['headings']);
            else
                $Build['headings'] = DefaultTheme_TableRow($Build['headings']);
        }
        $Build['rows'] = '';
        if(isset($Array['rows'])) {
            foreach($Array['rows'] as $Row) {
                $Cells = '';
                if(function_exists('Subtheme_TableCell')) {
                    foreach($Row['cols'] as $Cell) {
                        $Cells .= Subtheme_TableCell($Cell);
                    }
                } else if(function_exists('Theme_TableCell')) {
                    foreach($Row['cols'] as $Cell) {
                        $Cells .= Theme_TableCell($Cell);
                    }
                } else {
                    foreach($Row['cols'] as $Cell) {
                        $Cells .= DefaultTheme_TableCell($Cell);
                    }
                }
                unset($Row['cols']);
                $Row['content'] = $Cells;
                unset($Cells);
                if(function_exists('Subtheme_TableRow'))
                    $Build['rows'] .= Subtheme_TableRow($Row);
                else if(function_exists('Theme_TableRow'))
                    $Build['rows'] .= Theme_TableRow($Row);
                else
                    $Build['rows'] .= DefaultTheme_TableRow($Row);
            }
        }
        unset($Array['rows']);
        if(function_exists('Subtheme_Table'))
            $Build = Subtheme_Table($Build);
        else if(function_exists('Theme_Table'))
            $Build = Theme_Table($Build);
        else
            $Build = DefaultTheme_Table($Build);
        return $Build;
    }
    public function BuildComments($Array) {
        /* TODO, ATTR */
        $Comments = '';
        foreach($Array['comments'] as $ID => $Comment) {
            if(isset($Comment['attachments'])) {
                $Attachments = '';
                foreach($Comment['attachments'] as $Attachment) {
                    $Attachment['title'] = $GLOBALS['trans'][3016];
                    if(function_exists('Subtheme_CommentAttachment'))
                        $$Attachments .= Subtheme_CommentAttachment($Attachment);
                    else if(function_exists('Theme_CommentAttachment'))
                        $Attachments .= Theme_CommentAttachment($Attachment);
                    else
                        $Attachments .= DefaultTheme_CommentAttachment($Attachment);
                }
                if(function_exists('Subtheme_CommentAttachmentContainer'))
                    $Comment['attachments'] = Subtheme_CommentAttachmentContainer($Attachments);
                else if(function_exists('Theme_CommentAttachmentContainer'))
                    $Comment['attachments'] = Theme_CommentAttachmentContainer($Attachments);
                else
                    $Comment['attachments'] = DefaultTheme_CommentAttachmentContainer($Attachments);
                unset($Attachments);
            }
            if(function_exists('Subtheme_Comment'))
                $Comments .= Subtheme_Comment($Comment);
            else if(function_exists('Theme_Comment'))
                $Comments .= Theme_Comment($Comment);
            else
                $Comments .= DefaultTheme_Comment($Comment);
        }
        unset($Array['comments']);
        if(function_exists('Subtheme_CommentContainer'))
            $Comments = Subtheme_CommentContainer($Comments);
        else if(function_exists('Theme_CommentContainer'))
            $Comments = Theme_CommentContainer($Comments);
        else
            $Comments = DefaultTheme_CommentContainer($Comments);
        return $Comments;
    }
    public function Paginate($TotalPages, $CurrentPage, $PagePrefix, $PageSuffix = '') {
        PrintDebug('Called Theming->Paginate', 2);
        if($TotalPages > 1) {
            $MaxNumbers = 20;
            $Links = array();
            if($TotalPages <= $MaxNumbers) {
                for($i = 1; $i <= $TotalPages; $i++) {
                    $Link = array('url'=>$PagePrefix.$i.$PageSuffix, 'text'=>$i, 'class'=>'');
                    if($i == 1) $Link['class'] .= 'first ';
                    if($i == $CurrentPage) $Link['class'] .= 'active ';
                    if($i == $TotalPages) $Link['class'] .= 'last';
                    $Link['class'] = trim($Link['class']);
                    if($Link['class'] == '') unset($Link['class']);
                    $Links[] = $Link;
                }
            } else {
                $Half = floor($MaxNumbers / 2);
                $Start = $CurrentPage - $Half;
                if($Start < 1) $Start = 1;
                $Total = $Start + ($Half * 2);
                if($Total > $TotalPages) {
                    $Diff = $Total - $TotalPages;
                    $Start -= $Diff;
                    $Total = $TotalPages;
                }
                unset($Half);
                for($i = $Start; $i <= $Total; $i++) {
                    $Link = array('url'=>$PagePrefix.$i.$PageSuffix, 'text'=>$i, 'class'=>'');
                    if($i == $Start) $Link['class'] .= 'first ';
                    if($i == $CurrentPage) $Link['class'] .= 'active ';
                    if($i == $Total) $Link['class'] .= 'last';
                    $Link['class'] = trim($Link['class']);
                    if($Link['class'] == '') unset($Link['class']);
                    $Links[] = $Link;
                }
                unset($Start, $Total);
            }
            $StartLinks[0] = array('url'=>$PagePrefix.'1'.$PageSuffix, 'text'=>'&laquo;&laquo;First', 'class'=>($CurrentPage<=1?'disabled':''));
            $StartLinks[1] = array('url'=>$PagePrefix.($CurrentPage>1?($CurrentPage-1):1).$PageSuffix, 'text'=>'&laquo;Previous', 'class'=>($CurrentPage<=1?'disabled':''));
            array_unshift($Links, $StartLinks[0], $StartLinks[1]);
            unset($StartLinks);
            $EndLinks[0] = array('url'=>$PagePrefix.($CurrentPage<$TotalPages?($CurrentPage+1):$TotalPages).$PageSuffix, 'text'=>'Next&raquo;', 'class'=>($CurrentPage>=$TotalPages?'disabled':''));
            $EndLinks[1] = array('url'=>$PagePrefix.$TotalPages.$PageSuffix, 'text'=>'Last&raquo;&raquo;', 'class'=>($CurrentPage>=$TotalPages?'disabled':''));
            array_push($Links, $EndLinks[0], $EndLinks[1]);
            unset($MaxNumbers);
            $BuildLinks = '';
            if(function_exists('Subtheme_PaginationLink')) {
                foreach($Links as $Link) {
                    $BuildLinks .= Subtheme_PaginationLink($Link);
                }
            } else if(function_exists('Theme_PaginationLink')) {
                foreach($Links as $Link) {
                    $BuildLinks .= Theme_PaginationLink($Link);
                }
            } else {
                foreach($Links as $Link) {
                    $BuildLinks .= DefaultTheme_PaginationLink($Link);
                }
            }
            unset($Links);
            if(function_exists('Subtheme_Pagination')) 
                $BuildLinks = Subtheme_Pagination($BuildLinks);
            else if(function_exists('Theme_Pagination'))
                $BuildLinks = Theme_Pagination($BuildLinks);
            else
                $BuildLinks = DefaultTheme_Pagination($BuildLinks);
            return $BuildLinks;
        }
        return false;
    }
    public function BuildPage() {
        PrintDebug('Called Theming->BuildPage', 2);
        $Page['head'] = $this->BuildHead();
        $Navs = $this->BuildNavs();
        $Page['usernav'] = $Navs[1];
        $Page['mainnav'] = $Navs[0];
        unset($Navs);
        $Page['header'] = $this->BuildHeader();
        $Page['stats'] = $this->Stats;
        unset($this->Stats);
        $Page['content'] = $this->Content;
        unset($this->Content);
        $Page['footer'] = $this->BuildFooter();
        if(function_exists('Subtheme_BuildPage'))
            $Build = Subtheme_BuildPage($Page);
        else if(function_exists('Theme_BuildPage'))
            $Build = Theme_BuildPage($Page);
        else
            $Build = DefaultTheme_BuildPage($Page);
        return $Build;
    }
}
?>