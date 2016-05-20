<?php
require_once("template.php");
require_once("mini.php");
error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors', 'On');
$ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

$totrace= $ezer_local ? 'Mu' : '';  // Mu

$nam= $ezer_local ? 'gandi' : 'proglas';
$pas= $ezer_local ? '' : 'pr0gl8s';
$ezer_db= array( /* lokální */
  'setkani' =>  array(0,'localhost',$nam,$pas,'utf8')
);
ezer_connect('setkani');
if ( count($_POST) ) {
//   $y= (object)array('cmd'=>$x->cmd);
  $x= array2object($_POST);
  $y= $x;
  server($x);
  header('Content-type: application/json; charset=UTF-8');
  $yjson= json_encode($y);
  echo $yjson;
  exit;
}
$href= $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].
  $_SERVER['SCRIPT_NAME'].'?page=';
$path= isset($_GET['page']) ? explode('!',$_GET['page']) : array('home');
$user= isset($_GET['user']) ? $_GET['user'] : 0;
javascript_init();
$login= "<a href='./cms.php?page={$_GET['page']}'>přihlásit se</a>";
template($href,$path,$user);
die();
?>
