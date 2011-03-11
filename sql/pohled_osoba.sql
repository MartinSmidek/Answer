/*
CREATE VIEW $osoba
  (id_osoba,tab,id,prijmeni,jmeno,rodcis,narozeni,ulice,psc,obec,telefon,email,info) AS
*/

SELECT
  ezer_ys.chlapi.id_chlapi * 10 + 4 AS id_osoba,
  'C' AS tab,
  ezer_ys.chlapi.id_chlapi AS id,
  ezer_ys.chlapi.prijmeni AS prijmeni,
  ezer_ys.chlapi.jmeno AS jmeno,
  '' AS rodcis,
  ezer_ys.chlapi.narozeni AS narozeni,
  ezer_ys.chlapi.ulice AS ulice,
  ezer_ys.chlapi.psc AS psc,
  ezer_ys.chlapi.obec AS obec,
  ezer_ys.chlapi.telefon AS telefon,
  ezer_ys.chlapi.email AS email,
  CONVERT(concat('Iniciace=',ezer_ys.chlapi.iniciace) USING utf8) AS info
FROM ezer_ys.chlapi
WHERE 1

UNION

SELECT
  ezer_ys.ms_pary.id_pary * 10 + 1 AS id_osoba,
  'M' AS tab,
  ezer_ys.ms_pary.id_pary AS id,
  ezer_ys.ms_pary.prijmeni_m AS prijmeni,
  ezer_ys.ms_pary.jmeno_m AS jmeno,
  ezer_ys.ms_pary.rodcislo_m AS rodcis,
  CAST('' AS date) AS narozeni,
  ezer_ys.ms_pary.adresa AS ulice,
  ezer_ys.ms_pary.psc AS psc,
  ezer_ys.ms_pary.mesto AS obec,
  ezer_ys.ms_pary.telefon AS telefon,
  ezer_ys.ms_pary.email AS email,
  CONVERT(concat(vzdelani.zkratka,'\n',ezer_ys.ms_pary.jazyk_m,'\n',cirkev.zkratka,'\n',ezer_ys.ms_pary.aktivita_m,'\n',ezer_ys.ms_pary.zajmy_m,'\n',ezer_ys.ms_pary.zamest_m) USING utf8) AS info
FROM ezer_ys.ms_pary
JOIN ezer_ys._cis vzdelani ON (ezer_ys.ms_pary.vzdelani_m = vzdelani.data) AND (vzdelani.druh = 'ms_akce_vzdelani')
JOIN ezer_ys._cis cirkev ON (ezer_ys.ms_pary.vzdelani_m = cirkev.data) AND (cirkev.druh = 'ms_akce_cirkev')
WHERE ezer_ys.ms_pary.prijmeni_m <> ''

UNION

SELECT
  ezer_ys.ms_pary.id_pary * 10 + 2 AS id_osoba,
  'Z' AS tab,
  ezer_ys.ms_pary.id_pary AS id,
  ezer_ys.ms_pary.prijmeni_z AS prijmeni,
  ezer_ys.ms_pary.jmeno_z AS jmeno,
  ezer_ys.ms_pary.rodcislo_z AS rodcis,
  CAST('' AS date) AS narozeni,
  ezer_ys.ms_pary.adresa AS ulice,
  ezer_ys.ms_pary.psc AS psc,
  ezer_ys.ms_pary.mesto AS obec,
  ezer_ys.ms_pary.telefon AS telefon,
  ezer_ys.ms_pary.email AS email,
  CONVERT(concat(vzdelani.zkratka,'\n',ezer_ys.ms_pary.jazyk_z,'\n',cirkev.zkratka,'\n',ezer_ys.ms_pary.aktivita_m,'\n',ezer_ys.ms_pary.zajmy_m,'\n',ezer_ys.ms_pary.zamest_m) USING utf8) AS info
FROM ezer_ys.ms_pary
JOIN ezer_ys._cis vzdelani ON (ezer_ys.ms_pary.vzdelani_z = vzdelani.data) AND (vzdelani.druh = 'ms_akce_vzdelani')
JOIN ezer_ys._cis cirkev ON (ezer_ys.ms_pary.vzdelani_z = cirkev.data) AND (cirkev.druh = 'ms_akce_cirkev')
WHERE ezer_ys.ms_pary.prijmeni_z <> ''

UNION

SELECT
  setkani.ds_osoba.id_osoba * 10 + 3 AS id_osoba,
  'A' AS tab,
  setkani.ds_osoba.id_osoba AS id,
  setkani.ds_osoba.prijmeni AS prijmeni,
  setkani.ds_osoba.jmeno AS jmeno,
  '' AS rodcis,
  setkani.ds_osoba.narozeni AS narozeni,
  setkani.ds_osoba.ulice AS ulice,
  setkani.ds_osoba.psc AS psc,
  setkani.ds_osoba.obec AS obec,
  setkani.ds_osoba.telefon AS telefon,
  setkani.ds_osoba.email AS email,
  CONVERT(concat('host') USING utf8) AS info
FROM setkani.ds_osoba
WHERE setkani.ds_osoba.prijmeni <> ''
