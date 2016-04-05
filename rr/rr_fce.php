<?php # (c) 2007-2012 Martin Smidek <martin@smidek.eu>
# ================================================================================================== RR
# -------------------------------------------------------------------------------------------------- rr_nastav
# $par = {den:ode dneška,poslat: 0/1}
function rr_nastav($den,$datum,$pocet) {  trace();
  $ret= (object)array('msg'=>'','last'=>'','next'=>'');
  $nastaveno= 0;
  $dat= sql_date1($datum,1);
  $ndat0= sql2stamp($dat);
  for ($d= 0; $d<$pocet; $d++) {
    $day_n= $den+$d;
    $ndatum= date('Y-m-d',$ndat0+$d*60*60*24);
    query("UPDATE rr SET datum='$ndatum',state='prepared' WHERE day_n=$day_n");
    $nastaveno+= mysql_affected_rows();
  }
  $ret->last= date('Y-m-d',$ndat0+($pocet-1)*60*60*24);
  $ret->next= date('Y-m-d',$ndat0+$pocet*60*60*24);
  $ret->msg= "nastaveno $nastaveno dnů od $dat po $ndatum";
  return $ret;
}
# -------------------------------------------------------------------------------------------------- rr_send
# $par = {den:ode dneška,poslat: 0/1}
function rr_send($par) {
  global $EZER;
  $offset= $par->den<0 ? "-INTERVAL ".abs($par->den)." DAY" : ($par->den>0 ? "+INTERVAL {$par->den} DAY" : '');
  $plus= $par->den ? $par->den : 0;
  $dnes= date('j/n/Y',mktime(0,0,0,date('n'),date('j')+$plus,date('Y')));
  $html= "neni pro $dnes nastaveno! ($offset)";
  //return $html;
  ezer_connect();
  $qry= "SELECT * FROM rr WHERE datum=curdate()$offset ";
  $res= mysql_qry($qry);
                                                $html.= "<br>$res=$qry";
  while ( $res && ($o= mysql_fetch_object($res)) ) {
//     $html= $o->text_cz;
    $day_n= $o->day_n;
    $day= $o->day;
    $subject= $o->subject;
    $datum= $o->datum;
    $state= $o->state;
    $title_cz= $o->title_cz;
    $text_cz= $o->text_cz;
    $text_cz= strtr($text_cz,array('š'=>'&scaron;','ž'=>'&#382;'));
    $title_en= $o->title_en;
    $text_en= $o->text_en;
    $from_en= $o->from_en;
    // formátování
    $subject= strtr($subject,array(
        'Neděle'=>'neděli', 'Pondělí'=>'pondělí', 'Úterý'=>'úterý', 'Středa'=>'středu'
      , 'Čtvrtek'=>'čtvrtek', 'Pátek'=>'pátek', 'Sobota'=>'sobotu'
      , 'První'=>'první', 'Druhá'=>'druhou', 'Čtvrtá'=>'čtvrtou', 'Pátá'=>'pátou'
      , 'Šestá'=>'šestou', 'Sedmá' => 'sedmou'
      ));
    $subj= "Richard Rohr na $subject - $title_cz";
    $body= "<table cellpadding='10'><tr>";
    $body.= "<td valign='top' width='50%'><b>$title_cz</b><br>$text_cz</td>";
    $body.= "<td valign='top' width='50%'><b>$title_en</b><br>$text_en<div align='right'>$from_en</div></td>";
    $body.= "</tr></table>";
    $html= "<h1>$subj</h1>$body";
    if ( $par->poslat  ) {
      if ( $state=='prepared' || $par->opakovat || $_GET['again']=='yes' ) {
        // odeslání a ochrana proti zdvojení
        $email= $_GET['email'] ? $_GET['email'] : 'chlapi-myslenky@googlegroups.com';
        $html.= "<hr/>zaslání na <i>$email</i> skončilo se stavem ";
        $ok= send_simple_mail($subj,$body,'smidek@proglas.cz',$email,'Richard Rohr');
        $html.= $ok;
        //$html.= $mail->sendHtmlMail('smidek@proglas.cz',$email,'','',$subj,$body,'Richard Rohr');
        if ( $ok && !isset($_GET['email']) ) {
          query("UPDATE rr SET state='sent' WHERE day_n=$day_n ");
        }
      }
      else $html= "Pozor! Už bylo jednou zasláno, lze vynutit klíčem again=yes<hr/>$html";
    }
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- send_gmail
# pošle mail přes GMAIL
# $to může být seznam adres oddělený čárkou
function send_simple_mail($subject,$html,$from='',$to='',$fromname='') { trace();
  global $ezer_path_serv, $ezer_root, $EZER;
  $from= $from ? $from : ($EZER->smtp->from ? $EZER->smtp->from : $EZER->options->mail);
  $fromname= $fromname ? $fromname : $ezer_root;
  $to= $to ? $to : $EZER->options->mail;
  // poslání mailu
  $phpmailer_path= "$ezer_path_serv/licensed/phpmailer";
  require_once("$phpmailer_path/class.phpmailer.php");
  // napojení na mailer
  $mail= new PHPMailer;
  $mail->Mailer= 'mail';
  // kompozice mailu
  $mail->SetLanguage('cz',"$phpmailer_path/language/");
  $mail->CharSet = "utf-8";
  $mail->From= $from;
  $mail->FromName= $fromname;
  foreach (explode(',',$to) as $to1) {
    $mail->AddAddress($to1);
  }
  $mail->Subject= $subject;
  $mail->Body= $html;
  $mail->IsHTML(true);
  // pošli
  $ok= $mail->Send();
//                                                 display("send_mail=$ok,".$mail->ErrorInfo);
  if ( !$ok )
    fce_warning("Selhalo odeslání mailu: $mail->ErrorInfo");
  else {
//                                                 $mail->Subject= $mail->Body= $mail->language= "---";
//                                                 debug($mail,"send_mail(..,..,$from,$to)=$ok");
  }
  return $ok;
}?>
