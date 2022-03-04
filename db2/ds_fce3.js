/* global Ezer */

// uživatelské funkce aplikace DS
// ------------------------------------------------------------------------------------------------- form_set
//ff: ds.form_set (form,fields)
//      nastaví hodnoty polí ve formuláři
//s: ds
function form_set(form,fields) {
  for ( let fid in fields ) {
    Ezer.assert(form.part[fid] && form.part[fid].set,"form_set: formulář "+form.id+" nemá položku "+fid);
    form.part[fid].set(fields[fid]);
  }
  return true;
};
Ezer.fce.form_set= form_set; 
// ------------------------------------------------------------------------------------------------- rooms_check
//ff: ds.rooms_check (rooms,form,prefix)
//      nastaví hodnoty checkboxů prefix_<n> daného formuláře na 1 pokud se <n> vyskytuje v romms
//s: ds
function rooms_check(rooms,form,prefix) {
  // vynuluj vše
  for (let id in form.part) {
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
Ezer.fce.rooms_check= rooms_check;
// ------------------------------------------------------------------------------------------------- check_rooms
//ff: ds.check_rooms (rooms,form,prefix)
//      nastaví hodnoty checkboxů prefix_<n> daného formuláře na 1 pokud se <n> vyskytuje v romms
//s: ds
Ezer.fce.check_rooms= check_rooms;
function check_rooms(rooms,form,prefix) {
  var list= rooms.get().split(','), change= false;
  for (let id in form.part) {
    var elem= form.part[id];
    if ( elem.type=='check' && id.substr(0,prefix.length)==prefix ) {
      // checkbox označuje číslo pokoje
      let room= id.substr(prefix.length),
          wanted= elem.get(),
          includes= list.indexOf(room);
      if ( wanted && includes==-1 ) {
        list.push(room);
        change= true;
      }
      else if ( !wanted && includes>=0 ) {
        list.splice(includes,1);
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
