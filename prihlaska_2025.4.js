/* global jQuery, myself_sid, myself_url */
/*
  (c) 2025 Martin Smidek <martin@smidek.eu>
   
  online přihlašování pro ASC - verze 2025.3
  debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

 */
// -------------------------------------------------------------------------------------- pretty log
// volá se z prihlaska.log.php -- upraví dynamicky HTML
// pokud je zadán $patt zobrazí jen řádky, které jej obsahují
// pokud je zadan akce dá se její název do nadpisu
function pretty_log(patt,akce) { 
  if (!pretty_log.called) {
    // vložení pole pro dotaz - jen poprvé
    let orgs= {0:'', 1:'YMCA Setkání',2:'YMCA Familia',8:'Šance pro manželství'},
        url= (new URL(window.location.href)).toString(),
        m= url.match(/prihlaska\.log\.(\d+)\.php/),
        org= orgs[m ? m[1] : 0]??'';
    let head= `
        <h3>Online přihlášky na akce ${org}</h3>
        <label for="inputField" style="font-weight:bold;font-family:monospace">
        Vyber řádky vyhovující regulárnímu výrazu:
      <input type="text" id="inputField" placeholder="například ID.*POBYT" 
        style="font-family:monospace">+Enter nebo 
      <button onclick="reload_bez_akce();">zobraz vše</button></label>`;
    document.body.insertAdjacentHTML('afterbegin', head);
    document.getElementById('inputField').addEventListener('keydown', function(event) {
      if (event.key === 'Enter') {
        const inputValue = event.target.value; // Získání hodnoty z pole
        pretty_log(inputValue); // Zavolání funkce find
      }
    });
    // obrácení a kompatibilita se staršími formáty
    let log= $('#log').html();
    let lines= log.split("\n");
    for (let i= lines.length-2; i>0; i--) {
      if (lines[i][6]!='/' && lines[i][5]!='=') {
        if (lines[i][11]=='-') {
          let match= lines[i].match(/akce=A_(\d+)/);
          let ida= match ? match[1] : '?'; 
          lines[i]= lines[i].slice(0,7)+ida.toString().padStart(4,' ')+' '+lines[i].slice(7);
        }
        let match= lines[i].match(/id_prihlaska=(\d+)/);
        let idw= match ? match[1] : '?'; // Číslo je v první zachycené skupině
        lines[i]= lines[i].substr(0,6)+'  '+lines[i].substr(6,25)+idw.toString().padStart(5,' ')
          + lines[i].substr(31);
      }
      // přeškrtni ručně zrušenou chybovou hlášku (má '-' na začátku)
      if (lines[i].substr(0,1)=='-') {
        const solved= lines[i].replace('<b style="color:red">CATCH</b>','CATCH');
        lines[i]= `<s>${solved}</s>`;
      }
    }
    pretty_log.called= true;
    
    pretty_log.lines= lines;
  }
  // zobrazení s filtrem
  let ted= yms_hms();
  let nazev= 'nebo ',  
      mezer= 80,
      row= '',
      hlavicky= ''; 
  for (let i= pretty_log.lines.length-2; i>0; i--) {
    let hlavicka= pretty_log.lines[i].substr(14,1)=='=' ? 1 : 0;
    if (hlavicka) {
      let akce_id= pretty_log.lines[i].substr(9,4),
          akce_nazev= pretty_log.lines[i].substr(16);
      if (akce && akce==akce_id) {
        nazev= 'přihlašování na '+akce_id+': '+'<big>'+akce_nazev+'</big>\n';
      }
      hlavicky+= '\n      vyber jen '+akce_id+': '+akce_nazev;
    }
    else {
      if (patt && !pretty_log.lines[i].match(patt)) continue;
      row+= '\n'+pretty_log.lines[i];
    }
  }
  nazev+= hlavicky;
  let title= nazev+"\n\n<u><b>VERZE/JS AKCE "+ted+"  PŘIHLÁŠKA      KLIENT"+' '.repeat(mezer)+"</u> ";
  $('#log').html(title+row);
}
function reload_bez_akce() {
  const url= new URL(window.location.href);
  url.searchParams.delete("akce");
  window.location.href = url.toString();
}
function yms_hms() {
  // aktuální datum
  let now = new Date();
  let year = now.getFullYear();
  let month = (now.getMonth() + 1).toString().padStart(2, '0'); // +1, protože měsíce jsou indexovány od 0
  let day = now.getDate().toString().padStart(2, '0');
  let hours = now.getHours().toString().padStart(2, '0');
  let minutes = now.getMinutes().toString().padStart(2, '0');
  let seconds = now.getSeconds().toString().padStart(2, '0');  
  return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}
