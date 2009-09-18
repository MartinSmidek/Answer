<?php # (c) 2007-2009 Martin Smidek <martin@smidek.eu>
// ================================================================================================= AKTIVITY
# -------------------------------------------------------------------------------------------------- sys_activity
# vygeneruje přehled aktivit podle menu
function sys_activity($k) {
//                                                                 debug($k,'sys_activity');
  global $json, $user_options, $APLIKACE, $USER;
  $user_options= $_SESSION['user_options'];
  $stav_moduly= $user_options->sys_moduly_all ? '' : "bez {$USER->abbr}";
//   $stav_uzivatele= $user_options->sys_uzivatele_all ? '' : "bez {$USER->abbr}";
  $stav_chyby= $user_options->sys_chyby_all ? '' : "bez {$USER->abbr}";
  $html= "<div class='CSection CMenu'>";
  switch ( "{$k->s} {$k->c}" ) {
  case 'moduly all':
  case 'uzivatele all':
  case 'chyby all':
    $ioptions= "sys_{$k->s}_all";
    if (!isset($user_options->$ioptions) ) $user_options->$ioptions= 0;
    $stav= ($user_options->$ioptions ? 'bez ' : 'včetně ' ).$USER->abbr;
    $user_options->$ioptions= $user_options->$ioptions ? 0 : 1;
    $_SESSION['user_options']= $user_options;
    $html.= "stav bude zobrazen $stav";
    break;
  case 'moduly dnes':
    $day= date('j.n.Y');
    $day_mysql= date('Y-m-d');
    $html.= "<h3 class='CTitle'>Aktuální stav užívání $APLIKACE $day $stav_modules</h3>";
    $html.= sys_day_modules($day_mysql,$k->short);
    break;
  case 'moduly vcera':
    $day= date('j.n.Y',mktime(0,0,0,date("m"),date("d")-1,date("Y")));
    $day_mysql= date('Y-m-d',mktime(0,0,0,date("m"),date("d")-1,date("Y")));
    $html.= "<h3 class='CTitle'>Stav užívání $APLIKACE $day $stav_modules</h3>";
    $html.= sys_day_modules($day_mysql,$k->short);
    break;
  case 'uzivatele dnes':
    $day= date('j.n.Y');
    $day_mysql= date('Y-m-d');
    $html.= "<h3 class='CTitle'>Aktuální stav užívání $APLIKACE $day $stav_uzivatele</h3>";
    $html.= sys_day_users($day_mysql,$k->short);
    break;
  case 'uzivatele vcera':
    $day= date('j.n.Y',mktime(0,0,0,date("m"),date("d")-1,date("Y")));
    $day_mysql= date('Y-m-d',mktime(0,0,0,date("m"),date("d")-1,date("Y")));
    $html.= "<h3 class='CTitle'>Stav užívání $APLIKACE $day</h3>";
    $html.= sys_day_users($day_mysql,$k->short);
    break;
  case 'chyby dnes':
    $day= date('j.n.Y');
    $day_mysql= date('Y-m-d');
    $html.= "<h3 class='CTitle'>Chybová hlášení $APLIKACE $day $stav_chyby</h3>";
    $html.= sys_day_errors($day_mysql);
    break;
  case 'chyby vcera':
    $day= date('j.n.Y',mktime(0,0,0,date("m"),date("d")-1,date("Y")));
    $day_mysql= date('Y-m-d',mktime(0,0,0,date("m"),date("d")-1,date("Y")));
    $html.= "<h3 class='CTitle'>Chybová hlášení $APLIKACE $day $stav_chyby</h3>";
    $html.= sys_day_errors($day_mysql);
    break;
  case 'chyby tyden':
    $day= date('j.n.Y',mktime(0,0,0,date("m"),date("d")-8,date("Y")));
    $day_mysql= date('Y-m-d',mktime(0,0,0,date("m"),date("d")-8,date("Y")));
    $html.= "<h3 class='CTitle'>Chybová hlášení $APLIKACE od $day $stav_chyby</h3>";
    $html.= sys_day_errors($day_mysql,'>');
    break;
  case 'chyby mesic':
    $day= date('j.n.Y',mktime(0,0,0,date("m"),date("d")-32,date("Y")));
    $day_mysql= date('Y-m-d',mktime(0,0,0,date("m"),date("d")-32,date("Y")));
    $html.= "<h3 class='CTitle'>Chybová hlášení $APLIKACE od $day $stav_chyby</h3>";
    $html.= sys_day_errors($day_mysql,'>');
    break;
  case 'chyby vsechny':
    $html.= "<h3 class='CTitle'>Všechna chybová hlášení $APLIKACE $stav_chyby</h3>";
    $html.= sys_day_errors($day_mysql,'all');
    break;
  case 'chyby BUG1':
    $html.= "<h3 class='CTitle'>Nevyřešené chyby $APLIKACE klasifikované jako BUG</h3>";
    $html.= sys_bugs(1);
    break;
  case 'chyby BUG2':
    $html.= "<h3 class='CTitle'>Vyřešené chyby $APLIKACE klasifikované jako BUG</h3>";
    $html.= sys_bugs(2);
    break;
  }
  $html.= "</div>";
  return $html;
}
# -------------------------------------------------------------------------------------------------- sys_day_modules
# vygeneruje podrobný přehled aktivit pro daný den
function sys_day_modules($day,$short=false) {
  global $user_options, $USER;
  $touch= array();
  $hours= array();
//   $and= $user_options->sys_moduly_all ? '' : " AND user!='{$USER->abbr}'";
  $qry= "SELECT day,hour(time) as hour,user,module,menu,count(*) as c FROM _touch
        WHERE day='$day' AND user!='' AND module='block' $and
        GROUP BY module,menu,user,hour(time) ORDER BY module,menu";
  $res= mysql_qry($qry);
  while ( $res &&$row= mysql_fetch_assoc($res) ) {
    $user= $row['user'];
    $hour= $row['hour'];
    $hours[$hour]= true;
    $module= $row['module'];
    $menu= $row['menu'];
    if ( $short ) {
      $ids= explode('.',$menu);
      $menu= $ids[0];
    }
    $c= $row['c'];
    if ( !$touch[$menu] ) $touch[$menu]= array(array());
    if ( strpos($touch[$menu][$hour][0],$user)==false )
      $touch[$menu][$hour][0].= " $user";
  }
  $html= sys_table($touch,$hours,'module','#dce7f4');
  return $html;
}
# -------------------------------------------------------------------------------------------------- sys_day_users
# vygeneruje přehled aktivit pro daný den
function sys_day_users($day,$short=false) {
  global $user_options, $USER;
  $touch= array();
  $hours= array();
//   $and= $user_options->sys_uzivatele_all ? '' : " AND user!='{$USER->abbr}'";
  $qry= "SELECT day,hour(time) as hour,user,module,menu,count(*) as c,sum(hits) as h FROM _touch "
    . " WHERE day='$day' AND user!='' $and GROUP BY user,module,menu,hour(time) ORDER BY user,hour";
  $res= mysql_qry($qry);
  while ( $res &&$row= mysql_fetch_assoc($res) ) {
    $user= $row['user'];
    $hour= $row['hour'];
    $hours[$hour]= true;
    $module= $row['module'];
    $menu= $row['menu'];
    if ( $short ) {
      $ids= explode('.',$menu);
      $menu= $ids[0];
      $menu= strtr($menu,array("login"=>'&lt',"timeout"=>'&gt'));
    }
    $c= $row['c'];
    $h= $row['h'];
//     if ( $module  ) {
      if ( !$touch[$user] ) $touch[$user]= array();
      if ( !$touch[$user][$hour] ) $touch[$user][$hour]= array();
      if ( !isset($touch[$user][$hour]['touch'][$menu]) )
        $touch[$user][$hour]['touch'][$menu]= 1;
      $touch[$user][$hour]['touch'][$menu]+= $h;
//       if ( !isset($touch[$user][$hour]['module'][$module]) )
//         $touch[$user][$hour]['module'][$module]+= $h;
//       if ( !isset($touch[$user][$hour]['menu']["$module.$menu"]) )
//         $touch[$user][$hour]['menu']["$module.$menu"]+= $h;
//     }
  }
  $html= sys_table($touch,$hours,'user','#e7e7e7',true); // použít tabulku barev, je-li v config
  return $html;
}
# -------------------------------------------------------------------------------------------------- sys_bugs
# vygeneruje přehled BUGs
#   _touch.level == 1 BUG čekající na vyřešení
#   _touch.level == 2 BUG opravená
function sys_bugs($level) {
  global $user_options;
  $n= 0;
  $html.= '<dl>';
  $qry= "SELECT max(level) as bug, min(id_touch) as id, msg,"
    . " group_concat($day1 time,' ',user,' ',module,' ',menu) as popis"
    . " FROM _touch WHERE msg!='' GROUP BY msg HAVING bug=$level ORDER BY day DESC";
  $res= mysql_qry($qry);
  while ( $res &&$row= mysql_fetch_assoc($res) ) {
    $n++;
    $popis= $row['popis'];
    $id= $row['id'];
    $bug= $row['bug'];
    $msg= $row['msg'];
    // generování
    $color= $bug==1 ? '#fb6' : ($bug==2 ? '#6f6' : '#eee');
    $mark= $bug>0 ? "BUG#$id" : '';
    $html.= <<<__JS
    <dt style='background-color:$color;cursor:default;' oncontextmenu="
     sys_x= 'sys_day_error';
     mn=[
       '@ae.ask([sys_x,$id,1],ae)|označit jako BUG',
       '@ae.ask([sys_x,$id,2],ae)|BUG je opravený',
       '@ae.ask([sys_x,$id,3],ae)|smazat tento BUG'
     ];
     ContextMenuHi3(this,'$n',mn,null,arguments[0]);"
    > $mark $popis $module $menu</dt><dd>$msg</dd>
__JS
    ;
  }
  $result= $n ? "celkem $n" : "nic";
  return $result.$html;
}
# -------------------------------------------------------------------------------------------------- sys_day_errors
# vygeneruje přehled chyb pro daný den
#   $sign= 'all' => všechno
function sys_day_errors($day,$sign='=') {
//                                                         display("sys_day_errors($day,$sign)");
  global $user_options, $USER;
  $max_len= 512;
  $n= 0;
//   $and= $user_options->sys_chyby_all ? '' : " AND user!='{$USER->abbr}'";
  $html.= '<dl>';
  $day1= $sign=='=' ? '' : "day,' ',";
  $cond= $sign=='all' ? '1' : "day$sign'$day'";
  $qry= "SELECT max(level) as bug, min(id_touch) as id, msg,"
    . " group_concat($day1 time,' ',user,' ',module,' ',menu) as popis"
    . " FROM _touch WHERE $cond AND msg!='' $and GROUP BY msg ORDER BY day DESC";
  $res= mysql_qry($qry);
  while ( $res &&$row= mysql_fetch_assoc($res) ) {
    $n++;
    $popis= $row['popis'];
    $id= $row['id'];
    $bug= $row['bug'];
    $msg= $row['msg'];
    $msg= strtr($msg,array('<'=>'&lt;','>'=>'&gt;'));
    if ( strlen($msg)>$max_len ) $msg= substr($msg,0,$max_len).' ...';
    // generování
    $color= $bug==1 ? '#fb6' : ($bug==2 ? '#6f6' : '#eee');
    $mark= $bug>0 ? "BUG#$id" : '';
    $html.= <<<__JS
    <dt style='background-color:$color;cursor:default;' oncontextmenu="
     sys_x= 'sys_day_error';
     mn=[
       '@ae.ask([sys_x,$id,1],ae)|označit jako BUG',
       '@ae.ask([sys_x,$id,2],ae)|BUG je opravený',
       '@ae.ask([sys_x,$id,3],ae)|smazat toto hlášení',
       '@ae.ask([sys_x,$id,4],ae)|ukázat plný text v trace'
     ];
     ContextMenuHi3(this,'$n',mn,null,arguments[0]);"
    > $mark $popis $module $menu</dt><dd>$msg</dd>
__JS
    ;                                           //<dd>$msg</dd>
  }
  $html.= '</dl>';
  $result= $n ? "$n hlášení chyb" : "bez hlášení chyby";
  return $result.$html;
}
# -------------------------------------------------------------------------------------------------- sys_day_error
# callback funkce ze sys_day_errors
function sys_day_error($id,$akce) {
//                                                 display("sys_day_error($id,$akce)");
  switch ( $akce ) {
  case 1:       // označit jako BUG
    $qry= "UPDATE _touch SET level=1 WHERE id_touch=$id";
    $res= mysql_qry($qry);
    break;
  case 2:       // označit jako opravený BUG
    $qry= "UPDATE _touch SET level=2 WHERE id_touch=$id";
    $res= mysql_qry($qry);
    break;
  case 3:       // smazat
    $qry= "SELECT group_concat(id_touch) AS ids FROM _touch WHERE msg="
      . "(SELECT msg FROM _touch WHERE id_touch=$id)";
    $res= mysql_qry($qry);
    if ( $res &&$row= mysql_fetch_assoc($res) ) {
      $ids= $row['ids'];
      $qry= "DELETE FROM _touch WHERE FIND_IN_SET(id_touch,'$ids')";
      $res= mysql_qry($qry);
//                                                 display("sys_day_error($id,$akce)=$ids");
    }
    break;
  case 4:       // plný text
    $qry= "SELECT * FROM _touch WHERE id_touch=$id";
    $res= mysql_qry($qry);
    if ( $res && $row= mysql_fetch_assoc($res) ) {
      debug($row);
    }
    break;
  }
  return '';
}
# -------------------------------------------------------------------------------------------------- sys_table
# zobrazí přehled aktivit pro daný den, pokud není uvedeno $color, použije se definice barev
# z config.php $EZER->activity->colors= "80:#f0d7e4,40:#e0d7e4,20:#dce7f4,0:#e7e7e7"; (sestupně)
# (pokud je h>hi použije se jako podklad colori)
# $type= user|module
function sys_table($touch,$hours,$type,$color,$config_colors=false) { #trace();
//                                                 display("sys_table($touch,$hours,$color,$config_colors)");
  $tab= '';
  // tabulka barev pro hit>0
  global $EZER;
  $colors= array();
  if ( $config_colors ) {
    foreach ( explode(',',$EZER->activity->colors) as $mezclr) {
      list($mez,$clr)= explode(':',$mezclr);
      $colors[$mez]= $clr;
    }
  }
  $colors[0]= '#e7e7e7';  // zarážka nakonec
//                                                 debug($colors);
  // vykreslení tabulky
  if ( $hour_min <= $hour_max ) {
    $wt= '100%';
    $wt= '';
    $wh= 100/($hour_max-$hour_min+1).'%';
    $wh= 50;
    // čas
    $tab.= "<table width='$wt'><tr><th width='50'></th>";
    for ($h= 0; $h<=24; $h++) if ( $hours[$h] ) $tab.= "<th width='$wh'>$h</th>";
    $tab.= "</tr>";
    // uživatelé
//                                                 debug($touch,'$touch');
    foreach ( $touch as $user => $activity ) {
      $tab.= "<tr><td>$user</td>";
      for ($h= 0; $h<=24; $h++) if ( $hours[$h] )  {
        switch ( $type ) {
        case 'module':
          $act= $activity[$h][0] ? $activity[$h][0] : "";
          $bg= $act ? "bgcolor='$color'" : '';
          $tab.= "<td $bg>$act</td>";
          break;
        case 'user':
          if ( $activity[$h] ) {
            $act= implode(' ',array_keys($activity[$h]['touch']));
            $act= str_replace('LOGIN',"<font color='#ff3333'>login</font>",$act);
            $hit= array_sum($activity[$h]['touch']);
            $tit= '';
            foreach ($activity[$h]['touch'] as $menu => $menu_hit ) {
              $tit.= " $menu_hit*$menu ";
            }
            $bg= '';
            if ( $act ) {
              // volba barvy
              foreach ($colors as $mez => $clr) {
                if ( $hit>=$mez ) {
                  $bg= "bgcolor='$clr'";
                  break;
                }
              }
            }
            $tab.= "<td $bg title='$tit, celkem $hit'>$act</td>";
          }
          else
            $tab.= "<td></td>";
          break;
        }
      }
      $tab.= "</tr>";
    }
    $tab.= "</table>";
  }
  return $tab;
}
?>
