<?php
# --------------------------------------------------------------------------------------==> page
function page($app_root,$page) {  trace();
  global $CMS;
  $CMS= true;
  ezer_connect('setkani');
  $href= "http://$app_root/panel.php?trace=uU&page=";
                                                display("href=$href");
  $path= $page ? explode('!',$page) : array('home');
                                                debug($path,"path");
  $user= isset($_GET['user']) ? $_GET['user'] : 0;
  return template($href,$path,$user,0);
}
# --------------------------------------------------------------------------------------==> template
# path= [stranka, id2, ...] jako rozklad předaného url
#   stránka = home | kontakty | search | mapa
function template($href,$path,$user=0,$echo=1) { trace();
global $CMS;
global $def_pars, $eb_link, $eb_script, $href0, $edit;
ezer_connect('setkani');
$edit= $user==1024;
// $edit= 1;
global $trace, $ezer_local;
$href0= $href;
# ---------------------------------------------------------------------------------==> HTML elementy
$abstrakt= <<<__EOD
      <div class='abstrakt x' onclick="location.href='{$href}clanek';">
        abstrakt
      </div>
__EOD;

$list= <<<__EOD
  <div id='list' class='x'>
      $abstrakt
  </div>
__EOD;

$clanek= <<<__EOD
  <div id='clanek' class='x' onclick="history.back();">
    <div class='clanek x'>
      článek
    </div>
  </div>
__EOD;
# ---------------------------------------------------------------------------------==> definice menu

# blok = typ_bloku : název : next : elem*;      -- popis stránky, pokud typ=menu je popsáno dál
# path = id*,                                   -- defaulní pokračování cesty
# elem = typ_elementu = id*,                    -- popis elementu stránky

$def_block= array(
  # hlavní menu    typ id název             next/default       elem ...
  'plan_akci'   => 'hm: 1:Akce chystané::   manzele:           proc=plan; akce2=bude',
  'archiv_akci' => 'hm: 1:Archiv akcí::     manzele,letos:     proc=zpet; akce2=bylo',
  'dum'         => 'hm::  Dům setkání:      archiv: letos:     menu=alberice,chystame,archiv,objednavky',
  'foto'        => 'hm::  Foto&shy;galerie:::                  foto',
  'libr'        => 'hm::  Knihovnička:::                       block=list',
  'my'          => 'hm: 9:O nás:::                             clanky=21,15,13', //o_nas',
  # Dům setkání
  'alberice'    => 'sm: 3:Albeřice:::                          clanky=37',
  'chystame'    => 'sm: 1:Chystáme akce::   alberice:          proc=aplan; akce2=bude',
  'archiv'      => 'sm: 1:Prožili jsme::    letos:             proc=azpet; akce2=bylo',
  'objednavky'  => 'sm::  Objednávky:::                        proc=objednavky',
  # speciální stránky
  'home'        => 'tm::  Domů:::                              home',
  'kontakty'    => 'tm: 9:Kontakty:::                          clanek=79', //kontakty',
  'test'        => 'tm: 9:TEST:::                              clanek=31', //admin/try/test',
//   'mapa'        => 'tm::  Mapa webu:::                         block=clanek',
  'hledej'      => 'tm::  Hledej:::                            block=clanek',
);

$def_pars= array(
  'komu' => array(
                  'rodiny'   => 'rodiny:1',
                  'manzele'  => 'manželé:2',
                  'chlapi'   => 'chlapi:3',
                  'zeny'     => 'ženy:4',
                  'mladez'   => 'mládež:5',
                  'alberice' => 'Albeřice:6',
                ),
  'bylo' => array(
                  'letos'  => 'letos:YEAR(FROM_UNIXTIME(fromday))=YEAR(NOW())',
                  'vloni'  => 'vloni:YEAR(NOW())-YEAR(FROM_UNIXTIME(fromday))=1',
                  '5let'   => 'před 2-5 lety:YEAR(NOW())-YEAR(FROM_UNIXTIME(fromday)) BETWEEN 2 AND 5',
                  '10let'   => 'před 6-10 lety:YEAR(NOW())-YEAR(FROM_UNIXTIME(fromday)) BETWEEN 6 AND 10',
                  'starsi' => 'dříve:YEAR(NOW())-YEAR(FROM_UNIXTIME(fromday))>10',
                )
);

# zobrazíme topmenu a hlavní menu, přitom zjistíme případné doplnění cesty a její správnost
$topmenu= $mainmenu= $submenu= $page= $elems= $pars= '';
$page_ok= false;        // je dobře definovaná aktivní stránka
foreach ($def_block as $ref=>$def) {
  list($typ_bloku,$id1,$nazev,$next,$default1,$elems1)= explode(':',$def);
  $next= str_replace(' ','',$next);
  $default1= str_replace(' ','',$default1);
  $elems1= str_replace(' ','',$elems1);
  if ( $typ_bloku=='tm' ) {
    list($elem)= explode(';',$elems1);
    $active= $path[0]==$ref ? ' active' : '';
    if ( $active ) {
      $page_ok= true;
      $page= $ref;
      $elems= $elems1;
    }
    $topmenu.= $CMS
      ? " <a onclick='go(\"$href$ref\");' class='jump$active'>$nazev</a>"
      : " <a href='$href$ref' class='jump$active'>$nazev</a>";
  }
  elseif ( $typ_bloku=='hm' ) {
    list($elem)= explode(';',$elems1);
    list($typ,$ids)= explode('=',$elem);
    $active= $path[0]==$ref ? ' active' : '';
    if ( $active ) {
      $page= $ref;
      $elems= $elems1;
      $href0.= $ref;
    }
    # doplnění defaultní cesty
    $ref.= $next ? "!$next" : '';
//     $ref.= $default1 ? '!'.str_replace(',','!',$default1) : '';
    $ref.= $default1 ? '!'.$default1 : '';
    $on= '';
    if ( $typ=='menu' && $active ) {
      $submenu.= "<div id='page_sm'><span class='label'>$nazev:</span>";
      foreach (explode(',',$ids) as $ref2) {
        list($typ2,$id2,$nazev2,$next2,$default2,$elems2)= explode(':',$def_block[$ref2]);
        $next2= str_replace(' ','',$next2);
        $default2= str_replace(' ','',$default2);
        $elems2= str_replace(' ','',$elems2);
        $active2= $path[1]==$ref2 ? ' active' : '';
        if ( $active2 ) {
          $page_ok= true;
          $page= $ref2;
          $elems= $elems2;
          $href0.= "!$ref2";
        }
         # doplnění defaultní cesty
        $ref2.= $default2 ? '!'.str_replace(',','!',$default2) : '';
        $submenu.= " <a href='$href{$path[0]}!$ref2' class='jump$active2'>$nazev2</a>";
      }
      $submenu.= "</div>";
      array_shift($path);
    }
    elseif ( $active ) $page_ok= true;
    $mainmenu.= $CMS
      ? " <a onclick='go(\"$href$ref\");' class='jump$active'$on>$nazev</a>"
      : " <a href='$href$ref' class='jump$active'$on>$nazev</a>";
  }
}
                                                        display("page=$page, elems=$elems, ok=$page_ok");
# zobrazíme stránku $page podle jeho $elems a $pars
$body= $submenu;
                                                        debug($path);
$par= array_shift($path);
                                                        display("par=$par");
# projdeme elementy
$vyber= '';
foreach (explode(';',$elems) as $elem) {
  list($typ,$ids)= explode('=',$elem);
  $typ= str_replace(' ','',$typ);
                                                        display("elem:$typ=$ids, vyber=$vyber");
  switch ($typ) {

  case 'block': # ------------------------------------------------- . block
    # ukázkový blok
    $body.= $$ids;
    break;

  case 'clanek': # ---------------------------------------------==> . clanek
    # článek zadaný názvem nebo cid
    if ( $edit ) {
      $body.= "<div id='clanek' class='x'>
                 <script>clanek('clanek','$ids')</script></div>";
    }
    else {
      $x= clanek($ids);
      $body.= "<div id='clanek' class='x'><div class='clanek x'>
                 <h1>$x->nadpis</h1>$x->obsah</div></div>";
    }
    break;

  case 'clanky': # ------------------------------------------------ . clanky
    # seznam abstraktů článků zadaných jménem menu nebo pid
    # může následovat ident jednoho z článků (vznikne kliknutím na abstrakt)
    $body.= "<div id='list' class='x'>";
    $id= array_shift($path);
    list($id)= explode('#',$id);
    $xx= clanky($ids,$id);
    foreach($xx as $x) {
                                                        display("čláky {$x->ident} ? $id");
      $body.= $x->ident==$id
            ? "<div id='clanek' class='x'><div class='clanek x' onclick='history.back();'>
               <h1>$x->nadpis</h1>$x->abstract</div></div>"
//             : "<div class='x'>
//                  <div class='abstrakt x' onclick=\"location.href='$href0!$x->ident#clanek';\">
//                    <b>$x->nadpis:</b> $x->abstract</div></div>";
            : "<div class='x'>
                 <div class='abstrakt x' onclick=\"go('$href0!$x->ident#clanek');\">
                   <b>$x->nadpis:</b> $x->abstract</div></div>";
    }
    $body.= "</div>";
    break;

  case 'home':    # ----------------------------------------------- . home
    # vybrané stránky na home-page
    # může následovat ident jednoho z článků (vznikne kliknutím na abstrakt)
    $abs= '';
    $id= array_shift($path);
    $xx= home($id);
    foreach($xx as $x) {
      $abs.= $x->ident==$id
            ? "<div id='clanek' class='x'><div class='clanek x' onclick='history.back();'>
               <h1>$x->nadpis</h1>$x->abstract</div></div>"
            : "<div class='x'>
                 <div class='abstrakt x' onclick=\"location.href='{$href0}home!$x->ident#clanek';\">
                   <span class='datum'>$x->datum $x->dnu</span> <b>$x->nadpis:</b> $x->abstract
                 </div></div>";
    }
    $body.= <<<__EOD
  <div id='home' class='x'>
    <div id='home_akce' class='x'>
      <div class='home_akce x'>
        informace vlevo nebo nahoře - podle šířky displeje
      </div>
    </div>
    <div id='home_telo' class='x'>$abs</div>
    <div id='home_info' class='x'>
      <div class='home_info x'>
        informace vpravo nebo dole
      </div>
    </div>
  </div>
__EOD;
    break;

  case 'foto':    # ----------------------------------------------- . foto
    # fotky
    $body.= <<<__EOD
    <div class="sites-embed-content sites-embed-type-picasa"><embed type="application/x-shockwave-flash" src="//picasaweb.google.com/s/c/bin/slideshow.swf" width="510" height="350" wmode="transparent" pluginspage="//www.macromedia.com/go/getflashplayer" flashvars="host=picasaweb.google.com&amp;captions=1&amp;noautoplay=1&amp;RGB=0x000000&amp;feed=http%3A%2F%2Fpicasaweb.google.com%2Fdata%2Ffeed%2Fapi%2Fuser%2F107320043704164538609%2Falbum%2FJarniObnova%3Fkind%3Dphoto%26alt%3Drss%26"></div>
__EOD;
    break;

  case 'akce2':   # ------------------------------------------------ . akce
    # seznam akcí podle proměnné $vyber
    # může následovat ident jednoho z článků (vznikne kliknutím na abstrakt)
    $body.= "<div id='list' class='x'>";
    $id= array_shift($path);
    $kdy= $ids=='bude' ? $ids : $par_bylo;
    $xx= akce2($vyber,$ids,$id);
    foreach($xx as $x) {
      $body.= $x->ident==$id
            ? "<div id='clanek' class='x'><div class='clanek x' onclick='history.back();'>
               <h1>$x->nadpis</h1>$x->abstract</div></div>"
            : "<div class='x'>
                 <div class='abstrakt x' onclick=\"location.href='$href0!$vyber!$x->ident#clanek';\">
                   <span class='datum'>$x->datum $x->dnu</span> <b>$x->nadpis:</b> $x->abstract
                 </div></div>";
    }
    $body.= "</div>";
    break;

  case 'proc':
    # ----------------------------------------------- . výběr komu
    $proc_kdo= function ($x) {
                                                        display("kdo($x)");
      global $def_pars,$href0;
      $html= "<div id='vyber' class='x'>";
      foreach($def_pars['komu'] as $id=>$nazev_i) {
        list($nazev,$i)= explode(':',$nazev_i);
        $checked= strpos($x,$id)!==false ? ' checked' : '';
        $on= " onclick='history_push(\"$href0\",\"komu,bylo\");'";
        $html.= "<input name='komu' value='$id' type='checkbox'$checked$on>$nazev</input>";
      }
      $html.= "</div>";
      return $html;
    };
    # ----------------------------------------------- . výběr kdy - minulost
    $proc_kdy= function ($x) {
      global $def_pars,$href0;
      $html= "<div id='vyber' class='x'>";
      foreach($def_pars['bylo'] as $id=>$nazev_i) {
        list($nazev,$i)= explode(':',$nazev_i);
        $checked= strpos($x,$id)!==false ? ' checked="checked"' : '';
        $on= " onclick='history_push(\"$href0$komu\",\"komu,bylo\");'";
        $html.= "<input type='radio' value='$id' name='bylo'$checked$on>$nazev</input>";
      }
      $html.= "</div>";
      return $html;
    };
    # specifické elementy
    switch ($ids) {

    case 'plan':  # ----------------------------------------------- . proc plan = komu
      $vyber= array_shift($path);
      $body.= $proc_kdo($vyber);
      break;

    case 'zpet':  # ----------------------------------------------- . proc zpet = komu,bylo
      $vyber= array_shift($path);
      $body.= $proc_kdo($vyber);
      $body.= $proc_kdy($vyber);
      break;

    case 'aplan':  # ---------------------------------------------- . proc aplan = alberice,bude
      $vyber= "alberice,".array_shift($path);
      break;

    case 'azpet':  # ---------------------------------------------- . proc azpet = alberice,bylo
      $vyber= "alberice,".array_shift($path);
      $body.= $proc_kdy($vyber);
      break;

    case 'objednavky': # ------------------------------------------ . proc objednávky
      $body.= "<div id='clanek' class='x' onclick='history.back();'>
                 <div class='clanek x'>objednávky pobytů v Albeřicích</div>
               </div>";
      break;
    default:
    }
    break;
  default:
  }
}
end:
# --------------------------------------------------------------------------------------==> template
$icon= $ezer_local ? "web_local.png" : "web.png";
$xtrace= $echo ? $trace : '';
$template=  <<<__EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
  <meta name="robots" content="noindex, nofollow" />
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=9" />
  <meta name="viewport" content="width=device-width, user-scalable=yes" />
  <title>web</title>
  <link rel="shortcut icon" href="img/$icon" />
  $eb_link
  <script type="text/javascript">
  $eb_script
  </script>
  <style>
/*  div.x {outline:1px dotted silver; } */
  a.dbg { outline:1px solid red; }

  /* stránka na mobilu a tabletu */
  body {margin:0px; padding:0; font-size:10pt; }
  @media all and (min-width: 80em) {
    /* stránka na velkém monitoru */
    body {width:80em; margin:auto; }
  }
  a.jump { display:inline-block; padding:0.1em 0.3em; height:1.2em; font-weight:bold;
           text-decoration:none; font-family:sans-serif; font-size:small;}
  a.active { background-color:orange !important; }
  span.datum { background-color:orange }
  /* top menu */
  #page_tm {width:100%; text-align:right; min-height:2em;}
  #page_tm input {display:inline-block; float:right; border:1px solid silver; }
  #page_tm a.jump { margin:0.1em 0.1em; background-color:gray; color:white; }
  @media all and (max-width:30em) {
    #page_tm a.jump { width:12%; white-space:wrap; overflow:hidden; }
  }

  /* main menu */
  #page_hm {width:100%; display:inline-flex;}
  #page_hm a.jump { margin:0.1em 0.3em; background-color:silver; color:black; }
  @media all and (max-width: 30em) {
    #page_hm a.jump { width:16.6667%; white-space:wrap; overflow:hidden; }
  }
  #vyber {width:100%; }

  /* sub menu */
  #page_sm {width:100%; display:inline-flex; margin-top: 0.3em;}
  #page_sm a.jump { margin:0.1em 0.3em; background-color:silver; color:black; }
  @media all and (max-width: 30em) {
    #page_sm a.jump, #page_sm span.label {
            width:16.6667%; white-space:wrap; overflow:hidden; }
  }
  @media all and (max-width: 40em) {
    #page_sm span.label { padding-top:0.4em; }
    #page_sm a.jump, #page_hm a.jump {
            border-bottom:0.5em solid silver; border-top:0.5em solid silver; }
    #page_tm a.jump {
            border-bottom:0.5em solid gray; border-top:0.5em solid gray; }
    #page_sm a.active, #page_hm a.active, #page_tm a.active {
            border-bottom:0.5em solid orange; border-top:0.5em solid orange; }
  }
  #page_sm span.label { display:inline-block; margin:0.3em 0.4em; height:1.2em;
           font-family:sans-serif; font-size:small; }

  /* patička */
  #page_foo {width:100%; display:table; clear:both; height:3em; }
  div.page_foo {background-color:#eee; min-height:3em; text-align:center; }

  /* seznamy abstraktů */
  #list {width:100%; }
  div.abstrakt { background-color:#eef; margin:0.5em; cursor:pointer;}

  /* článek */
  #clanek {width:100%; }
  div.clanek { background-color:#fee; /*min-height:20em;*/ margin:0.5em; }

  /* plán */
  #plan {width:100%; }

  /* home page na mobilu */
  #home {width: 100%; }

  #home_akce {width: 100%; display:inline-block; }
  div.home_akce { background-color:#efe; margin:0.5em; }

  #home_telo {width: 100%; display:inline-block; }

  #home_info {width: 100%; display:inline-block; }
  div.home_info { background-color:#efe; min-height:5em; margin:0.5em; }

  @media all and (min-width: 30em) {
    /* home page na na šířku nebo na tabletu */
    #home_akce {float: left; width: 20%; min-height:5em; }
    #home_telo {float: left; width: 60%; margin-top:0; }
    #home_info {float: left; width: 20%; margin-top:0; }
  }
  </style>
</head>
<body>
  <div id='page_tm' class='x'>$topmenu <input size=10></div>
  <div id='page_hm' class='x'>$mainmenu</div>
  $body
  <div id='page_foo' class='x'>
    <div class='page_foo x'>
      patička - tady bude možnost se přihlásit, základní informace pro dárce, zkratky na důležité stránky atd.
    </div>
  </div>
  $xtrace
</body>
</html>
__EOD;
if ( $echo )
  echo $template;
else
  return $template;
}
/** ====================================================================================> JAVASCRIPT */
function javascript_init() {
  global $eb_script, $eb_link, $edit;
  if ( $edit ) {
    $licensed= "ezer2.2/client/licensed";
    $eb_link= <<<__EOJ
    <script src="$licensed/ckeditor4/ckeditor.js" type="text/javascript" charset="utf-8"></script>
    <script src="$licensed/clientcide.js"         type="text/javascript" charset="utf-8"></script>
__EOJ;
  }
  else {
    $eb_link= <<<__EOJ
    <script src="MooTools-Core-1.6.0-compressed.js" type="text/javascript" charset="utf-8"></script>
__EOJ;
  }
  $eb_script= <<<__EOJ
  // -------------------------------------------------------------------------------------------- go
  function go(ref) {
    location.href= ref;
  }
  // ---------------------------------------------------------------------------------- history_push
  function history_push(href,checks) {
    var ref='', named, check;
    checks.split(',').each(function(check) {
      named= $$('input[name^="'+check+'"]');
      named.each(function(el) {
        if ( el.checked )
          ref+= (ref?',':'')+el.value;
      });
    });
    location.href= href+'!'+ref;
  }
  // ------------------------------------------------------------------------------------------- ask
  // ask(x,then): dotaz na server se jménem funkce po dokončení
  function ask(x,then,arg) {
    var ajax= new Request({url:'template.php', data:x, method:'post',
      onSuccess: function(ay) { var y;
        try { y= JSON.decode(ay); } catch (e) { y= null; error('AJAX error (1)'); }
        if ( then ) {
          then.apply(undefined,[y,arg]);
        }
      },
      onFailure: function() { error('AJAX error (2)'); }
    });
    ajax.send();
  }
  // ----------------------------------------------------------------------------------------- error
  function error(msg) {
    alert(msg);
  }
  // -------------------------------------------------------------------------------==> . clanek
  function clanek(idd,idc) {
    ask({cmd:'clanek',id:idc},clanek_,{elem:idd});
  }
  function clanek_(y,ctx) {
    var elem= $(ctx.elem);
    elem.set('html',"<div class='clanek' contenteditable='true'><h1>"+y.row.nadpis+"</h1>"+y.row.obsah+"</div>");
  }
  window.addEvent('domready', function() {
  });
__EOJ;
}
/** ========================================================================================> SERVER */
function server($x) {
  global $y;
  switch ( $x->cmd ) {
  case 'test':
    $y->html= "prošlo";
    break;
  case 'clanek':
    $y->row= clanek($x->id);
    break;
  }
}
/** =======================================================================================> SETKANI */
# --------------------------------------------------------------------------------------- x_shorting
# EPRIN
# zkrátí text na $n znaků s ohledem na html-entity jako je &nbsp;
function x_shorting ($text,$n=200) {
  // náhrada <h.> za <i>
  $text= preg_replace("/\<(\/|)h3>/si",' <$1i> ', $text);
  // hrubé zkrácení textu
  $stext= mb_substr(strip_tags($text,'<i>'),0,$n);
  // odstranění poslední (případně přeříznuté) html-entity
  $in= mb_strlen($stext);
  $ia= mb_strrpos($stext,'&');
  $stext= mb_substr($stext,0,$in-$ia<10 ? $ia : $in);
  $im= mb_strrpos($stext,' ');
  $stext= mb_substr($stext,0,$im);
  $stext= closetags($stext);
  $stext= preg_replace("/\s+/iu",' ', $stext);
  return $stext;
}
function closetags($html) {
  preg_match_all('#<(?!meta|img|br|hr|input\b)\b([a-z]+)(?: .*)?(?<![/|/ ])>#iU', $html, $result);
  $openedtags = $result[1];
  preg_match_all('#</([a-z]+)>#iU', $html, $result);
  $closedtags = $result[1];
  $len_opened = count($openedtags);
  if (count($closedtags) == $len_opened) {
    return $html;
  }
  $openedtags = array_reverse($openedtags);
  for ($i=0; $i < $len_opened; $i++) {
    if (!in_array($openedtags[$i], $closedtags)) {
      $html .= '</'.$openedtags[$i].'>';
    } else {
      unset($closedtags[array_search($openedtags[$i], $closedtags)]);
    }
  }
  return $html;
}
# --------------------------------------------------------------------------------------==> . clanek
function clanek($id) { trace();
  $x= (object)array();
  if ( is_numeric($id) ) {
    list($x->nadpis,$x->obsah)= select("title,text","setkani.tx_gncase_part","cid='$id'");
  }
  else {
    list($x->nadpis,$x->obsah)= select("nadpis,obsah","clanek","zkratka='$id'");
  }
                                                        display("článek=$x->nadpis");
  return $x;
}
# ------------------------------------------------------------------------------------------- clanky
# id=pid nebo název menu
function clanky($ids,$id=0) { trace();
  $x= array();
  $ukazat= 200;
  $cr= mysql_qry("
    SELECT uid,title,text FROM setkani.tx_gncase_part
    WHERE !deleted AND !hidden AND pid IN ($ids)");
  while ( $cr && ($c= mysql_fetch_object($cr)) ) {
    $tail= $c->text;
    if ( $c->uid!=$id ) {
      $tail= x_shorting($tail).' ...';
    }
    $x[]= (object)array('ident'=>$c->uid,'nadpis'=>$c->title,'abstract'=>$tail);
  }
                                                        debug($x);
  return $x;
}
# --------------------------------------------------------------------------------------------- akce
# id=pid nebo název menu
function akce2($vyber,$kdy,$id=0) { trace();
  global $def_pars;
  $x= array();
  $ukazat= 200;
  // překlad $komu na regexpr
  $rkomu= array();
  foreach(explode(',',$vyber) as $kdo) {
    $ki= $def_pars['komu'][$kdo];
    if ( $ki ) {
      list($k,$i)= explode(':',$ki);
      $rkomu[]= $i;
    }
  }
  $c_komu= "program REGEXP '".implode('|',$rkomu)."'";
                                                       display("komu=$c_komu");
  // překlad $kdy na podmínku
  $c_kdy= 0;
  if ( $kdy=='bude' || $kdy=='bude_alberice' ) {
    $c_kdy= "FROM_UNIXTIME(fromday)>=NOW()";
    $ORDER= "ASC";
  }
  else {
    foreach(explode(',',$vyber) as $v) {
      $ki= $def_pars['bylo'][$v];
      if ( $ki ) {
        list($k,$c_kdy)= explode(':',$ki);
        $c_kdy.= " AND FROM_UNIXTIME(fromday)<NOW()";
        $ORDER= "DESC";
        break;
      }
    }
  }
                                                       display("kdy=$c_kdy");
  $cr= mysql_qry("
    SELECT cid, program, tema, title, text,
      DATEDIFF(FROM_UNIXTIME(untilday),FROM_UNIXTIME(fromday))+1 AS _dnu,
      FROM_UNIXTIME(fromday) AS _od, FROM_UNIXTIME(untilday) AS _do
    FROM setkani.tx_gncase AS c
    JOIN setkani.tx_gncase_part AS p ON p.cid=c.uid
    WHERE !c.deleted AND !c.hidden AND !p.deleted AND !p.hidden AND tags='A'
      AND c.pid IN (55,46,98,99,120,197,237,247,260,265,266,267,268,269,270,
                    271,272,273,274,275,276,277,278,279,280,281,282)
      AND $c_komu AND $c_kdy
    ORDER BY fromday $ORDER
  ");
  while ( $cr && ($c= mysql_fetch_object($cr)) ) {
    $tail= $c->text;
    $datum= sql_date1($c->_od);
    $dnu= $c->_dnu;
    $dnu= $dnu==1 ? '' : ($dnu<5 ? " - $dnu dny" : " - $dnu dnů");
    if ( $c->cid!=$id ) {
      $tail= x_shorting($tail).' ...';
    }
    $x[]= (object)array('ident'=>$c->cid,'datum'=>$datum,'dnu'=>$dnu,
      'nadpis'=>$c->title,'abstract'=>$tail);
  }
//                                                         debug($x);
  return $x;
}
# --------------------------------------------------------------------------------------------- akce
# id=pid nebo název menu
function akce($komu,$kdy,$id=0) { trace();
  global $def_pars;
  $x= array();
  $ukazat= 200;
  // překlad $komu na regexpr
  $rkomu= array();
  foreach(explode(',',$komu) as $kdo) {
    $ki= $def_pars['komu'][$kdo];
    if ( $ki ) {
      list($k,$i)= explode(':',$ki);
      $rkomu[]= $i;
    }
  }
  $c_komu= "program REGEXP '".implode('|',$rkomu)."'";
                                                       display("komu=$c_komu");
  // překlad $kdy na podmínku
  $c_kdy= 0;
  if ( $kdy=='bude' || $kdy=='bude_alberice' ) {
    $c_kdy= "FROM_UNIXTIME(fromday)>=NOW()";
    $ORDER= "ASC";
  }
  else {
    $ki= $def_pars['bylo'][$kdy];
    if ( $ki ) {
      list($k,$c_kdy)= explode(':',$ki);
    }
    $c_kdy.= " AND FROM_UNIXTIME(fromday)<NOW()";
    $ORDER= "DESC";
  }
                                                       display("kdy=$c_kdy");
  $cr= mysql_qry("
    SELECT cid, program, tema, title, text,
      DATEDIFF(FROM_UNIXTIME(untilday),FROM_UNIXTIME(fromday))+1 AS _dnu,
      FROM_UNIXTIME(fromday) AS _od, FROM_UNIXTIME(untilday) AS _do
    FROM setkani.tx_gncase AS c
    JOIN setkani.tx_gncase_part AS p ON p.cid=c.uid
    WHERE !c.deleted AND !c.hidden AND !p.deleted AND !p.hidden AND tags='A'
      AND c.pid IN (55,46,98,99,120,197,237,247,260,265,266,267,268,269,270,
                    271,272,273,274,275,276,277,278,279,280,281,282)
      AND $c_komu AND $c_kdy
    ORDER BY fromday $ORDER
  ");
  while ( $cr && ($c= mysql_fetch_object($cr)) ) {
    $tail= $c->text;
    $datum= sql_date1($c->_od);
    $dnu= $c->_dnu;
    $dnu= $dnu==1 ? '' : ($dnu<5 ? " - $dnu dny" : " - $dnu dnů");
    if ( $c->cid!=$id ) {
      $tail= x_shorting($tail).' ...';
    }
    $x[]= (object)array('ident'=>$c->cid,'datum'=>$datum,'dnu'=>$dnu,
      'nadpis'=>$c->title,'abstract'=>$tail);
  }
//                                                         debug($x);
  return $x;
}
# --------------------------------------------------------------------------------------------- home
# id=pid nebo název menu
function home($id=0) { trace();
  global $def_pars;
  $x= array();
  $ukazat= 200;
  $cr= mysql_qry("
    SELECT cid, program, tema, title, text,
      DATEDIFF(FROM_UNIXTIME(untilday),FROM_UNIXTIME(fromday))+1 AS _dnu,
      FROM_UNIXTIME(fromday) AS _od, FROM_UNIXTIME(untilday) AS _do
    FROM setkani.tx_gncase AS c
    JOIN setkani.tx_gncase_part AS p ON p.cid=c.uid
    WHERE !c.deleted AND !c.hidden AND !p.deleted AND !p.hidden AND tags='A'
      AND p.homepage=2
    ORDER BY fromday $ORDER
  ");
  while ( $cr && ($c= mysql_fetch_object($cr)) ) {
    $tail= $c->text;
    $datum= sql_date1($c->_od);
    $dnu= $c->_dnu;
    $dnu= $dnu==1 ? '' : ($dnu<5 ? " - $dnu dny" : " - $dnu dnů");
    if ( $c->cid!=$id ) {
      $tail= x_shorting($tail).' ...';
    }
    $x[]= (object)array('ident'=>$c->cid,'datum'=>$datum,'dnu'=>$dnu,
      'nadpis'=>$c->title,'abstract'=>$tail);
  }
//                                                         debug($x);
  return $x;
}
?>
