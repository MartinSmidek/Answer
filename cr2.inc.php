<?php # Systém An(w)er/Ezer/YMCA Familia a YMCA Setkání, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # nastavení systému Ans(w)er před voláním AJAX
  #   $answer_db  = logický název hlavní databáze 

  global // import 
    $ezer_root; 
  global // export
    $EZER, $ezer_server, $ezer_version;
  
  // vyzvednutí ostatních hodnot ze SESSION
  $ezer_server=  $_SESSION[$ezer_root]['ezer_server'];
  $ezer_version= $_SESSION[$ezer_root]['ezer'];
  $abs_root=     $_SESSION[$ezer_root]['abs_root'];
  $rel_root=     $_SESSION[$ezer_root]['rel_root'];
  chdir($abs_root);

  // inicializace objektu Ezer
  $EZER= (object)array(
      'version'=>"ezer{$_SESSION[$ezer_root]['ezer']}",
      'options'=>(object)array(
          'mail' => "martin@smidek.eu",
          'phone' => "603&nbsp;150&nbsp;565",
          'author' => "Martin"
      ),
      'activity'=>(object)array());

  // informace pro debugger o poloze ezer modulů
  $dbg_info= (object)array(
    'src_path'  => array('cr2','db2','ezer3.2') // poloha a preference zdrojových modulů
  );

  // databáze
  $deep_root= "../files/answer";
  require_once("$deep_root/cr2.dbs.php");
  
  $path_backup= "$deep_root/sql";
  
  // definice zápisů do _track
  $tracking= '_track';
  $tracked= ',akce,pobyt,spolu,osoba,tvori,rodina,_user,';
  
  // definice modulů specifických pro Answer
  $app_php= array(
    "db2/db-akce.php",
    "db2/db-bank.php",
    "db2/db-cenik.php",
    "db2/db-data.php",
    "db2/db-dotaznik.php",
    "db2/db-dum.php",
    "db2/db-elim.php",
    "db2/db-evid.php",
    "db2/db-foto.php",
    "db2/db-lib.php",
    "db2/db-mail.php",
    "db2/db-mapa.php",
    "db2/db-pece.php",
    "db2/db-pokl.php",
    "db2/db-prihl.php",
    "db2/db-stat.php",
    "db2/db-tisk.php",
    "db2/db-ucast.php",
    "db2/db2_tcpdf.php",
    "ezer$ezer_version/server/ezer_ruian.php",
  );
  
  $ezer= array(
  );
  
  // PDF knihovny
  require_once('tcpdf/tcpdf.php');
//  require_once('tcpdf/db2_tcpdf.php');
 
  // je to aplikace se startem v rootu
  chdir($abs_root);
  require_once("{$EZER->version}/ezer_ajax.php");

//  echo("db2.inc.php end<br>");
  // SPECIFICKÉ PARAMETRY
  global $USER;
  $VPS= 'VPS';
  $EZER->options->org= 'CPR Ostrava';

  
# -----------------------------------------------------------------------------------==> crm_migrace
function crm_migrace() {
  $n= 0;
  $fpath= "docs/answer-crm.csv";
  $flds= "id_rodina;rodina;svatba;spz;r_ulice;r_obec;r_psc;"
       . "id_osoba;jmeno;prijmeni;rodne;pohlavi;role;telefon;email;narozeni;op;ulice;obec;psc;poznamka;"
       . "id_akce;akce;zacatek;funkce;budova;pokoj";
  $f= @fopen($fpath,'w');
  fputs($f, chr(0xEF).chr(0xBB).chr(0xBF));  // BOM pro Excel
  fputcsv($f,explode(';',$flds),';');
  $ro= pdo_qry("
    SELECT 
      i0_rodina,id_osoba,id_akce,kontakt,adresa,
      IFNULL(r.telefony,'') AS telefony,IFNULL(r.emaily,'') AS emaily,
      IFNULL(r.nazev,'') AS rodina,IFNULL(r.datsvatba,'') AS svatba,IFNULL(r.spz,'') AS spz,
      IFNULL(r.ulice,'') AS r_ulice,IFNULL(r.obec,'') AS r_obec,IFNULL(r.psc,'') AS r_psc,
      IFNULL(r.note,'') AS r_pozn,

      jmeno,prijmeni,rodne,sex,IFNULL(role,'') AS role,telefon,email,narozeni,obcanka,
      o.ulice,o.obec,o.psc,o.note AS o_pozn,

      a.datum_od,a.nazev AS akce,datum_od,p.funkce,budova,pokoj,p.poznamka AS p_pozn,s.poznamka AS s_pozn
      
    FROM osoba AS o
    JOIN spolu AS s USING (id_osoba)
    JOIN pobyt AS p USING (id_pobyt)
    JOIN akce AS a ON id_akce=id_duakce
    LEFT JOIN rodina AS r ON id_rodina=i0_rodina
    LEFT JOIN tvori AS t USING (id_osoba,id_rodina)
    WHERE o.deleted='' AND YEAR(datum_od) BETWEEN 2021 AND 2025
    ORDER BY IF(i0_rodina=0,1,0),r.nazev,prijmeni,jmeno
  ");
  while ( $ro && $o= pdo_fetch_object($ro) ) {
    $sex= $o->sex==1 ? 'M' : ($o->sex==2 ? 'Ž' : '');
    $role= $o->role=='a' ? 'manžel' : ($o->role=='b' ? 'manželka' : ($o->role=='d' ? 'dítě' : ''));
    $telefon= $o->telefon ?: $o->telefony;
    $email= $o->email ?: $o->emaily;
    $a_funkce= map_cis('ms_akce_funkce','zkratka');  $a_funkce[0]= '';
    $funkce= $a_funkce[$o->funkce]??'';
    $list= [
      $o->i0_rodina,$o->rodina,$o->svatba,$o->spz,$o->r_ulice,$o->r_obec,$o->r_psc,
      $o->id_osoba,$o->jmeno,$o->prijmeni,$o->rodne,$sex,$role,$telefon,$email,$o->narozeni,
      $o->obcanka,$o->ulice,$o->obec,$o->psc,$o->o_pozn,
      $o->id_akce,$o->akce,$o->datum_od,$funkce,$o->budova,$o->pokoj
    ];
    fputcsv($f,$list,';');
    $n++;
  }
  fclose($f);
  $html.= "Exportováno $n řádků do <a href='$fpath'>CSV</a>";
  return $html;
}
