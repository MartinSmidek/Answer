/* global jQuery */
/*
  (c) 2025 Martin Smidek <martin@smidek.eu>
   
  pilotní verze online přihlašování pro YMCA Setkání (jen obnovy a LK MS YS)
  debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

 */

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
// reakce na návratovou hodnotu PHP funkce
function after_php(DOM) {
  for (const id in DOM) {
    let elem= jQuery(`#${id}`),
        value= DOM[id];
    if (!elem.length) 
      alert(`chyba programu - '${id}' je neznámé id`);
    if (!Array.isArray(value)) value= [value];
    for (let i = 0; i < value.length; i++) {
      let val = value[i];
      switch (val) {
        case 'hide': elem.hide(); break;
        case 'show': elem.show(); break;
        case 'disable': elem.prop('disabled', true); break;
        case 'enable': elem.prop('disabled', false); break;
        case 'ok': elem.addClass('chng_ok').removeClass('chng'); break;
        case 'ko': elem.addClass('chng').removeClass('chng_ok'); break;
        case 'empty': elem.empty(); break;
        case 'remove': elem.remove(); break;
        default: 
          if (elem.is('input')) 
            elem.val(val); 
          else 
            elem.html(val.charAt(0)=='+' 
              ? elem.html()+val.substring(1) 
              : val)
      }
    }
  }
}
// --------------------------------------------------------------------------------------------- ask
// ask(x,then): dotaz na server se jménem funkce po dokončení
function ask(x,then,arg) {
  var xx= x;
  jQuery.ajax({url:myself_url, data:x, method: 'POST',
    success: function(y) {
      if ( typeof(y)==='string' ) {
        error("Došlo k chybě 1 v komunikaci se serverem - '"+xx.cmd+"'");
        jQuery('#errorbox').show().html(y);
      }
      else if ( y.error ) {
        error("Došlo k chybě 2 v komunikaci se serverem - '"+y.error+"'");
        jQuery('#errorbox').show().html(y.error);
      }
      else if ( then ) {
        then.apply(undefined,[y,arg]);
      }
    },
    error: function(xhr) {
      error("Došlo k chybě 3 v komunikaci se serverem");
      jQuery('#errorbox').show().html(xhr.responseText);
    }
  })
}
// ------------------------------------------------------------------------------------------- error
function error(msg) {
//  alert(msg + " pokud napises na martin@smidek.eu pokusim se pomoci, Martin");
}
