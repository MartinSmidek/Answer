<?php // answer-test
/*
  (c) 2025 Martin Smidek <martin@smidek.eu>

  konfigurace online přihlašování pro ASC

 */

$ORG= (object)[
  'code'  => 1,
  'smtp'  => 6,
  'name'  => 'YMCA Setkání',
  'deep'  => 'answer/dbt.dbs.php',
  'icon'  => '/db2/img/akce_test.png',
   // default pro garanta akce, pokud není dostupný z AKCE/Úprava
  'info'  => ['name'=>'Markéta Zelinková','mail'=>'kancelar@setkani.org','tlfn'=>''],
];