// ------------------------------------------------------------------------------------ elem changed
// vrátí změněný element s jeho novou hodnotou
function elem_changed(elem) {
  elem.classList.add('chng');
  let x= {cmd:'DOM_zmena',args:[]};
  let id= jQuery(elem).attr('id');
  x.args[0]= id;
  if (id && jQuery(elem).hasClass('chng')) { 
    if (jQuery(elem).attr('type') === 'checkbox') {
        // Pro checkbox vrátíme 0 nebo 1 podle stavu
        x.args[1]= jQuery(elem).is(':checked') ? 1 : 0;
    } else {
        // Pro ostatní inputy a textarea vrátíme jejich hodnotu
        x.args[1]= jQuery(elem).val();
    }
  }
  ask(x,after_php);
}
// --------------------------------------------------------------------------------------- clear css
// odstraní dané css
function clear_css(css) {
  jQuery('.'+css).removeClass(css);
}
// ===========================================================================================> AJAX
// ---------------------------------------------------------------- php se jménem funkce v button.id
// pro název PHP funkce vzatý z button.id
function php(pars) {
  let button= event.target;
  // zabráníme dvojkliku
  button.disabled= true; setTimeout(() => { button.disabled = false;}, 2000 );
  php0(button.id+(pars?',':'')+(pars||''));
}
// ---------------------------------------------------------------------------- php se jménem funkce
// název PHP funkce je v prvním parametru 
function php2(name_pars) {
  let button= event.target;
  // zabráníme dvojkliku
  button.disabled= true; setTimeout(() => { button.disabled = false;}, 2000 );
  php0(name_pars);
}
// --------------------------------------------------------------------------------- tělo php a php2
// název PHP funkce je v prvním parametru, další mohou být
// =val => předá se hodnota
//  * => předá se pole [id:val,...] pro všechny input a textarea
// id => předá se id.val
function php0(name_pars) {
  let np= name_pars.split(/,/), 
      x= {cmd:np.shift(),args:[]};
  for (let i= 0; i<np.length; i++) {
    if (np[i][0]=='=') {
      x.args[i]= np[i].substring(1);
    }
    else if (np[i]=='*') {
      x.args[i]= {};
      jQuery('input, textarea').each(function() {
        let id= jQuery(this).attr('id');
        if (id && jQuery(this).hasClass('chng')) { 
          if (jQuery(this).attr('type') === 'checkbox') {
              // Pro checkbox vrátíme 0 nebo 1 podle stavu
              x.args[i][id]= jQuery(this).is(':checked') ? 1 : 0;
          } else {
              // Pro ostatní inputy a textarea vrátíme jejich hodnotu
              x.args[i][id]= jQuery(this).val();
          }
        }
      });
    }
    else
      x.args[i]= jQuery(`#${np[i]}`).val();
  }
  jQuery('#errorbox').hide().html('');
  jQuery('#mailbox').hide().html('');
//  jQuery('.popup').hide(); 
  jQuery('#popup_mask').hide()
  ask(x,after_php);
}
// --------------------------------------------------------------------------------------- after php
// reakce na návratovou hodnotu PHP funkce, jejíž jméno je v DOM.php_function
function after_php(DOM) {
  let unknowns= [];
  for (const id in DOM) {
    if (id=='php_function') continue;
    let elem= jQuery(`#${id}`),
        closure= elem.closest('label').length ? elem.closest('label') : elem,
        value= DOM[id];
    if (!elem.length) {
      unknowns.push(id);
    }
    if (value==='') continue;
    if (!Array.isArray(value)) value= [value];
    for (let i = 0; i < value.length; i++) {
      let val = value[i];
      switch (val) {
        case 'hide': // pokud je obalené label uprav to
          closure.hide(); break;
        case 'show': 
          closure.show(); break;
        case 'disable': 
          elem.prop('disabled', true); break;
        case 'enable': 
          elem.prop('disabled', false); break;
        case 'ok': 
          elem.addClass('chng_ok').removeClass('chng'); break;
        case 'ko': 
          elem.addClass('chng').removeClass('chng_ok'); break;
        case 'remove': 
          elem.remove(); break;
        case 'empty': 
          if (elem.is('input')) 
            elem.val(''); 
          else
            elem.empty(); 
          break;
        default: 
          if (elem.is('input')) {
            if (elem.is(':checkbox')) {
              elem.prop('checked',val); 
            }
            else {
              elem.val(val); 
            }
          }
          else 
            elem.html(val.charAt(0)=='+' 
              ? elem.html()+val.substring(1) 
              : val)
      }
    }
  }
  if (unknowns.length) {
    let x= {cmd:'DOM_unknown',args:[unknowns,DOM.php_function]};
    ask(x);
  }
}
// --------------------------------------------------------------------------------------------- ask
// ask(x,then): dotaz na server se jménem funkce po dokončení
function ask(x,then,arg) {
  if (myself_sid) 
    x.sid= myself_sid;
  jQuery.ajax({url:myself_url, data:x, method: 'POST',
    success: function(y) {
      if ( typeof(y)==='string' ) { 
        errorbox_only(y);
      }
      else if ( y.error ) {
        errorbox_only(y.error);
      }
      else if ( then ) {
        then.apply(undefined,[y,arg]);
      }
    },
    error: function(xhr) {
      errorbox_only(xhr.responseText);
    }
  })
}
// ------------------------------------------------------------------------------------------- error
function error(msg,DOM) {
  let tr= DOM.trace || '',
      index= tr.indexOf("<hr>");
  tr= index !== -1 ? tr.substring(0, index) : tr.substring(0, 24);
  if (!tr) tr= 'empty trace';
  let x= {cmd:'DOM_error',args:[msg,tr]};
  ask(x);
}
function errorbox_only(msg) {
  jQuery('#form').hide();
  jQuery('.popup').hide();
  jQuery('#popup_mask').hide();
  jQuery('#errorbox').show().html(msg);
}
