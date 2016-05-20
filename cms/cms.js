// uživatelské funkce aplikace Ezer/CMS
// ---------------------------------------------------------------------------------------------- go
// předá CMS info na kterou stránku webu přepnout
function go(href) {
  var url= href.split('page=');
  url= url.length==2 ? url[1] : url[0];
  url= url.split('#');
  Ezer.run.$.part.p._call(0,'cms_go',url[0])
};


// ------------------------------------------------------------------------------------ history_push
function history_push(href,checks) {
  var ref='', named, check;
  checks.split(',').each(function(check) {
    named= $$('input[name^="'+check+'"]');
    named.each(function(el) {
      if ( el.checked )
        ref+= (ref?',':'')+el.value;
    });
  });
  var url= href.split('page=');
  go(url[1]+'!'+ref);
}
// ---------------------------------------------------------------------------------------- vytvorit
function vytvorit(typ,id) {
  Ezer.run.$.part.p._call(0,'vytvorit',typ,id);
}
// ----------------------------------------------------------------------------------------- opravit
function opravit(typ,id) {
  Ezer.run.$.part.p._call(0,'opravit',typ,id);
}
// ------------------------------------------------------------------------------------------ zrusit
function zrusit(typ,id) {
  Ezer.run.$.part.p._call(0,'zrusit',typ,id);
}
