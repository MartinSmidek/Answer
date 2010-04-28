* Transformace dat pro YMCA SETKANI do systému Ans(w)er (MySQL) ze systému Say (Foxpro)
*   podle http://forums.mysql.com/read.php?126,2051,112501#msg-112501
* import into mySql
*   mysql -u user -ppassword database < dbf-filename-no-ext.sql
* po importu je třeba provést fukci ys_akce.php/akce_data
* (c) 2010, Martin Šmídek

set deleted on
public all_in_one,mimporthandle
all_in_one= .t.

if all_in_one
  mexportfile = "c:\apache\htdocs\ezer\www-ys2\sql\sql\all.sql"
*   delete file &mexportfile
*   if file(mexportfile) && Does file exist?
*     mimporthandle = fopen(mexportfile,11) && If so, open write only
*   else
    mimporthandle = fcreate(mexportfile) && If not, create it
*   endif
  if mimporthandle < 1
    messagebox("could not open output file")
    cancel
  endif
endif

* mysql_export(1,"ms-lida","L","DETI")
mysql_export(1,"ms-lida","L","DRUHAKCE")
* mysql_export(1,"ms-lida","L","KURS")
* mysql_export(1,"ms-lida","L","KURSDETI")
* mysql_export(1,"ms-lida","L","KURSPECE")
* mysql_export(1,"ms-lida","L","PARY")
* mysql_export(1,"ms-lida","L","PECE")
* mysql_export(1,"ms-lida","L","PORAAKCE")
*
* mysql_export(0,"ms-milos","M","DETI")
mysql_export(0,"ms-milos","M","DRUHAKCE")
* mysql_export(0,"ms-milos","M","KURS")
* mysql_export(0,"ms-milos","M","KURSDETI")
* mysql_export(0,"ms-milos","M","KURSPECE")
* mysql_export(0,"ms-milos","M","PARY")
* mysql_export(0,"ms-milos","M","PECE")
* mysql_export(0,"ms-milos","M","PORAAKCE")

if all_in_one
  fclose(mimporthandle)
endif

*c:\apache\mysql\bin\mysql -ugandi db_ms < c:\apache\htdocs\ezer\www-ys2\sql\sql\deti.sql
*c:\apache\mysql\bin\mysql -ugandi db_ms < c:\apache\htdocs\ezer\www-ys2\sql\sql\druhakce.sql
*c:\apache\mysql\bin\mysql -ugandi db_ms < c:\apache\htdocs\ezer\www-ys2\sql\sql\kurs.sql
*c:\apache\mysql\bin\mysql -ugandi db_ms < c:\apache\htdocs\ezer\www-ys2\sql\sql\kursdeti.sql
*c:\apache\mysql\bin\mysql -ugandi db_ms < c:\apache\htdocs\ezer\www-ys2\sql\sql\kurspece.sql
*c:\apache\mysql\bin\mysql -ugandi db_ms < c:\apache\htdocs\ezer\www-ys2\sql\sql\pary.sql
*c:\apache\mysql\bin\mysql -ugandi db_ms < c:\apache\htdocs\ezer\www-ys2\sql\sql\poraakce.sql
*c:\apache\mysql\bin\mysql -ugandi db_ms < c:\apache\htdocs\ezer\www-ys2\sql\sql\sestavy.sql

