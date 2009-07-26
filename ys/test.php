<?php # (c) 2007-2009 Martin Smidek <martin@smidek.eu>
# -------------------------------------------------------------------------------------------------- t_load
# test form_make
function t_load($key) {
  global $y;
  make_get(&$set,&$select,&$fields);
                                                debug(array($set,$select,$fields),"set-select-fields");
  $fs= implode(',',$select);
  $qry= "SELECT $fs FROM t WHERE id_t=$key";
  $res= mysql_qry($qry);
  if ( $res && $t= mysql_fetch_object($res) ) {
    $elem= (object)array();
    foreach ($fields as $f) {
      $elem->$f= $t->$f;
    }
    $y->load= $elem;
  }
  $y->key= $key;
}
# -------------------------------------------------------------------------------------------------- t_insert
# test form_make
function t_insert() {
  global $y;
  make_get(&$set,&$select,&$fields);
                                                debug(array($set,$select,$fields),"set-select-fields");
  $fs= implode(',',$set['t']);
  $qry= "INSERT INTO t SET $fs";
  $res= mysql_qry($qry);
  $y->key= mysql_insert_id();
}
# -------------------------------------------------------------------------------------------------- doc_todo
# vygeneruje přehled aktivit podle menu
function doc_todo($item) {
  $html= "<div id='Content'><div class='CSection CMenu'>";
  $nove= 7;
  switch ( $item ) {
  case 'nove':
    $html.= "<h3 class='CTitle'>Vlastnosti systému přidané za posledních $nove dní</h3>";
    $html.= "<i>Věnujte prosím pozornost zejména zvýrazněným řádkům. "
      . "Zvýrazněné úpravy se týkají téměř všech uživatelů.</i>";
    $html.= doc_todo_show('++done','',0,$nove);
    break;
  case 'stare':
    $html.= "<h3 class='CTitle'>Vlastnosti systému přidané před $nove dny</h3>";
    $html.= doc_todo_show('++done','',$nove+1);
    break;
  case 'todo':
    $html.= "<h3 class='CTitle'>Požadavky na opravy, úpravy a doplnění systému</h3>";
    $x= "<br><br><br><br>Odesláno ze stránky Nápověda/Novinky";
    $html.= "<i>Požadavky mi posílejte prosím tímto odkazem "
      . "<a href='mailto:smidek@proglas.cz?subject=Pozadavek na upravu&body=$x'>smidek@proglas.cz</a></i>.";
    $html.= doc_todo_show('++todo','++done');
    break;
  }
  $html.= "</div></div>";
  return $html;
}
# -------------------------------------------------------------------------------------------------- doc_todo_show
# vygeneruje přehled aktivit podle menu
function doc_todo_show($ods,$dos,$odt=0,$dot=99999) {
  $file= file_get_contents("../../wiki/todo.wiki");
  $f1= strpos($file,"\n$ods") + strlen($ods) + 3;
  $f2= $dos ? strpos($file,"\n$dos") : 999999;
  // rozklad na řádky
  $text= substr($file,$f1,$f2-$f1);
  $line= explode("\n",$text);
//                                                 debug($line,'todo.wiki');
  $tab= "<dl class='todo'>";
  for ($i= 1; $i<=count($line); $i++) {
    $j= 1;
    $err= '';
    $todo= explode('|',$line[$i-1]);
    if ( count($todo)==1 ) continue;
    if ( count($todo)!=6 ) $err= "chybná syntaxe: chybný počet sloupců ";
    else {
      $zadano= trim($todo[$j++]);
      if ( $zadano && !verify_datum($zadano,&$d,&$m,&$y,&$timestamp) )
        $err.= "chybné datum zadání: $zadano";
      $user= trim($todo[$j++]);
      $typ= trim($todo[$j++]);
      if ( $ods=='++todo' ) {
        $hotovo= $todo[$j++];
        if ( trim($hotovo) ) $err.= "plán má uvedeno datum ukončení";
      }
      else {
        $hotovo= $todo[$j++];
        if ( !($ok= verify_datum($hotovo,&$d,&$m,&$y,&$timestamp)) )
          $err.= "chybné datum ukončení: $hotovo";
        if ( $ok ) {
          // hotové zobrazíme jen v požadovaném intervalu
          $now= time();
          $days= ($now-$timestamp)/(60*60*24);
          if ( $days > $dot || $days < $odt ) continue;
        }
      }
      $datum= date('d.m.Y',$timestamp);
      $popis= trim($todo[$j++]);
      $popis= ereg_replace('\*([^\*]+)\*','<b>\\1</b>',$popis);
    }
    // vlastní zobrazení
    $class= '';
    if ( substr($popis,0,1)=='+' ) { $class=' class=todo_plus'; $popis= substr($popis,1); }
    switch ( $err ? 'error' : ( $popis ? $ods : 'nic') ) {
    case '++done':
      if ( !$zadano )
        $tab.= "<dt>$hotovo bylo přidáno</dt><dd$class>$popis</dd>";
      else
        $tab.= "<dt>$hotovo byla dokončena $typ, "
          ."kterou dne $zadano požadoval/a $user</dt><dd$class>$popis</dd>";
      break;
    case '++todo':
      $tab.= "<dt>ode dne $zadano je $user "
        . "požadována $typ</dt><dd$class>$popis</dd>";
      break;
    case 'error':
      $tab.= "<dt style='background-color:#ff6'>$err v souboru todo/todo.wiki"
        . " v sekci $ods, řádek $i</dt><dd>{$line[$i]}</dd>";
      break;
    }
  }
  $tab.= "</dl>";
  $html= $tab ? $tab : "nic";
  return $html;
}
?>
