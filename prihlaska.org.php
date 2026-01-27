<?php // answer-test
/*
  (c) 2025 Martin Smidek <martin@smidek.eu>

  konfigurace online přihlašování podle pořadatele akce

 */

$access2org= [
 1 => (object)[ // YMCA Setkání - ostrá databáze
  'smtp'  => 2,
  'name'  => 'YMCA Setkání',
  'deep'  => 'answer/db2.dbs.php',
  'sess'  => 'db2',
  'icon'  => '/db2/img/akce.png',
  'gdpr'  => "Vyplněním této přihlášky dáváme výslovný souhlas s použitím uvedených 
      osobních údajů pro potřeby organizace akcí YMCA Setkání v souladu s Nařízením 
      Evropského parlamentu a Rady (EU) 2016/679 ze dne 27. dubna 2016 o ochraně 
      fyzických osob (GDPR) a zákonem č. 110/2019 Sb. ČR. Na našem webu naleznete 
      <a href='https://www.setkani.org/ymca-setkani/5860#anchor5860' target='show'>
      podrobnou informací o zpracování osobních údajů v YMCA Setkání</a>.",
  'conf'  => "Potvrzuji, že jsem byl@ upozorněn@, že není možné se účastnit pouze části kurzu, 
      že kurz není určen osobám závislým na alkoholu, drogách nebo jiných omamných látkách, ani
      osobám zatíženým neukončenou nevěrou, těžkou duševní nemocí či jiným omezením, která neumožňují 
      zapojit se plně do programu. V případě, že jsem v odborné péči psychologa nebo psychiatra, 
      prohlašuji, že se akce účastním s jeho souhlasem a konzultoval jsem náročnost akce s organizátory. 
      Zatržením prohlašuji, že jsem si plně vědom@, že pořadatel neodpovídá za škody a újmy, které by 
      mně/nám mohly vzniknout v souvislosti s nedodržením těchto zásad účasti na kurzu, a veškerá rizika
      v takovém případě přebíráme na sebe.",
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
  'deep'  => 'answer/db2.dbs.php',
  'sess'  => 'db2',
  'icon'  => '/db2/img/akce-fa.png',
  'gdpr'  => "Vyplněním této přihlášky dáváme výslovný souhlas s použitím uvedených 
      osobních údajů a fotografií z akce pro potřeby organizace YMCA Familia v souladu 
      s Nařízením Evropského parlamentu a Rady (EU) 2016/679 ze dne 27. dubna 2016 
      o ochraně fyzických osob a zákonem č. 110/2019 Sb. ČR. Podrobnou Informaci 
      o zpracování osobních údajů v YMCA Familia naleznete na našem webu:
      <a href='http://www.familia.cz/familia/odkazy/' target='show'>
      http://www.familia.cz/familia/odkazy/</a>.",
  'conf'  => "V případě, že jsem v odborné péči psychologa nebo psychiatra, prohlašuji, že se "
     . "akce účastním s jeho souhlasem a konzultoval jsem program semináře s organizátory.",
   // default pro garanta akce, pokud není dostupný z AKCE/Úprava
  'info'  => (object)[
      'name'=>'Carlos Plechl',
      'mail'=>'sekretariat@familia.cz',
      'tlfn'=>''],
  ],
 8 => (object)[ // Šance pro manželství - ostrá databáze
  'smtp'  => 1,
  'name'  => 'Šance pro manželství',
  'deep'  => 'answer/ms2.dbs.php',
  'sess'  => 'ms2',
  'icon'  => '/db2/img/akce.png',
  'gdpr'  => "Vyplněním této přihlášky dáváme výslovný souhlas s použitím uvedených 
      osobních údajů pro potřeby organizace akcí Šance pro manželství v souladu s Nařízením 
      Evropského parlamentu a Rady (EU) 2016/679 ze dne 27. dubna 2016 o ochraně 
      fyzických osob (GDPR) a zákonem č. 110/2019 Sb. ČR.",
  'conf'  => "Potvrzuji, že jsem byl@ upozorněn@, že není možné se účastnit pouze části kurzu, 
      že kurz není určen osobám závislým na alkoholu, drogách nebo jiných omamných látkách, ani
      osobám zatíženým neukončenou nevěrou, těžkou duševní nemocí či jiným omezením, která neumožňují 
      zapojit se plně do programu. V případě, že jsem v odborné péči psychologa nebo psychiatra, 
      prohlašuji, že se akce účastním s jeho souhlasem a konzultoval jsem náročnost akce s organizátory. 
      Zatržením prohlašuji, že jsem si plně vědom@, že pořadatel neodpovídá za škody a újmy, které by 
      mně/nám mohly vzniknout v souvislosti s nedodržením těchto zásad účasti na kurzu, a veškerá rizika
      v takovém případě přebíráme na sebe.",
   // default pro garanta akce, pokud není dostupný z AKCE/Úprava
  'info'  => (object)[
      'name'=>'Ondřej Lednický',
      'mail'=>'info@manzelskasetkani.cz',
      'tlfn'=>'+420 734 647 785'
    ],
  ],   
];