procedure mysql_export
  parameters to_create,folder,source,mdbf
  public all_in_one,mimporthandle
  * ------------------------------------------------------------------------------------------------ STRUCTURE
  mdbf = lower(mdbf)
  mdbffile = "c:\apache\htdocs\ezer\www-ys2\sql\" + folder + "\data\" + mdbf + ".dbf"
  if not all_in_one
    * where you want mysqldump files to go
    mexportfile = "c:\apache\htdocs\ezer\www-ys2\sql\sql\" + folder + "\" + mdbf + ".sql"
    * where dbf files are
    set default to "c:\apache\htdocs\ezer\www-ys2\sql\" + folder + "\data"
    if !file(mdbffile)
      messagebox("not a valid file")
      cancel
    endif
    * open/create file to hold sql commands
    * target is a linux box so only lf "chr(10)" at end of line
    *delete sql dump file if exists (important)
    delete file &mexportfile
    if file(mexportfile) && Does file exist?
      mimporthandle = fopen(mexportfile,11) && If so, open write only
    else
      mimporthandle = fcreate(mexportfile) && If not, create it
    endif
    if mimporthandle < 1
      messagebox("could not open output file")
      cancel
    endif
  endif
  set century on
  set hours to 24
  mjunk = 0
  use &mdbffile
