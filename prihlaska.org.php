<?php // answer-test
/*
  (c) 2025 Martin Smidek <martin@smidek.eu>

  konfigurace online přihlašování podle pořadatele akce

 */

$access2org= [
 8 => (object)[ // Šance pro manželství
  'smtp'  => 1,
  'name'  => 'Šance pro manželství',
  'deep'  => 'answer/ms2_test.dbs.php',
  'sess'  => 'ms2',
  'icon'  => '/db2/img/akce.png',
  'gdpr'  => "Vyplněním této přihlášky dáváme výslovný souhlas s použitím uvedených 
      osobních údajů pro potřeby organizace akcí Šance pro manželství v souladu s Nařízením 
      Evropského parlamentu a Rady (EU) 2016/679 ze dne 27. dubna 2016 o ochraně 
      fyzických osob (GDPR) a zákonem č. 110/2019 Sb. ČR.",
   // default pro garanta akce, pokud není dostupný z AKCE/Úprava
  'info'  => (object)[
      'name'=>'Ondřej Lednický',
      'mail'=>'info@manzelskasetkani.cz',
      'tlfn'=>'+420 734 647 785'
    ],
  ],   
 1 => (object)[ // YMCA Setkání - ostrá databáze
  'smtp'  => 2,
  'name'  => 'YMCA Setkání',
  'deep'  => 'answer/dbt.dbs.php',
  'icon'  => '/db2/img/akce.png',
  'sess'  => 'dbt',
  'gdpr'  => "Vyplněním této přihlášky dáváme výslovný souhlas s použitím uvedených 
      osobních údajů pro potřeby organizace akcí YMCA Setkání v souladu s Nařízením 
      Evropského parlamentu a Rady (EU) 2016/679 ze dne 27. dubna 2016 o ochraně 
      fyzických osob (GDPR) a zákonem č. 110/2019 Sb. ČR. Na našem webu naleznete 
      <a href='https://www.setkani.org/ymca-setkani/5860#anchor5860' target='show'>
      podrobnou informací o zpracování osobních údajů v YMCA Setkání</a>.",
   // default pro garanta akce, pokud není dostupný z AKCE/Úprava
  'info'  => (object)[
      'name'=>'Markéta Zelinková',
      'mail'=>'kancelar@setkani.org',
      'tlfn'=>''
    ],
  ],
 2 => (object)[ // YMCA Familia - ostrá databáze
  'smtp'  => 6,
  'name'  => 'YMCA Familia',
  'deep'  => 'answer/dbt.dbs.php',
  'sess'  => 'dbt',
  'icon'  => '/db2/img/akce_fa.png',
  'gdpr'  => "Vyplněním této přihlášky dáváme výslovný souhlas s použitím uvedených 
      osobních údajů a fotografií z akce pro potřeby organizace YMCA Familia v souladu 
      s Nařízením Evropského parlamentu a Rady (EU) 2016/679 ze dne 27. dubna 2016 
      o ochraně fyzických osob a zákonem č. 110/2019 Sb. ČR. Podrobnou Informaci 
      o zpracování osobních údajů v YMCA Familia naleznete na našem webu:
      <a href='http://www.familia.cz/familia/odkazy/' target='show'>
      http://www.familia.cz/familia/odkazy/</a>.",
   // default pro garanta akce, pokud není dostupný z AKCE/Úprava
  'info'  => (object)[
      'name'=>'Carlos Plechl',
      'mail'=>'akce@chlapi.cz',
      'tlfn'=>''],
  ],
];
