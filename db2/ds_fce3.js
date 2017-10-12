// uživatelské funkce aplikace DS
// ------------------------------------------------------------------------------------------------- form_set
//ff: ds.form_set (form,fields)
//      nastaví hodnoty polí ve formuláři
//s: ds
Ezer.fce.form_set= function (form,fields) {
  for ( fid in fields ) {
    Ezer.assert(form.part[fid] && form.part[fid].set,"form_set: formulář "+form.id+" nemá položku "+fid);
    form.part[fid].set(fields[fid]);
  }
  return true;
};
// ------------------------------------------------------------------------------------------------- rooms_check
//ff: ds.rooms_check (rooms,form,prefix)
//      nastaví hodnoty checkboxů prefix_<n> daného formuláře na 1 pokud se <n> vyskytuje v romms
//s: ds
Ezer.fce.rooms_check= function (rooms,form,prefix) {
  // vynuluj vše
  for (id in form.part) {
    var elem= form.part[id];
    if ( elem.type=='check' && id.substr(0,prefix.length)==prefix ) {
      elem.set(0);
    }
  }
  // nastav aktuální
  for (let room of rooms.get().split(',')) {
    var id= prefix+room;
    if ( form.part[id] && form.part[id].type=='check' )
      form.part[id].set(1);
  }
  return true;
};
// ------------------------------------------------------------------------------------------------- check_rooms
//ff: ds.check_rooms (rooms,form,prefix)
//      nastaví hodnoty checkboxů prefix_<n> daného formuláře na 1 pokud se <n> vyskytuje v romms
//s: ds
Ezer.fce.check_rooms= function (rooms,form,prefix) {
  var list= rooms.get().split(','), change= false;
  for (id in form.part) {
    var elem= form.part[id];
    if ( elem.type=='check' && id.substr(0,prefix.length)==prefix ) {
      // checkbox označuje číslo pokoje
      var room= id.substr(prefix.length);
      var wanted= elem.get();
      if ( wanted && !list.contains(room,',') ) {
        list.push(room);
        change= true;
      }
      else if ( !wanted && list.contains(room,',') ) {
        list.erase(room);
        change= true;
      }
      // odstraň příznak změny
      elem.plain();
    }
    if ( change ) {
      rooms.set(list.toString());
      rooms.change();
    }
  }
  return true;
};
