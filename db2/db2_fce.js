// uživatelské funkce aplikace Ans(w)er
// =========================================================================================> funkce
// --------------------------------------------------------------------------------------- personify
// upraví vzhled aplikace pro uživatele s omezenými datovými právy
function personify(access) {
  if ( access==1 ) {
    $$('#appl span')[0].setProperty('text',"Answer Setkání");
    Asset.css("skins/ck/ck.ezer.css");
  }
  else if ( access==2 ) {
    $$('#appl span')[0].setProperty('text',"Answer Familia");
    Asset.css("skins/ch/ch.ezer.css");
  }
  return 1;
}
// ---------------------------------------------------------------------------------------- je_1_2_5
// výběr správného tvaru slova podle množství a tabulky tvarů pro 1,2-4,5 a více
// např. je_1_2_5(dosp,"dospělý,dospělí,dospělých")
function je_1_2_5(kolik,tvary) {
  tvar= tvary.split(',');
  return kolik>4 ? tvar[2] : (
         kolik>1 ? tvar[1] : (
         kolik>0 ? tvar[0] : tvar[2]));
}
// ======================================================================================> Ezer.Form
Ezer.Form.implement({
// ------------------------------------------------------------------------------- on_dblclk_copy_to
//fm: Form.on_dblclk_copy_to (goal)
// Pro elementy při DblClk zkopíruje hodnotu do stejnojmenného elementu formuláře goal
// (funguje pro field, edit)
  on_dblclk_copy_to: function(goal) {
    var form= this instanceof Ezer.Var ? this.value : this;
    goal= goal instanceof Ezer.Var ? goal.value : goal;
    $each(form.part,function(field,id) {
      if ( (field instanceof Ezer.Field || field instanceof Ezer.Edit || field instanceof Ezer.Select)
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
    });
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
// --------------------------------------------------------------------------------------- set_notes
//fm: Form.set_helps (pairs,css1,css2)
// pairs :: { name:val, ...]
// zobrazí hodnoty 'val' jako title pro elementy s data.id=name a přidá css resp. css2
// (css2, pokud val začíná vykřičníkem)
// bezparametrická funkce vymaže všechny definované title a přidaná css
  set_helps: function(pairs,css1,css2) {
    if ( pairs ) {
      $each(this.part,function(field,id) {
        if ( field.data && pairs[field.data.id] ) {
          var title= pairs[field.data.id];
          var css= title[0]=='!' ? css2 : css1;
          if ( field instanceof Ezer.Radio || field instanceof Ezer.Check ) {
            field.DOM_Block.set('title',title||'?');
            field.DOM_Block.addClass(css);
          }
          else if ( field instanceof Ezer.Elem && field.DOM_Input ) {
            field.DOM_Input.set('title',title||'?');
            field.DOM_Input.addClass(css);
          }
        }
      },this);
    }
    else {
      $each(this.part,function(field,id) {
        if ( field instanceof Ezer.Radio || field instanceof Ezer.Check ) {
          field.DOM_Block.set('title','');
          field.DOM_Block.removeClass(css1).removeClass(css2);
        }
        else if ( field instanceof Ezer.Elem && field.DOM_Input ) {
          field.DOM_Input.set('title','');
          field.DOM_Input.removeClass(css1).removeClass(css2);
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
// ======================================================================================> Ezer.View
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
// =====================================================================================> Ezer.Label
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
// =======================================================================================> Ezer.fce
// ------------------------------------------------------------------------------------- stack_forms
//ff: db.stack_forms (form,tag_list[,space=0[,smer='down']])
//      přeskládá formulář podle seznamu tagů, navrátí výslednou výšku, prokládá danou mezerou;
//      rovná podle smer - down:shora dolů | up:zdola nahoru;
//      prízdné tagy ignoruje
//s: ys
Ezer.fce.stack_forms= function (form,tag_list,space,smer) {
  space= space || 0;
  smer= smer || 'down';
  var sum_h= 0;
  var tags= tag_list.split(',');
  if ( smer=='down' ) {
    for(var i= 0; i<tags.length; i++) if ( tags[i] ) {
      var tag= tags[i];
      form.display(1,tag);
      var bounds= form.property({down:sum_h,return:'bounds'},tag);
      sum_h+= bounds._t==undefined ? 0
        : ((!i ? bounds._t : 0) + (bounds._h ? bounds._h + space : 0));
    }
  }
  else if ( smer=='up' ) {
    var h, top= [];
    top[0]= form.options._h;
    for(var i= 0; i<tags.length; i++) if ( tags[i] ) {
      var bounds= form.property({return:'bounds'},tags[i]);
      sum_h+= h= bounds._h ? bounds._h + space : 0;
      top[i+1]= top[i]+h;
    }
    for(var i= 0; i<tags.length; i++) if ( tags[i] ) {
      var tag= tags[i];
      form.display(1,tag);
      form.property({down:top[i]-sum_h},tag);
    }
  }
  return sum_h;
}
// ----------------------------------------------------------------------------------------- rc2roky
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
// -------------------------------------------------------------------------------------------- roku
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
// ------------------------------------------------------------------------------------------ roku_k
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
// --------------------------------------------------------------------------------- set_css_changed
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