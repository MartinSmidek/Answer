// uživatelské funkce aplikace Ans(w)er
// ================================================================================================= Ezer.Form
Ezer.Form.implement({
// ------------------------------------------------------------------------------- on_dblclk_copy_to
//fm: Form.on_dblclk_copy_to (goal)
// Pro elementy při DblClk zkopíruje hodnotu do stejnojmenného elementu formuláře goal
// (funguje pro field, edit)
  on_dblclk_copy_to: function(goal) {
    $each(this.part,function(field,id) {
      if ( (field instanceof Ezer.Field || field instanceof Ezer.Edit)
        && field.DOM_Input ) {
//                                                         Ezer.trace('*',id);
        field.DOM_Input.addEvents({
          dblclick: function(el) {
//                                                         Ezer.trace('*','DblClick '+this.id+'='+this.value);
            if ( field2= goal.part[this.id] ) {
              field2.set(this.value);
              field2.change();
            }
          }.bind(field)
        });
      }
    },this);
    return 1;
  },
// ----------------------------------------------------------------------------------------- copy_to
//fm: Form.copy_to (goal,tag)
// Pro všechny elementy s atributem tag zkopíruje hodnotu do stejnojmenného elementu formuláře goal
// (funguje pro field, edit)
  copy_to: function(goal,tag) {
    $each(this.part,function(field,id) {
      if ( field instanceof Ezer.Field || field instanceof Ezer.Edit ) {
        if ( (field2= goal.part[field.id]) && field.value && field.options.tag==tag ) {
          field2.set(field.value);
          field2.change();
        }
      }
    },this);
    return 1;
  },
// -------------------------------------------------------------------------------------- set_styles
//fm: Form.set_styles (styles[,tag])
// Na všechny elementy (je-li zadán 'tag', tak s atributem tag) aplikuje styly dané objektem 'styles'
// (funguje pro field, edit)
  set_styles: function(styles,tag) {
    $each(this.part,function(field,id) {
      if ( field instanceof Ezer.Field || field instanceof Ezer.Edit ) {
        if ( field.DOM_Input && (tag?field.options.tag==tag:true)  ) {
          field.DOM_Input.setStyles(styles);
        }
      }
    },this);
    return 1;
  },
// --------------------------------------------------------------------------------------- set_notes
//fm: Form.set_notes ([pairs,css])
// pairs :: { name:val, ...]
// zobrazí hodnoty 'val' přes elementy dané jménem 'name', aplikuje přitom styl zadaný 'css'
// bezparametrická funkce pouze vymaže přidané texty
  set_notes: function(pairs,css) {
    if ( this.notes ) {
      this.notes.each(function(note){note.destroy()});
      this.notes= [];
    }
    else {
      this.notes= [];
    }
    if ( pairs ) {
      $each(this.part,function(field,id) {
        if ( field.data && pairs[field.data.id] ) {
          this.notes.push(new Element('div',{'class':css,html:pairs[field.data.id],styles:{
            position:'absolute',left:field._l,top:field._t+(field._h||16)+2
          }}).inject(this.DOM_Block));
        }
      },this);
    }
    return 1;
  },
// --------------------------------------------------------------------------------------- load_json
//fm: Form.load_json (json_str,styles,tag)
// zobrazí v elementech form hodnoty předané v zakódovaném tvaru, který obsahuje barvu pozadí
// - hodnota je zadána buďto přímo (s defaultním pozadím 'white') nebo jako pole [hodnota,pozadí]
// - barvy jsou zadány stringem 'p1:c1,p2:c2,...' kde ci bude aplikováno podkud pozadí=pi
  load_json: function(json_str,styles,tag) {
    json_str= json_str.replace(/\n/,"\\n").replace(/\r/,"\\r");
    var obj= JSON.decode(json_str);
    // převod styles na pole
    if ( styles ) {
      var as= styles.split(',');
      styles= [];
      as.each(function(vs){
        var avs= vs.split(':');
        styles[avs[0]]= avs[1];
      })
    }
    var ok= 1, value, style= '';
    for (var ie in obj) {
      var elem= this.part[ie];
      // definuj jen elementy tohoto view s metodou set
      if ( !elem || !elem.set ) continue;
      if ( elem.options && elem.options.tag && elem.options.tag==tag ) {
        if ( typeof(obj[ie])=="string" ) {
          value= obj[ie];
          style= '';
        }
        else {
          value= obj[ie][0];
          style= styles ? styles[obj[ie][1]] : '';
        }
        if ( elem instanceof Ezer.Select )
          elem.key(value);
        else
          elem.set(value);
        // aplikace barvy
        if ( elem.DOM_Input )
          elem.DOM_Input.setStyle('background-color',style ? style : '#fff');
      }
    }
    return ok;
  }
});
// ================================================================================================= Ezer.View
Ezer.View.implement({
// --------------------------------------------------------------------------------------- real_json
// oprava metody View.json
//fm: View.real_json (str)
  real_json: function(str) {
    var res= 0;
    if ( str ) {
      // setter
      str= str.replace(/\n/,"\\n").replace(/\r/,"\\r");
      res= this.json(JSON.decode(str));
    }
    else {
      // getter
      res= JSON.encode(this.json());

    }
    return res;
  },
// --------------------------------------------------------------------------------------- load_json
//fm: View.load_json (json_str,styles)
// zobrazí v elementech view hodnoty předané v zakódovaném tvaru, který obsahuje barvu pozadí
// - hodnota je zadána buďto přímo (s defaultním pozadím 'white') nebo jako pole [hodnota,pozadí]
// - barvy jsou zadány stringem 'p1:c1,p2:c2,...' kde ci bude aplikováno podkud pozadí=pi
  load_json: function(json_str,styles) {
    json_str= json_str.replace(/\n/,"\\n").replace(/\r/,"\\r");
    var obj= JSON.decode(json_str);
    // převod styles na pole
    if ( styles ) {
      var as= styles.split(',');
      styles= [];
      as.each(function(vs){
        var avs= vs.split(':');
        styles[avs[0]]= avs[1];
      })
    }
    var ok= 1, value, style= '';
    for (var ie in obj) {
      var elem= this.owner.part[ie];
      // definuj jen elementy tohoto view s metodou set
      if ( !elem || elem.view!=this || !elem.set ) continue;
      if ( typeof(obj[ie])=="string" ) {
        value= obj[ie];
        style= '';
      }
      else {
        value= obj[ie][0];
        style= styles ? styles[obj[ie][1]] : '';
      }
      if ( elem instanceof Ezer.Select )
        elem.key(value);
      else
        elem.set(value);
      // aplikace barvy
      if ( elem.DOM_Input )
        elem.DOM_Input.setStyle('background-color',style ? style : '#fff');
    }
    return ok;
  },
// --------------------------------------------------------------------------------------- save_json
//fm: View.save_json (json_str,style)
// přebarví v elementech view pozadí změněných hodnot podle barvy dané řetězem p1:c1
  save_json: function(json_str,style) {
    json_str= json_str.replace(/\n/,"\\n").replace(/\r/,"\\r");
    var obj= JSON.decode(json_str);
    // převod styles na index a barvu
    var avs= style.split(':');
    var icolor= avs[0], color= avs[1];
    // projdi změněné elementy tohoto view
    $each(this.owner.part,function(field,id) {
      if ( field.changed && field.changed() && field.data && field._save && field.view==this ) {
        obj[id]= [field.value,icolor];
        field.DOM_Input.setStyle('background-color',color);
      }
    },this);
    var ret= JSON.encode(obj);
    return ret;
  },
// --------------------------------------------------------------------------------------- set_notes
//fm: View.set_notes ([pairs,css])
// pairs :: { name:val, ...]
// zobrazí hodnoty 'val' přes elementy dané jménem 'name', aplikuje přitom styl zadaný 'css'
// bezparametrická funkce pouze vymaže přidané texty
  set_notes: function(pairs,css) {
    if ( this.notes ) {
      this.notes.each(function(note){note.destroy()});
      this.notes= [];
    }
    else {
      this.notes= [];
    }
    if ( pairs ) {
      $each(this.owner.part,function(field,id) {
        if ( field.data && pairs[field.data.id] && field.view==this ) {
          this.notes.push(new Element('div',{'class':css,html:pairs[field.data.id],styles:{
            position:'absolute',left:field._l,top:field._t+(field._h||16)+2
          }}).inject(this.owner.DOM_Block));
        }
      },this);
    }
    return 1;
  }
});
// ================================================================================================= Google
// Ezer.obj= {};        -- je definováno v ezer.js
// ------------------------------------------------------------------------------------------------- google_maps
//ff: test.google_maps (fce,label[,options])
Ezer.obj.google_maps= {};                       // obsahuje: poly,mark
Ezer.fce.google_maps= function (fce,options) {
  ret= 1;
  switch (fce) {
  case 'mapa_cr':                                       // zobrazí prázdnou mapu ČR
    var stredCR= new google.maps.LatLng(49.8, 15.6);
    var g_options= {zoom:7, center:stredCR, mapTypeId:google.maps.MapTypeId.TERRAIN};
    Ezer.obj.google_maps.map= new google.maps.Map(options.label.DOM_Block,g_options);
    Ezer.obj.google_maps.poly= null;
    Ezer.obj.google_maps.mark= [];
    break;
  case 'mark_new':                                      // vymaže všechny markery
    if ( Ezer.obj.google_maps.mark )
      Ezer.obj.google_maps.mark.each(function(m){m.setMap(null)});
    Ezer.obj.google_maps.mark= [];
    break;
  case 'mark_put':                                      // přidá markery podle textu x,y;..
    options.mark.split(';').map(function(xy) {
      var x_y= xy.split(',');
      var ll= new google.maps.LatLng(x_y[0],x_y[1]);
      var map_opts= {position:ll,map:Ezer.obj.google_maps.map};
      if ( x_y[2] ) map_opts.title= x_y[2];     // přidá label
      Ezer.obj.google_maps.mark.push(new google.maps.Marker(map_opts));
//       Ezer.obj.google_maps.mark.push(
//         new google.maps.Marker({position:ll,map:Ezer.obj.google_maps.map}));
    });
    break;
  case 'poly_new':                                      // vymaže všechny polygony
    if ( Ezer.obj.google_maps.poly )
      Ezer.obj.google_maps.poly.setMap(null);
    Ezer.obj.google_maps.poly= null;
    break;
  case 'poly_spec':                                     // umožní editaci, je-li options.edit:1
    if ( Ezer.obj.google_maps.poly )
      Ezer.obj.google_maps.poly.setEditable(options.edit?true:false);
    break;
  case 'poly_put':                                      // přidá polygony podle textu x,y;..|..
    // transformace z textu na LatLng
    if ( typeof(options.poly)=='string' ) {
      options.poly.split('|').each(function(poly) {
        var coords= poly.split(';').map(function(xy) {
          var ll= xy.split(',');
          return new google.maps.LatLng(ll[0],ll[1]);
        });
        if ( Ezer.obj.google_maps.poly ) {
          // přidej k existující
          var paths= Ezer.obj.google_maps.poly.getPaths();
          paths.push(new google.maps.MVCArray(coords));
          Ezer.obj.google_maps.poly.setPaths(paths);
        }
        else {
          // vytvoř první
          Ezer.obj.google_maps.poly= new google.maps.Polygon({paths:coords,strokeColor:"#FF0000",
            strokeOpacity:0.8,strokeWeight:3,fillColor:"#FF0000",fillOpacity:0.35,editable:false});
        }
      });
      Ezer.obj.google_maps.poly.setMap(Ezer.obj.google_maps.map);
    }
    break;
  case 'poly_get':                                      // vrátí textovou formu polygonů x,y;..|..
    var ret= '', del= '';
    if ( Ezer.obj.google_maps.poly ) {
      var paths= Ezer.obj.google_maps.poly.getPaths();
      for (var n= 0; n < paths.length; n++) {
        var vertices= paths.getAt(n);
        for (var i= 0; i < vertices.length; i++) {
          ret+= del+vertices.getAt(i).toUrlValue();
          del= ';';
        }
        del= '|'
      }
    }
    break;
  default:
    Ezer.error("ve funkci google_maps byla použita neznámá podfunkce '"+fce+"'");
  }
  return ret;
}
// ================================================================================================= Ezer.Label
// ------------------------------------------------------------------------------------ image_filter
//fm: Label.image_filter (filter)
//      aplikuje zadaný filtr na obsažené obrázky, povolené hodnoty jsou: gray, sepia, sharpen
Ezer.Label.implement({
  image_filter: function(filter) {
    if ( !Ezer.canvas ) try {
      Ezer.canvas= fx.canvas();         // volání fce knihovny glfx.js
    } catch(e) { Ezer.canvas= null; };
    if ( Ezer.canvas && ['gray','sepia','sharpen'].contains(filter) ) {
      this.DOM_Block.getElements('img').each(function(image){
        var texture= Ezer.canvas.texture(image);
        var data= Ezer.canvas.draw(texture);
        switch (filter) {
        case 'gray':    data.hueSaturation(-1,-1); break;
        case 'sepia':   data.sepia(1);             break;
        case 'sharpen': var radius= 1, strength= 1;
                        data.unsharpMask(radius,strength);
                        break;
        }
        data.update();
        // replace the image with the canvas
        image.parentNode.insertBefore(Ezer.canvas, image);
        image.parentNode.removeChild(image);
      })
    }
    return 1;
  }
});
// ------------------------------------------------------------------------------------ img_filter
// pomocná funkce aplikuje zadaný filtr na obsažené obrázky, povolené hodnoty jsou: gray, sepia, sharpen
function img_filter (image,filter) {
  if ( !Ezer.canvas ) try {
    Ezer.canvas= fx.canvas();         // volání fce knihovny glfx.js
  } catch(e) { Ezer.canvas= null; };
  if ( Ezer.canvas && ['gray','sepia','sharpen'].contains(filter) ) {
    var radius, strength;
    var texture= Ezer.canvas.texture(image);
    var data= Ezer.canvas.draw(texture);
    switch (filter) {
    case 'sharpen':
      radius= arguments[2]||1;
      strength= arguments[3]||1;
      data.unsharpMask(radius,strength);
      break;
    case 'gray':
      data.hueSaturation(-1,-1);
      break;
    case 'sepia':
      data.sepia(1);
      break;
    }
    data.update();
    // replace the image with the canvas
    if ( image.parentNode ) {
      image.parentNode.insertBefore(Ezer.canvas, image);
      image.parentNode.removeChild(image);
    }
  }
  return 1;
}
// ================================================================================================= Ezer.fce
// ------------------------------------------------------------------------------------------------- stack_forms
//ff: db.stack_forms (form,tag_list[,space=0[,smer='down']])
//      přeskládá formulář podle seznamu tagů, navrátí výslednou výšku, prokládá danou mezerou
//      rovná podle smer - down:shora dolů | up:zdola nahoru
//s: ys
Ezer.fce.stack_forms= function (form,tag_list,space,smer) {
  space= space || 0;
  smer= smer || 'down';
  var sum_h= 0;
  var tags= tag_list.split(',');
  if ( smer=='down' ) {
    for(var i= 0; i<tags.length; i++) {
      var tag= tags[i];
      form.display(1,tag);
      var bounds= form.property({down:sum_h,return:'bounds'},tag);
      sum_h+= bounds._h ? bounds._h + space : 0;
    }
  }
  else if ( smer=='up' ) {
    var h, top= [];
    top[0]= form.options._h;
    for(var i= 0; i<tags.length; i++) {
      var bounds= form.property({return:'bounds'},tags[i]);
      sum_h+= h= bounds._h ? bounds._h + space : 0;
      top[i+1]= top[i]+h;
    }
    for(var i= 0; i<tags.length; i++) {
      var tag= tags[i];
      form.display(1,tag);
      form.property({down:top[i]-sum_h},tag);
    }
  }
  return sum_h;
}
// ------------------------------------------------------------------------------------------------- rc2roky
//ff: ys.rc2roky (rodcis)
//      vrací věk, zjištěný z rodného čísla
//s: ys
Ezer.fce.rc2roky= function (rc) {
  var roku= '';
  if ( rc ) {
    var y, m, d;
    match= rc.match(/^([0-9]{2})([0-9]{2})([0-9]{2})/);
    if ( match ) {
      y= (match[1] >= 12 ? "19" : "20") + match[1];
      m= match[2] % 50;
      m= m ? m : 1;
      d= match[3];
      d= d ? d : 1;
    }
    if ( y ) {
      var now= new Date();
      var nar= new Date(y,m,d);
//       roku= now.getFullYear() - y;
      roku= Math.floor((now-nar)/(1000*60*60*24*365.2425));
    }
  }
  return roku;
};
// ------------------------------------------------------------------------------------------------- roku
//ff: ys.roku (dat)
//      vrací zaokrouhlený počet roku uplynulých od daného data, první rok počítá i načatý
//      uvedený na 1 desetinné místo pro věk>20
//s: ys
Ezer.fce.roku= function (dat,rok) {
  var roku= '', now= new Date();
  if ( dat ) {
    var m= dat.split('.');
    if ( m.length==3 ) {
      var nar= new Date(m[2],m[1]-1,m[0]);
      roku= (now-nar)/(1000*60*60*24*365.2425);
      roku= roku<10 ? Math.round(10*roku)/10 : Math.round(roku);
      if ( !roku ) roku= 1;       // první rok počítej i načatý
    }
    else if ( dat=='0000-00-00' ) {
      roku= '';
    }
    else {
      m= dat.split('-');        // sql-formát
      if ( m.length==3 ) {
        var nar= new Date(m[0],m[1]-1,m[2]);
        roku= (now-nar)/(1000*60*60*24*365.2425);
        // do 20 let desetiny
        roku= roku<20 ? roku.toFixed(1) : roku.toInt();
        if ( !roku ) roku= 0.1;       // první rok počítej i načatý
      }
    }
  }
  else if ( rok ) {
    roku= now.getFullYear() - rok;
    if ( !roku ) roku= 1;       // první rok počítej i načatý
  }
  return roku;
};
// ------------------------------------------------------------------------------------------------- roku
//ff: ys.roku_k (dat[,kdatu=now])
//      vrací počet roku uplynulých od daného data do daného data (neni-li uvedeno tak od běžného)
//s: ys
Ezer.fce.roku_k= function (dat,kdatu) {
  var str2date= function(s) {
    var m= s.split('.');      // formát d.m.Y
    if ( m.length==3 )
      d= new Date(m[2],m[1]-1,m[0]);
    else {
      m= s.split('-');        // sql-formát
      d= m.length==3 ? new Date(m[0],m[1]-1,m[2]) : 0;
    }
    return d;
  }
  if ( kdatu==undefined ) kdatu= new Date();
  var roku= '';
  var k= str2date(kdatu), d= str2date(dat);
  if ( d && k ) {
    // přibližně
    roku= (k-d)/(1000*60*60*24*365.2425);
    // přesně
    var kd= k.getDate(),  dd= d.getDate(),
        km= k.getMonth(), dm= d.getMonth(),
        ky= k.getYear(),  dy= d.getYear();
    roku= (km<dm || (km==dm && kd<dd)) ? ky-dy-1 : ky-dy;
  }
  return roku;
};
// ------------------------------------------------------------------------------------------------- set_css_changed
//ff: ys.set_css_changed (cases,css[,chngs])
// pokud je definováno chngs
//   odstraní css v daných formulářích a poté je obnoví u změněných dat podle pole
//   cases= [[form,table_id,key],...]    form se může opakovat pro různé table_id+key
//   chngs= [[table_id,key,field],...]
// pokud není definováno chngs
//   cases= [form,...]  seznam formulářů, ve kterých má být odstraněné css
//s: ys
Ezer.obj.set_css_changed= null;
Ezer.fce.set_css_changed= function (cases,css,chngs) {
  // vymazání starých poznámek
  if ( Ezer.obj.set_css_changed ) {
    Ezer.obj.set_css_changed.each(function(note){note.destroy()});
    Ezer.obj.set_css_changed= null;
  }
  Ezer.obj.set_css_changed= [];
  // vymazání css z pole formulářů
  var forms;
  if ( chngs ) {
    forms= [];
    for (ci= 0; ci<cases.length; ci++) {
        forms.push(cases[ci][0]);
    }
  }
  else {
    forms= cases;
  }
  // zrušení css ve formuláři
  for (fi= 0; fi<forms.length; fi++) {
    form= forms[fi].type=='var' ? forms[fi].value : forms[0];
    for (var pi in form.part) {
      var p= form.part[pi];
      if ( p.data ) {
        if ( p instanceof Ezer.Elem && p.DOM_Block ) {
          p.DOM_Block.removeClass(css);
        }
      }
    }
  }
  // pokud byla změna, označ ji
  if ( chngs ) {
    for (ci= 0; ci<cases.length; ci++) {
      var form= cases[ci][0].type=='var' ? cases[ci][0].value : cases[ci][0],
          table= cases[ci][1],
          key= cases[ci][2];
      // aplikace css podle změn
      for (di= 0; di<chngs.length; di++) {
        var t= chngs[di][0],    // tabulka změněné vět
            k= chngs[di][1];    // s klíčem
        // pokud je klíč shodný s aktivním
        if ( key==k ) {
          var f= chngs[di][2],  // změněné table.field
              w= chngs[di][3],  // pachatel
              d= chngs[di][4];  // čas změny
          // projdeme elementy formuláře
          for (var pi in form.part) {
            var p= form.part[pi];
            // a pro elementy
            if ( p instanceof Ezer.Elem && p.DOM_Block && p.data ) {
              // pokud data=table.field a klíč
              if ( p.data.id==f && p.table && p.table.id==table ) {
                // přidáme css
                p.DOM_Block.addClass(css);
                Ezer.obj.set_css_changed.push(
                  new Element('span',{'class':css,html:w,title:d,styles:{
                    left:p._l,top:p._t+(p._h||16)-1
                }}).inject(p.owner.DOM_Block));
              }
            }
          }
        }
      }
    }
  }
  return 1;
};
