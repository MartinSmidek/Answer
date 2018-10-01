<?php # (c) 2018 Martin Smidek <martin@smidek.eu> ... odstraněné část db2_fce.php

# ---------------------------------------------------------------------------==> . db2 sys_transform
# transformace na schema 2015
# par.cmd = seznam transformací
# par.akce = id_akce | 0
# par.pobyt = id_pobyt | 0
#         menu {title:'Transformace 2015',type:'group', _sys:'*'
#           item {title:'DB2: clear'              ,par:°{cmd:'imp_clear',confirm:'1'} }
#           item {title:'DB2<+YS: import'         ,par:°{cmd:'imp_YS',confirm:'1'} }
#           item {title:'DB2<+FA: import'         ,par:°{cmd:'imp_FA',confirm:'1'} }
#           item {title:'DB2 clear _track'        ,par:°{cmd:'imp_clear_track',confirm:'1'} }
#           item {title:'DB2<+YS: import _track'  ,par:°{cmd:'imp_YS_track',confirm:'1'} }
#           item {title:'DB2<+FA: import _track'  ,par:°{cmd:'imp_FA_track',confirm:'1'} }
#           item {title:'DB2: úprava mailistů'    ,par:°{cmd:'cor_mailist',confirm:'1'} }
#           item {title:'DB2: dokumenty YS'       ,par:°{cmd:'doc_YS',confirm:'1'} }
#           item {title:'DB2: dokumenty FA'       ,par:°{cmd:'doc_FA',confirm:'1'} }
#           item {title:'DB2: úpravy uživatelů'   ,par:°{cmd:'imp_user',confirm:'1'} }
#           item {title:'DB2: úpravy číselníků'   ,par:°{cmd:'imp_cis',confirm:'1'} }
#           item {title:'DB2: ... all in one ...' ,par:°{
#             cmd:'imp_clear,imp_clear_track,imp_YS,imp_FA,cor_mailist,imp_YS_track,imp_FA_track,doc_YS,doc_FA,imp_user,imp_cis',confirm:'1'} }
#           proc onclick (i) { clear;
#             info.fill(conc(i.owner.title,' - ',i.title),' ');
#             { not(i.par.confirm) | confirm("spustit úpravy databáze EZER_DB2?") };
#             info.fill('',' ... probíhá transformace dat ...');
#             info.fill('',ask('db2_sys_transform',i.par));
#           }
#         }
function db2_sys_transform($par) { trace();
  global $ezer_root, $ezer_path_root;
  $html= '';
  $updated= 0;
  $fs= array(
    // aplikace
    'akce' =>    array('access','id_duakce','id_hlavni'),
    'g_akce' =>  array(         'id_gakce'),
    'cenik' =>   array(         'id_cenik','id_akce'),
    'dar' =>     array('access','id_dar','id_osoba','id_rodina'),
    'dopis' =>   array('access','id_dopis','id_duakce','id_mailist'),
    'join_akce'=>array(         'id_akce'),
    'mailist' => array('access','id_mailist'),
    'mail' =>    array(         'id_mail','id_dopis','id_pobyt','id_clen'),
    'osoba' =>   array('access','id_osoba'),
    'platba' =>  array(         'id_platba','id_osoba','id_rodina','id_duakce','id_pokl'),
    'pobyt' =>   array(         'id_pobyt','id_akce','i0_rodina'),
    'rodina' =>  array('access','id_rodina'),
    'spolu' =>   array(         'id_spolu','id_pobyt','id_osoba','pecovane'),
    'tvori' =>   array(         'id_tvori','id_rodina','id_osoba'),
    // systém
    '_user' =>  array('id_user'),
    //'_track' => array('id_track','klic'), -- zvláštní běh imp_track
  );
  $ds= array(
    // dokumenty s _id^ na konci
    'pobyt' =>  array('modi'),
    // dokumenty odkázané původním jménem s ^ na konci
    'akce' =>   array('copy')
  );
  foreach (explode(',',$par->cmd) as $cmd ) {
    $update= false;
    $limit= "LIMIT 3";
    $db= 'ezer_fa';
    $root= 'fa';
    $ok= 1;
    $html.= "<br><b>$cmd</b>";
    switch ($cmd ) {
    // ---------------------------------------------- import: imp_clear
    // vyčistí databázi ezer_db2, založí uživatele GAN
    case 'imp_clear':
      // vyprázdnění tabulek
      foreach ($fs as $tab => $keys) {
        if ( $ok ) $ok= mysql_qry("TRUNCATE TABLE ezer_db2.$tab");
        if ( $ok ) $ok= mysql_qry("ALTER TABLE ezer_db2.rodina
          CHANGE ulice ulice tinytext COLLATE 'utf8_czech_ci' NOT NULL AFTER fotka,
          CHANGE obec obec tinytext COLLATE 'utf8_czech_ci' NOT NULL AFTER psc");
        if ( $ok ) $html.= "<br>$tab: vymazáno";
      }
      if ( $ok ) $ok= mysql_qry("DROP TABLE IF EXISTS ezer_db2._skill");
      if ( $ok ) $ok= mysql_qry("CREATE TABLE ezer_db2._skill LIKE ezer_ys._skill");
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2._skill SELECT * FROM ezer_ys._skill");
      if ( $ok ) $html.= "<br>_skill: zkopírováno";
      break;
    // ---------------------------------------------- dokumenty: YS
    // vloží zástupce dokumentů do files/db/... podle files/ys/... (klíče na dvojnásobek+1)
    case 'doc_YS':
      $root= 'ys';
    // ---------------------------------------------- dokumenty: FA
    // vloží zástupce dokumentů do files/db/... podle files/fa/... (klíče na dvojnásobek)
    case 'doc_FA':
      $files= substr($ezer_path_root,0,strrpos($ezer_path_root,'/'))."/files";
      foreach ( $ds as $sub=>$par) {
        list($stg)= $par;
        switch ($stg) {
        case 'modi':                          // dokumenty s modifikovaným klíčem _id^ na konci
          $dir= "$files/$root/$sub";
//                                                         display($dir);
          if ($dh= opendir($dir)) {
            while (($file= readdir($dh)) !== false) {
              if ( $file=='.' || $file=='..')  continue;
              // vyrob soubor s odkazem
              preg_match("@^(.*)_(\d+)$@",$file,$m);
//                                                         debug($m,$file);
              $key= $m[2];
              $key2= $cmd=='doc_FA' ? $key*2 : $key*2+1;
              $path2= "$files/db/$sub/{$m[1]}_$key2^";
//                                                 display("$path2:$file");
              file_put_contents($path2,"$root/$sub/$file");
//               break;
            }
          }
          break;
        case 'copy':                          // dokumenty bez klíče s ^ na konci
          $source= "$files/$root/$sub";
          if ( !file_exists($source) ) break;
          $dest= "$files/db/$sub";
          foreach ( $iterator= new RecursiveIteratorIterator(
              new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
              RecursiveIteratorIterator::SELF_FIRST) as $item ) {
            $subpath= str_replace("\\","/",$iterator->getSubPathName());
            $path= "$dest/$subpath";
            if ($item->isDir() ) {
              if ( !file_exists($path) ) {
                                                                display("mkdir $path");
                mkdir($path);
              }
            }
            else {
                                display("file_put_contents($path^,$root/$sub/$subpath");
              file_put_contents("$path^","$root/$sub/$subpath");
//               break;
            }
          }
          break;
        }
      }
      break;
    // ---------------------------------------------- import: YS
    // provede import z ezer_ys=>ezer_db (klíče na dvojnásobek+1 pro nenulové,access=1)
    case 'imp_YS':
      $db= 'ezer_ys';
      $root= 'ys';
    // ---------------------------------------------- import: FA
    // provede import z ezer_fa=>ezer_db (klíče na dvojnásobek,access=2)
    case 'imp_FA':
      foreach ($fs as $tab => $keys) {
        if ( $ok ) $ok= mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
        if ( $ok ) $ok= mysql_qry("CREATE TABLE ezer_db2._tmp_ LIKE $db.$tab");
        if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2._tmp_ SELECT * FROM $db.$tab");
        if ( $ok ) {
          $updt= ''; $main= '';
          foreach ($keys as $key) {
            if ( $key=='access' ) {
              $updt.= ($cmd=='imp_FA' ? ',access=2':',access=1');
            }
            else {
              $updt.= $cmd=='imp_FA' ? ",$key=$key*2" : ",$key=IF($key,$key*2+1,0)";
              $main= $main ?: $key;
            }
          }
          // aplikace změn
          $updt= substr($updt,1);
          $ok= mysql_qry("UPDATE ezer_db2._tmp_ SET $updt ORDER BY $main DESC");
          $nr= mysql_affected_rows();
        }
        if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2.$tab SELECT * FROM ezer_db2._tmp_");
        if ( $ok ) $html.= "<br>$tab: vloženo $nr záznamů";
        mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
      }
      break;
    // ---------------------------------------------- import: cor_mailist
    // pročištění mailist
    case 'cor_mailist':
      //mysql_qry("DELETE mailist FROM ezer_db2.mailist LEFT JOIN ezer_db2.dopis USING (id_mailist)
      //           WHERE ISNULL(id_dopis)");
      mysql_qry("UPDATE ezer_db2.mailist SET parms=REPLACE(parms,'\"akey\":3','\"akey\":5')");
      mysql_qry("UPDATE ezer_db2.mailist SET parms=REPLACE(parms,'\"akey\":4','\"akey\":6')");
      // 3 testovací mailisty
      $json= '{"ano_akce":{"typy":"1","keys":"1","cas":[{"akey":3}]},'
           . '"ne_akce":0,"ucasti":{"funkce":1,"fce_keys":"3,4","muzi":1,"zeny":1,'
           . '"ucasti":{"ucast":0},"mrop":0,"email":1}}';
      $sql= function ($acc) { return '"'.str_replace('  ',' ',str_replace("\n"," ",
         "SELECT o.access,COUNT(*) AS _ucasti,iniciace,o.id_osoba AS _id,
          CONCAT(prijmeni,' ',jmeno) AS _name,
          IF(o.adresa,o.psc,IFNULL(r.psc,'')) AS _psc,
          IF(o.adresa,o.ulice,IFNULL(r.ulice,'')) AS _ulice,
          IF(o.adresa,o.obec,IFNULL(r.obec,'')) AS _obec,
          IF(o.adresa,o.stat,IFNULL(r.stat,'')) AS _stat,
          IF(o.kontakt,o.email,IFNULL(r.emaily,'')) AS _email,nomail
          FROM pobyt AS p JOIN spolu AS s USING (id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba JOIN akce AS a ON a.id_duakce=p.id_akce
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba LEFT JOIN rodina AS r USING (id_rodina)
          WHERE o.deleted='' AND TRUE AND (IF(o.kontakt,o.email!='',FALSE) OR r.emaily!='') AND
          IFNULL(t.role,'?')!='d' AND p.funkce IN (3,4) AND 1 AND p.id_akce IN
            (SELECT id_duakce FROM akce
            WHERE spec=0 AND access&$acc
            AND spec=0 AND YEAR(datum_od)=YEAR(CURDATE()) AND druh IN (1))
          GROUP BY o.id_osoba
          ORDER BY prijmeni")).'"'; };
      mysql_qry("DELETE FROM ezer_db2.mailist WHERE id_mailist<=3");
      mysql_qry("DELETE FROM ezer_db2.dopis WHERE id_mailist BETWEEN 1 AND 3");
      mysql_qry("INSERT INTO ezer_db2.mailist (id_mailist,access,ucel,parms,sexpr)
         VALUES (1,1,'testovací mailist Setkání','$json',".$sql(1).")");
      mysql_qry("INSERT INTO ezer_db2.mailist (id_mailist,access,ucel,parms,sexpr)
         VALUES (2,2,'testovací mailist Familia','$json',".$sql(2).")");
      mysql_qry("INSERT INTO ezer_db2.mailist (id_mailist,access,ucel,parms,sexpr)
         VALUES (3,3,'testovací mailist společný','$json',".$sql(3).")");
      break;
    // ---------------------------------------------- import: imp_clear_track
    // vyprázdní tabulku ezer_db2._track
    case 'imp_clear_track':
      if ( $ok ) $ok= mysql_qry("TRUNCATE TABLE ezer_db2._track");
      if ( $ok ) $html.= "<br>_track: vyprázdněno";
      break;
    // ---------------------------------------------- import: YS (_track)
    // provede import z ezer_ys=>ezer_db (klíče na dvojnásobek+1 pro vybrané kombinace)
    case 'imp_YS_track':
      $db= 'ezer_ys';
      $root= 'ys';
    // ---------------------------------------------- import: FA (_track)
    // provede import z ezer_fa=>ezer_db (klíče na dvojnásobek pro vybrané kombinace)
    case 'imp_FA_track':
      $tab= '_track';
      if ( $ok ) $ok= mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
      if ( $ok ) $ok= mysql_qry("CREATE TABLE ezer_db2._tmp_ LIKE $db.$tab");
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2._tmp_ SELECT * FROM $db.$tab");
      if ( $ok ) {
        // id_track, klic
        $key= 'id_track';
        $updt= $cmd=='imp_FA_track' ? "$key=$key*2" : "$key=IF($key,$key*2+1,0)";
        $key= 'klic';
        $updt.= $cmd=='imp_FA_track' ? ",$key=$key*2" : ",$key=IF($key,$key*2+1,0)";
        $ok= mysql_qry("UPDATE ezer_db2._tmp_ SET $updt ORDER BY id_track DESC");
        // *.r,*.d, tvori, spolu, pobyt
        $key= 'val';
        $updt= $cmd=='imp_FA_track' ? "$key=$key*2" : "$key=IF($key,$key*2+1,0)";
        $ok= mysql_qry("UPDATE ezer_db2._tmp_ SET $updt
          WHERE op='r'
             OR (op='d' AND old!='chlapi')
             OR (kde='spolu' AND fld IN ('id_osoba','id_pobyt','pecovane'))
             OR (kde='pobyt' AND fld IN ('i0_rodina','id_akce'))
             OR (kde='tvori' AND fld IN ('id_rodina','id_osoba'))
          ");
        $nr= mysql_affected_rows();
      }
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2.$tab SELECT * FROM ezer_db2._tmp_");
      if ( $ok ) $html.= "<br>$root $tab: upraveno $nr záznamů";
      mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
      break;
    // ---------------------------------------------- import: imp_user
    // vyčistí databázi ezer_db2, založí uživatele GAN a upraví ZMI,HAN,MSM
    case 'imp_user':
      // výmaz GAN/1,2 a ZMI/1
      if ( $ok ) $ok= mysql_qry("DELETE FROM ezer_db2._user
        WHERE abbr='GAN' OR (abbr='ZMI' AND skills LIKE '% y %')");
      // nový uživatel GAN
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2._user
        (id_user,abbr,username,password,state,org,access,forename,surname,skills) VALUES
        (1,'GAN','gandi','radost','+-Uu',1,3,'Martin','Šmídek',
          'a ah f fa faa faa+ faa:c faan fad fae fam fam famg fams d m mg r sp spk spv y yp yd yaed')");
      //  úprava skill a access pro MSM,HAN,ZMI
      if ( $ok ) $ok= mysql_qry("UPDATE ezer_db2._user SET org=1,access=1,skills=CONCAT('d ',skills)
                                 WHERE abbr='MSM' AND skills NOT LIKE 'd %'");
      if ( $ok ) $ok= mysql_qry("UPDATE ezer_db2._user SET org=2,access=2,skills=CONCAT('d ',skills)
                                 WHERE abbr='HAN' AND skills NOT LIKE 'd %'");
      if ( $ok ) $ok= mysql_qry("UPDATE ezer_db2._user SET org=2,access=3,skills=CONCAT('d ',skills)
                                 WHERE abbr='ZMI' AND skills NOT LIKE 'd %'");
      // vymazat přístup přes IP
      if ( $ok ) $ok= mysql_qry("UPDATE ezer_db2._user SET ips=''");
      // doplnit skill d a sjednotit FA a YS skills
      if ( $ok ) {
        if ( !select('COUNT(*)','ezer_db2._skill',"skill_abbr='d'") ) {
          $ok= mysql_qry("INSERT INTO ezer_db2._skill (skill_abbr, skill_desc) VALUES ('d', 'DB2')");
        }
        $qs= mysql_qry("SELECT skill_abbr, skill_desc FROM ezer_fa._skill");
        while ( $qs && ($s= mysql_fetch_object($qs)) ) {
          if ( !select('COUNT(*)','ezer_db2._skill',"skill_abbr='{$s->skill_abbr}'") ) {
            $ok= mysql_qry("INSERT INTO ezer_db2._skill (skill_abbr, skill_desc)
                            VALUES ('{$s->skill_abbr}', '{$s->skill_desc}')");
          }
        }
      }
      break;
    // ---------------------------------------------- import: imp_cis
    // pročistí číselníky pro DB2, specifické číselníky pro YS2 dáme do tabulky _cis_ys
    case 'imp_cis':
      $nc= $nt= 0;
      // * je třeba rozdělit ys:data*2+1, fa:data*2
      // = jsou stejné
      // 1 použít YS
      // 2 použít FA
      // ! individuální úprava
      // ? promyslet
      $cis_db2_mai= "*db_maily_sql";
      // pro kopii
      $cis_ys= "s_todo_cast,s_todo_email,s_todo_stav,s_todo_typ,evi_vyber_o,evi_vyber_r,db_maily_tab,"
             . "ms_akce_dieta,ms_akce_pfunkce,ms_akce_prednasi,ms_akce_s_role,"
             . "ms_akce_sex,ms_akce_t_role,ms_akce_titul,ms_akce_vzdelani,"
             . "ms_akce_cirkev,ms_akce_dite_kat,ms_cena_vzorec";
      $cis_fa= "ms_akce_clen,ms_akce_platba";
      $cis_xx= array(
                 "ms_akce_funkce"=> array(
                   "ys" => "2",
                   "fa" => "0,1,3,4,5,6,7,8,9,10,12,13,14,99"
                 ),
                 "ms_akce_typ" => array(
                   "tables" => "akce.druh,mailist",
                   "ys" => "1,2,3,5,6,7,8,9,11,17,18,19,20,21,22,23",
                   "fa" => "4,5-24,8-25,9-26,10,12,13,14,15,16"
                 ),
                 "akce_slozka" => array(
                   "ys" => "",
                   "fa" => "1-3"
                 ),
                 "db_maily_sql" => array(
                   "tables" => "dopis.cis_skupina",
                   "ys" => "",
                   "fa" => "1-28,2-29,3-30"
                 )
               );
      // kopie do ezer_db2._cis
      query("TRUNCATE ezer_db2._cis;");
      query("INSERT INTO ezer_db2._cis SELECT * FROM ezer_ys._cis
             WHERE FIND_IN_SET(druh,'$cis_ys') OR (druh='_meta_' AND FIND_IN_SET(zkratka,'$cis_ys'))");
      $nc+= mysql_affected_rows();
      query("INSERT INTO ezer_db2._cis SELECT * FROM ezer_fa._cis
             WHERE FIND_IN_SET(druh,'$cis_fa') OR (druh='_meta_' AND FIND_IN_SET(zkratka,'$cis_fa'))");
      $nc+= mysql_affected_rows();
      // zvláštní úpravy
      if ( !select("COUNT(*)","ezer_db2._cis","id_cis=5803") ) {
      query("INSERT INTO ezer_db2._cis (id_cis,druh,data,zkratka,poradi)
             VALUES ('5803','ms_akce_clen','3','klub','3')");
      $nc+= mysql_affected_rows();
      }
      foreach($cis_xx as $cis=>$desc) {
        // vložení meta
        query("INSERT INTO ezer_db2._cis SELECT * FROM ezer_ys._cis
               WHERE druh='_meta_' AND zkratka='$cis'");
        $nc+= mysql_affected_rows();
        // kopie číselníku
        foreach($desc as $org=>$keys) if ($org=="ys" || $org=="fa") {
          if ( $keys ) {
            foreach(explode(',',$keys) as $key_12) {
              list($key1,$key2)= explode('-',$key_12);
              $xr= mysql_qry("SELECT * FROM ezer_$org._cis WHERE druh='$cis' AND data=$key1");
              $x= mysql_fetch_object($xr);
              $data= $key2 ?: $x->data;
              $id_cis= 100*floor($x->id_cis/100) + $data;
              query("INSERT INTO ezer_db2._cis
                (id_cis,druh,data,hodnota,zkratka,popis,poradi,ikona,barva) VALUES
                ($id_cis,'$x->druh',$data,\"$x->hodnota\",'$x->zkratka','$x->popis',
                 $x->poradi,'$x->ikona','$x->barva')");
              $nc+= mysql_affected_rows();
              // transformace hodnoty v tabulkách
              if ( $key2 && isset($desc['tables']) ) {
                foreach(explode(',',$desc['tables']) as $tab_fld) {
                  if ( $tab_fld=='mailist' ) {
                    $acc= $org=='fa' ? 2 : 1;
                    query("UPDATE ezer_db2.mailist
                           SET parms=REPLACE(parms,'\"keys\":\"$key1\"','\"keys\":\"$key2\"')
                           WHERE access=$acc");
                    $nk= mysql_affected_rows();
                    $html.= "<br>mailist/$org: oprava $key1 na $key2 - $nk x";
                  }
                  else {
                    $nt= 0;
                    list($tab,$fld)= explode('.',$tab_fld);
                    $access= $org=="ys" ? 1 : 2;
                    query("UPDATE ezer_db2.$tab SET $fld=$key2
                           WHERE $fld=$key1 AND access=$access");
                    $nt+= mysql_affected_rows();
                    $html.= "<br>$tab: upraveno $nt záznamů ($org: $fld=$key1=>$key2)";
                  }
                }
              }
            }
          }
          else { // kopie všeho
            query("INSERT INTO ezer_db2._cis SELECT * FROM ezer_$org._cis
                   WHERE FIND_IN_SET(druh,'$cis') ");
            $nc+= mysql_affected_rows();
          }
        }
      }
      $html.= "<br>_cis: upraveno $nc záznamů";
      break;
    default:
      fce_error("transformaci $cmd neumím");
    }
  }
  return $html;
}

?>
