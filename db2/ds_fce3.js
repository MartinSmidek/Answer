/* global Ezer,Block */

// uživatelské funkce aplikace DS
// --------------------------------------------------------------------------------------------- cmp
// porovná texty a vrací 1,0,-1 pro > = <
function cmp(a,b) {
  return a>b ? 1 : (a<b ? -1 : 0);
}
// ----------------------------------------------------------------------------- set_elem_backround
// zavolá funkci umístěnou v main.menu a její hodnotu předá funkci this_fce
// _this je blok obsahující funkci this_fce
function set_elem_backround(elem,style) {
  jQuery(elem.DOM_Input).css(style);
}
// ---------------------------------------------------------------------------------- call_root_func
// zavolá funkci umístěnou v main.menu a její hodnotu předá funkci this_fce
// _this je blok obsahující funkci this_fce
function call_block_method(block,method) {
  if (block instanceof Block)
    block._call('',method);
}
// ---------------------------------------------------------------------------------- call_root_func
// zavolá funkci umístěnou v main.menu a její hodnotu předá funkci this_fce
// _this je blok obsahující funkci this_fce
function call_root_func(root_fce,_this,this_fce) {
  let elem= _this;
  while (elem.type!=='menu.main') {
    elem= elem.owner;
  }
  new Eval([{o:'c',i:root_fce,a:0,s:''}],elem,[],root_fce,{
    args:[_this,this_fce],fce:
      function(_this,then){
        new Eval([{o:'c',i:then,a:1,s:''}],_this,[this.stack[0]],then);
      }
    }
  );
}
// ---------------------------------------------------------------------------------- call_root_func
// zavolá funkci umístěnou v main.menu a její hodnotu předá funkci this_fce
// _this je blok obsahující funkci this_fce
// pars je pole argumentů 
function call_root_func_par(root_fce,par,_this,this_fce) {
  let elem= _this;
  while (elem.type!=='menu.main') {
    elem= elem.owner;
  }
  new Eval([{o:'c',i:root_fce,a:par.length,s:''}],elem,par,root_fce,{
    args:[_this,this_fce],fce:
      function(_this,then){
        new Eval([{o:'c',i:then,a:1,s:''}],_this,[this.stack[0]],then);
      }
    }
  );
}
// ---------------------------------------------------------------------------------------- form_set
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
// ----------------------------------------------------------------------------------- dum rooms_get
//ff: ds.dum_rooms_get (form,prefix)
//      vrátí seznam zaškrtnutých pokojů
//s: ds
function dum_rooms_get(form,prefix) {
  // přečti pokoje s hodnotou 1
  let rooms= '', del= '';
  for (let id in form.part) {
    var elem= form.part[id];
    if ( elem.type=='check' && id.substr(0,prefix.length)==prefix ) {
      if (elem.get()) {
        let room= elem._id.substr(prefix.length);
        rooms+= del+room;
        del= ',';
      }
    }
  }
  return rooms;
};
// ------------------------------------------------------------------------------------- rooms_check
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
  let rms= typeof(rooms)=='string' ? rooms : rooms.get();
  for (let room of rms.split(',')) {
    var id= prefix+room;
    if ( form.part[id] && form.part[id].type=='check' )
      form.part[id].set(1);
  }
  return true;
};
Ezer.fce.rooms_check= rooms_check;
// ------------------------------------------------------------------------------------- check_rooms
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