*!*    set safety off
*!*    *remove deleted records
*!*    pack
  go top
  * setup array to hold field attributes
  mfield_cnt = afields(marray) && Create array
  if to_create=1
    * Create text to build create table commands
    mtext = "DROP TABLE IF EXISTS `ms_" + mdbf + "`;" + chr(10)
    mjunk = fwrite(mimporthandle, mtext)
    klic = ''
    do case
    case mdbf == 'druhakce'
      klic = chr(10) + "`id_akce` int(11) NOT NULL AUTO_INCREMENT,"
    case mdbf == 'pary'
      klic = chr(10) + "`id_pary` int(11) NOT NULL AUTO_INCREMENT,"
    case mdbf == 'kurs'
      klic = chr(10) + "`id_kurs` int(11) NOT NULL AUTO_INCREMENT,"
      klic = klic + chr(10) + "`id_pary` int(11),"
      klic = klic + chr(10) + "`id_akce` int(11),"
    case mdbf == 'deti'
      klic = chr(10) + "`id_deti` int(11) NOT NULL AUTO_INCREMENT,"
    case mdbf == 'kursdeti'
      klic = chr(10) + "`id_kursdeti` int(11) NOT NULL AUTO_INCREMENT,"
      klic = klic + chr(10) + "`id_pary` int(11),"
      klic = klic + chr(10) + "`id_akce` int(11),"
    endcase
    mtext = "CREATE TABLE IF NOT EXISTS `ms_" + mdbf + "`(" + klic + chr(10) + "`source` char(1) NOT NULL," + chr(10)
    mjunk = fwrite(mimporthandle, mtext)
    for nCount = 1 to mfield_cnt
      mtext = ""
      mtext = "`" + lower(marray(nCount,1)) + "` " && field name
      f_id= mdbf + "." + lower(marray(nCount,1))
      if (f_id=='pary.rodcislo_m') or (f_id=='pary.rodcislo_z') or (f_id=='deti.rodcislo')
        mtext = mtext + "varchar(10) "
      else
        do case
        case marray(nCount,2) = "C"
          mtext = mtext + "varchar(" + alltrim(str(marray(nCount,3))) + ") COLLATE utf8_czech_ci "
        case marray(nCount,2) = "N" AND  marray(nCount,4) = 0			&&g070831
          mtext = mtext + "int(" + alltrim(str(marray(nCount,3))) + ") "		&&g070831
        case inlist(marray(nCount,2), 'N', 'F', 'B')
          mtext = mtext + "decimal(" + alltrim(str(marray(nCount,3))) + "," + alltrim(str(marray(nCount,4))) + ") "
        case marray(nCount,2) = "D"
          mtext = mtext + "date "
        case marray(nCount,2) = "L"
          mtext = mtext + "tinyint(1) "
        case marray(nCount,2) = "I"
          mtext = mtext + "int(" + alltrim(str(marray(nCount,3))) + ") "
        case marray(nCount,2) = "T"
          mtext = mtext + "datetime "
        case marray(nCount,2) = "M"
          mtext = mtext + "text COLLATE utf8_czech_ci "
        otherwise
          mtext = mtext + "unknown data type "
        endcase
      endif

      if !marray(nCount, 5)
        mtext = mtext + "NOT NULL "
      endif

      if nCount < mfield_cnt
        mtext = mtext + "," + chr(10)
      else
        mtext = mtext + chr(10)
      endif
  *!*      if nCount < mfield_cnt
  *!*        mtext = mtext + "default '" + marray(nCount,9) + "'," + chr(10)
  *!*      else
  *!*        mtext = mtext + "default '" + marray(nCount,9) + "'" + chr(10)
  *!*      endif

      mjunk = fwrite(mimporthandle, mtext)

    endfor
  * ------------------------------------------------------------------------------------------------ KEYS
    mtext = ''
    do case
    case mdbf == 'druhakce'
      mtext = "," + chr(10) + "PRIMARY KEY (`id_akce`)" + chr(10)
    case mdbf == 'pary'
      mtext = "," + chr(10) + "PRIMARY KEY (`id_" + mdbf + "`)" + chr(10)
    case mdbf == 'kurs'
      mtext = "," + chr(10) + "PRIMARY KEY (`id_" + mdbf + "`)" + chr(10)
    case mdbf == 'deti'
      mtext = "," + chr(10) + "PRIMARY KEY (`id_" + mdbf + "`)" + chr(10)
    case mdbf == 'kursdeti'
      mtext = "," + chr(10) + "PRIMARY KEY (`id_" + mdbf + "`)" + chr(10)
    endcase

    mtext = mtext + ")TYPE=MyISAM DEFAULT CHARSET=utf8 COLLATE utf8_czech_ci COMMENT='" + mdbf + "';" + chr(10) + chr(10)
    mjunk = fwrite(mimporthandle, mtext)
  endif
  
  go top
  * ------------------------------------------------------------------------------------------------ DATA
  do while !eof()
    if DELETED()
      skip 
    endif  
    *beginning of line
    klic = ''
    do case
    case mdbf == 'druhakce'
      klic = "DEFAULT, "
    case mdbf == 'kurs'
      klic = "DEFAULT, "
    case mdbf == 'deti'
      klic = "DEFAULT, "
    case mdbf == 'kursdeti'
      klic = "DEFAULT, "
    endcase
    mtext = "INSERT INTO ms_" + mdbf + " VALUES (" + klic + "'" + source + "',"

    for nCount = 1 to mfield_cnt
      f_id= mdbf + "." + lower(marray(nCount,1))
      *build values
      if nCount > 1 && don't put a comma in the first time
        mtext = mtext + ", "
      endif

      do case
      case (f_id=='pary.rodcislo_m') or (f_id=='pary.rodcislo_z') or (f_id=='deti.rodcislo')
        * transformace rodného čísla aby se neztratila nula na začátku
        mtext = mtext + "'" + padl(alltrim(str(&marray(nCount,1))),10,'0') + "'"
      case (f_id=='pary.cislo') or (f_id=='kurs.cislo') or (f_id=='kursdeti.cislo') or (f_id=='deti.cislo')
        * transformace klíče přidáním '0' pro source='L' a '1' pro source='M'
        mtext = mtext + "'" + alltrim(str(&marray(nCount,1))) + iif(source=='L','0','1') + "'"
      case (f_id=='pary.cirkev_m') or (f_id=='pary.cirkev_z')
        * církve
        *   SELECT count(*),left(group_concat(distinct cirkev_m),50),ccirkev_m FROM `ms_pary`
        *   WHERE source='L' GROUP BY ccirkev_m ORDER BY cirkev_m
        cc= u(&marray(nCount+2,1))
        c= 0
        do case
        case cc==""
          c= 0
        case cc="katolická"
          c= 1
        case cc="evangelická"
          c= 2
        case cc="bez přísluš"
          c= 3
        case cc="apoštolská"
          c= 4
        case cc="adventistic"
          c= 5
        case cc="CČE"
          c= 6
        case cc="bratrská"
          c= 7
        case cc="KS - Praha"
          c= 8
        case cc="KS"
          c= 8
        case cc="př.na křest"
          c= 9
        case cc="kat-nepřesv"
          c= 10
        case cc= "bapt-neprak"
          c= 12
        case cc="Hn.Gr.sekta"
          c= 13
        case cc="řecko-kato"
          c= 14
        case cc="CASD"
          c= 15
        case cc="nevěřící"
          c= 16
        case cc="kat.pasiv."
          c= 17
        case cc="br.jed.bap"
          c= 18
        case cc="ČSH"
          c= 19
        endcase
        mtext = mtext + "'" + alltrim(str(c)) + "'"
      otherwise
        do case
        case marray(nCount,2) = "C" && characters
          if !isnull(&marray(nCount,1))
            mtext = mtext + "'" + strtran(u(&marray(nCount,1)), "'", "\'") + "'"
          else
            mtext = mtext + "'NULL'"
          endif
        case inlist(marray(nCount,2), 'N', 'F', 'B') && numeric,float,double
          if !isnull(&marray(nCount,1))
            mlen = marray(nCount,3)
            mdec = marray(nCount,4)
            mtext = mtext + "'" + alltrim(str(&marray(nCount,1),mlen,mdec)) + "'"
          else
            mtext = mtext + "'NULL'"
          endif
        case marray(nCount,2) = "I" && Integer
          if !isnull(&marray(nCount,1))
            mtext = mtext + "'" + alltrim(str(&marray(nCount,1))) + "'"
          else
            mtext = mtext + "'NULL'"
          endif
        case marray(nCount,2) = "D" && date
          if !isnull(&marray(nCount,1))
            mtext = mtext + "'" + substr(dtos(&marray(nCount,1)), 1, 4)
            mtext = mtext + "-" + substr(dtos(&marray(nCount,1)), 5, 2)
            mtext = mtext + "-" + substr(dtos(&marray(nCount,1)), 7, 2) + "'"
          else
            mtext = mtext + "'NULL'"
          endif
        case marray(nCount,2) = "T" && date time
          if !isnull(&marray(nCount,1))
            mtext = mtext + "'" + substr(dtos(&marray(nCount,1)), 1, 4)
            mtext = mtext + "-" + substr(dtos(&marray(nCount,1)), 5, 2)
            mtext = mtext + "-" + substr(dtos(&marray(nCount,1)), 7, 2)
            mtext = mtext + " " + substr(ttoc(&marray(nCount,1)), 12, 8) + "'"
          else
            mtext = mtext + "'NULL'"
          endif
        case marray(nCount,2) = "L" && logical
          if !isnull(&marray(nCount,1))
            if (&marray(nCount,1))
              mtext = mtext + "'1'"
            else
              mtext = mtext + "'0'"
            endif
          else
            mtext = mtext + "'NULL'"
          endif
        case marray(nCount,2) = "M" && memo
          if !isnull(&marray(nCount,1))
            if memlines(&marray(nCount,1)) = 0
              mtext = mtext + "''"
            else
              store memlines(&marray(nCount,1)) to gnNumLines
              mtext = mtext + "'"
              for gnCount = 1 to gnNumLines
                mtext = mtext + strtran(u(mline(&marray(nCount,1), gnCount)), "'", "\'")
              next
              mtext = mtext + "'"
            endif
          else
            mtext = mtext + "'NULL'"
          endif
        otherwise
          mtext = mtext + "unknown data type "
        endcase
      endcase
      if isnull(mtext)
        set step on
      endif
      *end of data load line
    endfor

    mtext = mtext + ");" + chr(10)
    mjunk = fwrite(mimporthandle, mtext)
    skip

  enddo

  use
  if not all_in_one
    fclose(mimporthandle)
  endif
return
  * ------------------------------------------------------------------------------------------------ UTF-8
proc u
  para str
  str= chrtran(str,'"',"'")
  str= strconv(allt(str),9,1029)
return str

proc n
  para str
  str= strconv(allt(str),11,1029)
return str
