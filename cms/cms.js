// uživatelské funkce aplikace Ezer/CMS
// ---------------------------------------------------------------------------------------------- go
// předá CMS info na kterou stránku webu přepnout
function go(href) {
  var url= href.split('page=');
  url= url.length==2 ? url[1] : url[0];
  Ezer.run.$.part.p._call(0,'cms_go',url)
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
