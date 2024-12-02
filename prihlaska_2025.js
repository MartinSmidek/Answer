/* global jQuery */
/*
  (c) 2025 Martin Smidek <martin@smidek.eu>
   
  pilotní verze online přihlašování pro YMCA Setkání (jen obnovy a LK MS YS)
  debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

 */

var myself_url= "http://answer-test.bean:8080/prihlaska_2025.php";

function start() {
}
// ===========================================================================================> AJAX
// --------------------------------------------------------------------------------------------- php
function php(pars) {
  let np= pars ? pars.split(/,/) : [], 
      fce= event.type=='load' ? 'start' : event.target.id,
      x= {cmd:fce};
  for (let i= 0; i<np.length; i++) {
    x[np[i]]= jQuery(`#${np[i]}`).val();
  }
  jQuery('#errorbox').hide().html('');
  jQuery('#mailbox').hide().html('');
  ask(x,after_php);
}
// --------------------------------------------------------------------------------------------- php
function php2(name_pars) {
  let np= name_pars.split(/,/), 
      fce= np[0],
      x= {cmd:fce};
  for (let i= 1; i<np.length; i++) {
    x[np[i]]= jQuery(`#${np[i]}`).val();
  }
  jQuery('#errorbox').hide().html('');
  jQuery('#mailbox').hide().html('');
  ask(x,after_php);
}
// --------------------------------------------------------------------------------------- after php
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