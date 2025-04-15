<?php # (c) 2008 Martin Smidek <martin@smidek.eu>
  error_reporting(E_ALL & ~E_NOTICE);
  session_start();
  $ezer_root= 'dbt';
  # -------------------------------------------------------------------------------------- show file
  $title= $_GET['title'];
  // test přihlášení
  $er= 0;
  $user_id= isset($_SESSION[$ezer_root]) && isset($_SESSION[$ezer_root]['user_id'])
          ? $_SESSION[$ezer_root]['user_id'] : 0;
  if ( !$user_id ) {
    $er= "uzivatel neni prihlasen"; goto err;
  }
  $path= $_SESSION[$ezer_root]['path_file'];
  // zjištění cesty a existence souboru uloženého do úložiště H
  if ( !file_exists($path)) {
    $er= "cesta '$path' ke slozce neni dostupna"; goto err;
  };
  $file= str_replace('//','/',"$path/$title");
  if ( !file_exists($file)) {
    $er= "soubor '$file' neni dostupny"; goto err;
  };
  // zjištění typu
  $f= pathinfo($file);
  $fext= substr(strtolower($f['extension']),0,3);
  // poslání souboru
//   header('Content-Description:File Transfer');
  switch ($fext) {
  case 'pdf':
              header('Content-Type:application/pdf');
//               header('Content-Type:application/x-google-chrome-pdf');
              header("Content-Disposition: inline; filename=\"$title\";");
              header('Content-Transfer-Encoding:binary');
              break;
  case 'png': header('Content-Type:image/png');
              header("Content-Disposition: inline; filename=\"$title\";");
              break;
  case 'jpe':
  case 'jpg': header('Content-Type:image/jpeg');
              header("Content-Disposition: inline; filename=\"$title\";");
              break;
  default:    header('Content-Type: application/octet-stream');
              header("Content-Disposition: attachment; filename=\"$title\";");
              break;
  }
//   header('Expires: 0');
//   header('Cache-Control: must-revalidate');
//   header('Pragma: public');
  header('Content-Length:' . filesize($file));
  readfile($file);
//   echo("$fext*$title");
  exit;
err:
  echo("soubor '$title' nelze zobrazit: $er");
?>
