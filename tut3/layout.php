<?php # layout pro jádro Ezer verze 3  (c) 2010 Martin Smidek <martin@smidek.eu>
$http= "http://192.168.1.5:8080/www-ys2";
$http= "http://{$_SERVER['HTTP_HOST']}";
// echo $http;

  $version= "title='jádro ezer3'";
  $title_right= "TEMPLATE";
  $app= "tut3";
  $css_login= $login= $info= $chngs= '';
  $debugger= $dolni= '';

$body_real= <<<__EOD
<body id="body" x="1" x-ms-format-detection="none">
<!-- menu a submenu -->
  <div id='horni' class="MainBar">
    <div id="appl" $version>$title_right</div>
    <div id='logo'>
      <button id='logoContinue' style='display:none;outline:3px solid orange;'>continue</button>
      <img class="StatusIcon" id="StatusIcon_idle" src="./$app/img/-logo.gif" />
      <img class="StatusIcon" id="StatusIcon_server" src="./$app/img/+logo.gif" />
    </div>
    <ul id="menu" class="MainMenu"></ul>
    <ul id="submenu" class="MainTabs">
      <li id="_help" style="display:block;float:right"><a>HELP<sub>&hearts;</sub></a></li>
    </ul>
  </div>
  <div id='ajax_bar'></div>
<!-- login -->
  <div id="login" style="display:none">
    <div id="login_1" class="$css_login">
      <h1>Přihlášení ...</h1>
      <div class="login_a">
        $login
      </div>
    </div>
    <div id="login_2" class="$css_login">
      <h1 style='text-align:right'>... informace</h1>
      <div class="login_a">
        $info
      </div>
    </div>$chngs
  </div>
<!-- pracovní plocha -->
  <div id="stred">
    <!-- div id="shield"></div -->
    <div id="work"></div>
  </div>
  <div id="uzivatel"></div>
<!-- paticka -->
  <div id="paticka">
    <div id="warning"></div>
    <div id="kuk_err"></div>
    <div id="error" style="margin:-30px 0px 0px;"></div>
  </div>
  <div id="dolni"$dolni>
    <div id="status_bar">
      <div id='status_left' style="float:left;"></div>
      <div id='status_center' style="float:left;">zpráva</div>
      <div id='status_right' style="float:right;"></div>
    </div>
    <div id="trace">
      $debugger
      <pre id="kuk"></pre>
    </div>
  </div>
  <div id="report" class="report"></div>
  <form><input id="drag" type="button" /></form>
  <form id="drag_form" class="ContextMenu" style="display:none;position:absolute;width:200px">
    <input id="drag_title" type="text" style="float:right;width:165px" />
    <div style="padding:3px 0 0 2px;width:30px">title:</div>
  </form>
  <div id="wait_mask">
    <div id="wait" onclick="waiting(0);"></div>
  </div>
<!-- konec -->
</body>
__EOD;

$body_test= <<<__EOD
<body>
<div id="ezer">

  <header>
    Logo<br>Aplikace
    <nav id='menu_main'>
      tabs
    </nav>
    <nav id='menu_tab'>
      panels
    </nav>
  </header>

  <div id="stred">

    <section>
      <div class="MenuLeft3">
        <i class="fa fa-caret-square-o-left"></i>
        <div class="MenuGroup3">
          <a>alfa</a>
          <ul>
            <li><i class="fa fa-at fa-fw efa"></i> item 1.1</li>
            <li class="selected3"><i class="fa fa-at fa-fw efa"></i> item 1.2</li>
          </ul>
        </div>
        <div class="MenuGroup3">
          <a>beta</a>
          <ul>
            <li>...</li> <li>...</li> <li>...</li> <li>...</li>
          </ul>
        </div>
      </div>
      <article class="PanelRight3">
        <div id="label">label</div>
        <div>
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
          Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
        </div>
      </article>
    </section>

  </div>

  <footer>
    <div id="status_bar">status</div>
    <br>trace
    <br>trace
    <br>trace
    <br>trace
  </footer>

</div>
</body>
__EOD;

$body= $body_real;
$body= $body_test;

echo <<<__EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
  <base href="$http/tut3">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=9" />
  <link rel="shortcut icon" href="$http/tut3/img/layout3_local.png" />
  <title>L3</title>
  <script src="$http/ezer3/client/licensed/jquery-3.2.1.min.js" type='text/javascript' charset='utf-8'></script>
  <script src="$http/ezer3/client/licensed/jquery-noconflict.js" type='text/javascript' charset='utf-8'></script>
  <link rel="stylesheet" href="$http/ezer3/client/ezer3.css.php" type="text/css" media="screen" charset="utf-8">
  <link rel="stylesheet" href="$http/ezer2.2/client/licensed/font-awesome/css/font-awesome.min.css" type="text/css" media="screen" charset="utf-8">
  <style>

html,body{
    height: 100%
}
body {
    font-family: Arial,Helvetica,sans-serif;
    padding: 0;
    margin: 0;
    font-size: 9pt;
    position: static;
    overflow: hidden;
    background: #bfdbff;
}


#ezer,#body {
  display: flex; flex-direction: column; justify-content: space-between;
  height: 100%;
}

header,#horni {
  flex-basis: 50px; flex-shrink: 0; flex-grow: 0;
  width:100%; height:50px; overflow:auto;
  border-bottom: 1px solid navy;
}

#stred {
  flex-grow: 1; display: inline-flex;
  overflow:hidden;
}

footer,#dolni {
  flex-basis: 50px; flex-shrink: 0; flex-grow: 0;
  width:100%; overflow:auto;
  border-top: 1px solid navy;
}

section,#work {
  display:flex; flex-direction: row; justify-content: space-between;
}
div.MenuLeft3 {
  flex-basis: 210px; flex-shrink: 0; flex-grow: 0;
  overflow-x: hidden; overflow-y: auto;
  display:block;
  transition: flex-basis 400ms linear;
}
div.MenuLeftFolded3 {
  flex-basis: 30px;
  transition: flex-basis 400ms linear;
}

article {
  flex-grow: 1; position:relative;
  overflow:auto;
}

#label { position:absolute; left:20px; top:20px; width:50px; height:16px; background-color:orange;}

#status_bar {
  height:16px; background-color:silver;
}

form,#paticka,#uziavtel,#login,#report {
  display: none;
}

  </style>
  <script type="text/javascript">

jQuery(document).ready(function() {

  jQuery('div.MenuGroup3 a').click( e => {
    jQuery(e.target).parent().find('ul').slideToggle();
  });

  jQuery('div.MenuLeft3 > i').click( e => {
    jQuery(e.target).parent().toggleClass('MenuLeftFolded3');
    jQuery(e.target).parent().find('i').toggleClass('fa-caret-square-o-left').toggleClass('fa-caret-square-o-right');
//    jQuery(this.owner.DOM_Block)
//      .animate({width:this.owner._folded ? '+=180' : '-=180'},{duration:400,easing:'linear'});
  });

});
  </script>
</head>
$body
</html>
__EOD;
