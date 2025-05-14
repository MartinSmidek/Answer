// uživatelské funkce aplikace Ans(w)er - varianta s jQuery
/* global Ezer, Form, Var, Field, FieldList, Edit, Select, View, Label, Highcharts */ // pro práci s Netbeans
"use strict";
// ====================================================================================> highcharts
// --------------------------------------------------------------------------------- highcharts load
// zavede dynamicky potřebné moduly highcharts
function highcharts_load() {
  if (typeof Highcharts=="undefined") {
    let code= `../ezer${Ezer.version}/client/licensed/highcharts/code`;
    for (const modul of ['highcharts.js','highcharts-more.js',
        'modules/heatmap.js','modules/exporting.js','modules/export-data.js',
        'modules/accessibility.js']) {
      jQuery('head').append(`<script type="text/javascript" src="${code}/${modul}"></script>`);
    }
  }
}
// -------------------------------------------------------------------------------- highcharts clear
// vymaže chart
function highcharts_clear(container) {
  if (container==undefined) container= 'container';
  jQuery('#'+container).html('<br><br>nelze zobrazit ...');
}
// --------------------------------------------------------------------------------- highcharts show
// par: series:[{name:str,list:str},...]
function highcharts_show(par,container) {
  // test zavedení modulu
  if (container==undefined) container= 'container';
  if (typeof Highcharts=="undefined") {
    jQuery('#'+container).html("není zaveden modul highcharts - použij &highcharts=1");
    return;
  }
  // rozeznání typu grafu
  let chart= {};
  switch (par['chart']) {
    case 'pie': { // --------------------------------------------------- pie
      chart= {
        chart: {type: 'pie'},
        title: {text: ''},
        plotOptions: {pie:{showInLegend:true, 
//            dataLabels:{enabled: false}, 
            dataLabels: {
              enabled: true,
              distance: -50,
              format: '{point.percentage:.1f} %',
            },
            startAngle: 90}},
        series: [{name:'Values', data:[]}],
        legend: {
            layout: 'vertical',
            align: 'right',
            verticalAlign: 'top',
            y: 50,
            padding: 3,
            itemMarginTop: 5,
            itemMarginBottom: 5,
            itemStyle: { lineHeight: '14px' }
        }
      };
      // doplnění předanými daty
      for (const p in par) {
        switch (p) {
          case 'chart':
            chart.chart= {};
            chart.chart.type= par[p];
            break;
          case 'series': // series= [{name, data:[{name,y},...]}]
            for (let name_value of par[p][0].data) {
              name_value.y= Number(name_value.y);
              chart.series[0].data.push(name_value);
            }
            break;
          case 'data': // string čárkami oddělené name:hodnota
            let name, value;
            for (let name_value of par[p].split(',')) {
              [name, value]= name_value.split(':');
              chart.series[0].data.push({name:name,y:Number(value)});
            }
            break;
          case 'title':
          case 'subtitle':
            chart[p].text= par[p];
            break;
          default:
            chart[p]= par[p];
            break;
        }
      }
      break;
    }
    case 'heatmap': { // ----------------------------------------------- heatmap
      chart= {
        chart: {type: 'heatmap',marginTop: 40,marginBottom: 80,plotBorderWidth: 1},
        title: {text: ''},
        xAxis: {categories: []},
        yAxis: {categories: [],title: null},
        colorAxis: {min: 0,minColor: '#FFFFFF',maxColor: '#aaa'},
        legend: {align: 'right',layout: 'vertical',margin: 0,verticalAlign: 'top',y: 25,symbolHeight: 280},
        tooltip: {
          formatter: function () { if (chart.tooltip.data) {
            return chart.tooltip.data[this.point.x][this.point.y];
          }}
        },
        series: [{name: '',borderWidth: 1,data: [],
            dataLabels: {enabled: true,color: '#000000'}
        }],
      };
      // doplnění předanými daty
      chart.title.text= par['title_text'];
      chart.colorAxis.maxColor= par['colorAxis_maxColor'];
      chart.xAxis.categories= par['xAxis_categories'];
      chart.yAxis.categories= par['yAxis_categories'];
      chart.series[0].data= par['series_0_data'];
      if (par['tooltip_data']) {
        chart.tooltip.data= par['tooltip_data'];
      }
      break;
    }
    default:  {      // ----------------------------------------------- bar
      chart= {
        exporting: {showTable: false},    
        title: {text: ' '},
        subtitle: {text: ''},
        legend: {layout: 'vertical', align: 'right', verticalAlign: 'middle'},
        series: [],
        responsive: {
          rules: [{
            condition: {
              maxWidth: 500},
              chartOptions: 
                {legend: {layout: 'horizontal',align: 'center',verticalAlign: 'bottom'}
              }
          }]
        }
      };
      // parametrizace
      for (const p in par) {
        switch (p) {
          case 'chart':
            chart.chart= {};
            chart.chart.type= par[p];
            break;
          case 'series_done':
            chart.series= par[p];
            break;
          case 'series':
            for (const serie of par[p]) {
              if (serie.type=='line') {
                chart[p].push({name:serie.name, type:'line', data:serie.data});
              }
              else {
                let data= typeof(serie.data)=='string' ? serie.data.split(',').map(x=>Number(x)) : (
                  Array.isArray(serie.data) ? serie.data : []);
                let s= {name:serie.name, data:data};
//                let s= {name:serie.name, data:data.map(x=>Number(x))};
                if (serie.color) s.color= serie.color;
                if (serie.dashStyle) s.dashStyle= serie.dashStyle;
                if (serie.marker) s.marker= serie.marker;
                chart[p].push(s);
              }
            }
            break;
          case 'title':
          case 'subtitle':
            chart[p].text= par[p];
            break;
          default:
            chart[p]= par[p];
            break;
        }
      }
      break;
    }
  }
  // zobrazení
  jQuery('div.highcharts-data-table').remove();
  Highcharts.chart(container, chart);
}
// ------------------------------------------------------------------------------ highcharts simple
function highcharts_simple() {
  if (typeof Highcharts=="undefined") {
    jQuery('#container').html("není zaveden modul highcharts - použij &highcharts=1");
    return;
  }

  Highcharts.chart('container', {
    exporting: {showTable: false},    
    title: {text: 'Solar Employment Growth by Sector, 2010-2016'},
    subtitle: {text: 'Source: thesolarfoundation.com'},
    yAxis: {
      title: {text: 'Number of Employees'}
    },
    xAxis: {
      accessibility: {rangeDescription: 'Range: 2010 to 2017'}
    },
    legend: {layout: 'vertical', align: 'right', verticalAlign: 'middle'},
    plotOptions: {
      series: {
        label: {connectorAllowed: false},
        pointStart: 2010
      }
    },

    series: [{
      name: 'Installation',
      data: [43934, 52503, 57177, 69658, 97031, 119931, 137133, 154175]
    }, {
      name: 'Manufacturing',
      data: [24916, 24064, 29742, 29851, 32490, 30282, 38121, 40434]
    }, {
      name: 'Sales & Distribution',
      data: [11744, 17722, 16005, 19771, 20185, 24377, 32147, 39387]
    }, {
      name: 'Project Development',
      data: [null, null, 7988, 12169, 15112, 22452, 34400, 34227]
    }, {
      name: 'Other',
      data: [12908, 5948, 8105, 11248, 8989, 11816, 18274, 18111]
    }],

    responsive: {
      rules: [{
      condition: {
        maxWidth: 500},
        chartOptions: 
          {legend: {layout: 'horizontal',align: 'center',verticalAlign: 'bottom'}
        }
      }]
    }
  });
}
// =========================================================================================> funkce
// -------------------------------------------------------------------------------------- in selects
// zjistí, zda hodnota je obsažena v nabídce Selects
function in_selects(select,value) {
  let ok= 0;
  for (let key in select.Items) {
    if (select.Items[key]==value) { ok= 1; break; }
  }
  return ok;
}
// --------------------------------------------------------------------------------------- set style
// odstraní styl předaného elementu
function removeClass(elem,css) {
  if (elem.DOM_Block) {
    jQuery(elem.DOM_Block).find(`td.${css}`).removeClass(css);
  }
  return 1;
}
// --------------------------------------------------------------------------------------- set style
// přidá styl danému browse.show 
function browseShow_addClass(show,i,css) {
  if (show.DOM_cell[i]) {
    jQuery(show.DOM_cell[i]).addClass(css);
  }
  return 1;
}
// ---------------------------------------------------------------------------------------- get type
// vrátí atribut type
function get_type(elem) {
  return elem.type;
}
// --------------------------------------------------------------------------------------- match tag
// vrátí 1 pokud elem.tag vyhovuje regulárnímu výrazu
function match_tag(elem,regexpr) {
  let ok= 0;
  if (elem.options!==undefined && elem.options.tag!==undefined) {
    let re= new RegExp(regexpr);
    ok= re.test(elem.options.tag) ? 1 : 0;
  }
  return ok;
}
// ---------------------------------------------------------------------------------------- add days
// funkce pro přičtení/odečtení 'ofset' dnů k danému datu.
function add_days(datum,ofset) {
//  let d= datum.split(/\.\s*/); 
//  let nd= new Date(d[2], d[1] - 1, d[0]); 
  let nd= new Date(Ezer.fce.date2sql(datum)); 
  nd.setDate(nd.getDate() + parseInt(ofset)); // Přidání/odebrání ofsetu dnů
  return nd.getDate() + "." + (nd.getMonth() + 1) + "." + nd.getFullYear();
}
// --------------------------------------------------------------------------------------- diff days
// funkce pro zjištění počtu dnů mezi dvěma daty
function diff_days(dflt,datum) {
//  let d1= dflt.split(/\.\s*/); 
//  let d2= datum.split(/\.\s*/); 
//  let dd1= new Date(d1[2], d1[1] - 1, d1[0]); 
//  let dd2= new Date(d2[2], d2[1] - 1, d2[0]); 
  let dd1= new Date(Ezer.fce.date2sql(dflt)); 
  let dd2= new Date(Ezer.fce.date2sql(datum)); 
  return Math.abs(Math.round((dd1 - dd2) / (1000 * 60 * 60 * 24)));
}
// ------------------------------------------------------------------------------------ between days
// je dotazovaný den mezi dvěma daty?
function between_days(day,start,end) {
  let d= new Date(Ezer.fce.date2sql(day));
  let s= new Date(Ezer.fce.date2sql(start));
  let e= new Date(Ezer.fce.date2sql(end));
  return d >= s && d <= e ? 1 : 0;
}
// --------------------------------------------------------------------------------------- set style
// nastaví styl předaného elementu
function set_style(elem,prop) {
  if (elem.DOM_Block) {
    let prop2= {};
    for (let p in prop) {
      prop2[p]= Number(prop[p]) ? Number(prop[p]) : prop[p];
    }
    jQuery(elem.DOM_Block).css(prop2);
  }
  return 1;
}
// -----------------------------------------------------------------------------==> . mrop map count
// funkce je svázaná s PHP fcí sta2_mrop_stat_map
// k celkem (celkem iniciovaných z ČR) se jak promile vyjádří zobrazení muži
// v mapě jsou muži a skupiny ... muž má title ve tvaru n:obec
function mrop_map_count(map,celkem) {
  let men= 0, groups= 0;
  for (let i in map.mark) {
    let x= map.mark[i];
    let n= x.title.split(':');
    switch (x.icon.scale) {
      case 8: men+= Number(n[0]); break;  
      case 12: groups++; break;  
    }
  }
  let pc= Math.round(100*men/celkem);
  return `je zobrazeno ${men} mužů tj. ${pc}% z ${celkem} a ${groups} skupin`;
}
// ----------------------------------------------------------------------------------- clipboard
// copy message to the clipboard
function clipboard_obj(sel) {
  let div= jQuery(sel),
      selection,
      range= document.createRange();
  // select element
  range.selectNodeContents(div.get(0));
  selection= window.getSelection();
  selection.removeAllRanges();
  selection.addRange(range);
  // lets copy element
  document.execCommand ("copy", false, null);
};
// -----------------------------------------------------------------------------------==> . ON login
Ezer.onlogin= function() {
  if ( Ezer.root=='db2' || Ezer.root=='dbt'  ) {
    var a1= Ezer.sys.user.access;
    var a2= Ezer.fce.get_cookie(Ezer.root+'_last_access',a1);
    var a= a1 & a2 ? a1 & a2 : a1;
    personify(a,Ezer.root=='dbt' ? '-TEST' : '');
  }
};
// ----------------------------------------------------------------------------------==> . personify
// upraví datová práva a vzhled aplikace
// před přihlášením:    každému (po přihlášení bude případně zredukováno)
// po přihlášení:       pokud má tato datová práva
function personify(access,test) {
//  var v= Ezer.version=='ezer3.1' ? "<sub>3</sub>" : '';
  test= test || '';
  var v= Ezer.version=='3.2' ? `<sub><small>${Ezer.version}</small></sub>` : '';
  var menu= jQuery('#access_menu'), body= jQuery(document.body);
  var orgs= ['','YMCA Setkání','YMCA Familia','obou organizací'];
  var tits= ['',`Answer${test}${v} Setkání`,`Answer${test}${v} Familia`,`Answer${test}${v} (společný)`];
  var sk=   test ? ['','tt','tt','tt'] : ['','ck','ch','db'];
  var off= function(e) {
    menu.css('display','none');
    body.off({click:off,contextmenu:off});
  };
  if ( access=='menu_on' ) {
    menu.css('display','block');
    setTimeout(function(){
      body.on({click:off,contextmenu:off});
    },1);
  }
  else {
    off();
    var access1= Ezer.sys.user && Ezer.sys.user.id_user
               ? Ezer.sys.user.has_access & access              // přihlášený - ověřeně
               : access;                                        // nepřihlášený - podle přání
    // u přihlášeného již víme skutečná práva tj. has_access
    // nepřihlášenému obarvíme přihlášení podle přání - po přihlášení se to ověří
    if ( access1 ) {
      // přepni aplikaci podle access a zapiš do cookie 'db2[_test]_last_access'
      Ezer.sys.user.access= access1;
      jQuery('#access').html(tits[access1]);
      DOM_change_skin(sk[access1]);
      Ezer.fce.set_cookie(Ezer.root+'_last_access',access1);
      // přihlášenému zavolej ezer-funkci v kořenu reaccess
      if ( Ezer.sys.user && Ezer.sys.user.id_user && Ezer.run && Ezer.run.$
           && Ezer.run.$.part && Ezer.run.$.part.db2 ) {
        Ezer.run.$.part.db2.callProc('reaccess',[orgs[access1]]);
      }
    }
    else {
      // uživatel nemá datové oprávnění
      Ezer.fce.alert("Nemáte oprávnění pracovat s daty "+orgs[access]);
    }
  }
  return 1;
}
// -----------------------------------------------------------------------------------==> . je_1_2_5
// výběr správného tvaru slova podle množství a tabulky tvarů pro 1,2-4,5 a více
// např. je_1_2_5(dosp,"dospělý,dospělí,dospělých")
function je_1_2_5(kolik,tvary) {
  let tvar= tvary.split(',');
  return kolik>4 ? tvar[2] : (
         kolik>1 ? tvar[1] : (
         kolik>0 ? tvar[0] : tvar[2]));
}
// -------------------------------------------------------------------------==> . browse_concat_clmn
// vrátí seznam hodnot daného sloupce
function browse_concat_clmn(browse,clmn_id) {
  var list='', del= '';
  for (var bi= 0; bi<browse.blen; bi++) {     // bi ukazuje do buf a keys
    list+= del+browse.buf[bi][clmn_id];
    del= ',';
  }
  return list;
}
// ======================================================================================> Ezer.Form
// ------------------------------------------------------------------------------- on_dblclk_copy_to
//fm: Form.on_dblclk_copy_to (goal)
// Pro elementy při DblClk zkopíruje hodnotu do stejnojmenného elementu formuláře goal
// (funguje pro field, edit)
Form.prototype.on_dblclk_copy_to= function(goal) {
  var form= this instanceof Var ? this.value : this;
  goal= goal instanceof Var ? goal.value : goal;
//   $each(form.part,function(field,id) {
  for (let id in form.part) { let field= form.part[id];
    if ( (field instanceof Field || field instanceof FieldList 
      || field instanceof Edit || field instanceof Select)
      && field.DOM_Input ) {
      field.DOM_Input
        .dblclick( function(el) {
          let field2= goal.part[this.id];
          if ( field2 ) {
            field2.set(this.value);
            field2.change();
          }
        }.bind(field));
    }
  }
  return 1;
};
// ----------------------------------------------------------------------------------------- copy_to
//fm: Form.copy_to (goal,tag)
// Pro všechny elementy s atributem tag zkopíruje hodnotu do stejnojmenného elementu formuláře goal
// (funguje pro field, edit)
Form.prototype.copy_to= function(goal,tag) {
//   $each(this.part,function(field,id) {
  for (let id in this.part) { let field= this.part[id];
    if ( field instanceof Field || field instanceof Edit ) {
      if ( (field2= goal.part[field.id]) && field.value && field.options.tag==tag ) {
        field2.set(field.value);
        field2.change();
      }
    }
  }
  return 1;
};
// -------------------------------------------------------------------------------------- set_styles
//fm: Form.set_styles (styles[,tag])
// Na všechny elementy (je-li zadán 'tag', tak s atributem tag) aplikuje styly dané objektem 'styles'
// (funguje pro field, edit)
Form.prototype.set_styles= function(styles,tag) {
//   $each(this.part,function(field,id) {
  for (let id in this.part) { let field= this.part[id];
    if ( field instanceof Field || field instanceof Edit ) {
      if ( field.DOM_Input && (tag?field.options.tag==tag:true)  ) {
        field.DOM_Input.css(styles);
      }
    }
  }
  return 1;
};
/*
// --------------------------------------------------------------------------------------- set_notes
//fm: Form.set_notes ([pairs,css])
// pairs :: { name:val, ...]
// zobrazí hodnoty 'val' přes elementy dané jménem 'name', aplikuje přitom styl zadaný 'css'
// bezparametrická funkce pouze vymaže přidané texty
// Form.prototype.set_notes= function(pairs,css) {
//   if ( this.notes ) {
//     this.notes.each(function(note){note.destroy();});
//     this.notes= [];
//   }
//   else {
//     this.notes= [];
//   }
//   if ( pairs ) {
// //     $each(this.part,function(field,id) {
//     for (let id in this.part) { let field= this.part[id];
//       if ( field.data && pairs[field.data.id] ) {
//         this.notes.push(new Element('div',{'class':css,html:pairs[field.data.id],styles:{
//           position:'absolute',left:field._l,top:field._t+(field._h||16)+2
//         }}).inject(this.DOM_Block));
//       }
//     }
//   }
//   return 1;
// };
*/
// --------------------------------------------------------------------------------------- set_notes
//fm: Form.set_helps (pairs,css1,css2)
// pairs :: { name:val, ...]
// zobrazí hodnoty 'val' jako title pro elementy s data.id=name a přidá css resp. css2
// (css2, pokud val začíná vykřičníkem)
// bezparametrická funkce vymaže všechny definované title a přidaná css
Form.prototype.set_helps= function(pairs,css1,css2) {
  if ( pairs ) {
//     $each(this.part,function(field,id) {
    for (let id in this.part) { let field= this.part[id];
      if ( field.data && pairs[field.data.id] ) {
        var title= pairs[field.data.id];
        var css= title[0]=='!' ? css2 : css1;
        if ( field instanceof Radio || field instanceof Check ) {
          field.DOM_Block.attr('title',title||'?');
          field.DOM_Block.addClass(css);
        }
        else if ( field instanceof Elem && field.DOM_Input ) {
          field.DOM_Input.attr('title',title||'?');
          field.DOM_Input.addClass(css);
        }
      }
    }
  }
  else {
//     $each(this.part,function(field,id) {
    for (let id in this.part) { let field= this.part[id];
      if ( field instanceof Radio || field instanceof Check ) {
        field.DOM_Block.attr('title','');
        field.DOM_Block.removeClass(css1).removeClass(css2);
      }
      else if ( field instanceof Elem && field.DOM_Input ) {
        field.DOM_Input.attr('title','');
        field.DOM_Input.removeClass(css1).removeClass(css2);
      }
    }
  }
  return 1;
};
// --------------------------------------------------------------------------------------- load_json
//fm: Form.load_json (json_str,styles,tag)
// zobrazí v elementech form hodnoty předané v zakódovaném tvaru, který obsahuje barvu pozadí
// - hodnota je zadána buďto přímo (s defaultním pozadím 'white') nebo jako pole [hodnota,pozadí]
// - barvy jsou zadány stringem 'p1:c1,p2:c2,...' kde ci bude aplikováno podkud pozadí=pi
Form.prototype.load_json= function(json_str,styles,tag) {
  json_str= json_str.replace(/\n/,"\\n").replace(/\r/,"\\r");
  var obj= JSON.decode(json_str);
  // převod styles na pole
  if ( styles ) {
    var as= styles.split(',');
    styles= [];
//     as.each(function(vs){
    for (let vs of as) {
      var avs= vs.split(':');
      styles[avs[0]]= avs[1];
    }
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
      if ( elem instanceof Select )
        elem.key(value);
      else
        elem.set(value);
      // aplikace barvy
      elem.DOM_Input.css('background-color',style ? style : '#fff');
    }
  }
  return ok;
};

// ======================================================================================> Ezer.View
// --------------------------------------------------------------------------------------- real_json
// oprava metody View.json
//fm: View.real_json (str)
View.prototype.real_json= function(str) {
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
};
// --------------------------------------------------------------------------------------- load_json
//fm: View.load_json (json_str,styles)
// zobrazí v elementech view hodnoty předané v zakódovaném tvaru, který obsahuje barvu pozadí
// - hodnota je zadána buďto přímo (s defaultním pozadím 'white') nebo jako pole [hodnota,pozadí]
// - barvy jsou zadány stringem 'p1:c1,p2:c2,...' kde ci bude aplikováno podkud pozadí=pi
View.prototype.load_json= function(json_str,styles) {
  json_str= json_str.replace(/\n/,"\\n").replace(/\r/,"\\r");
  var obj= JSON.decode(json_str);
  // převod styles na pole
  if ( styles ) {
    var as= styles.split(',');
    styles= [];
//     as.each(function(vs){
    for (let vs of as) {
      var avs= vs.split(':');
      styles[avs[0]]= avs[1];
    }
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
    if ( elem instanceof Select )
      elem.key(value);
    else
      elem.set(value);
    // aplikace barvy
    elem.DOM_Input.css('background-color',style ? style : '#fff');
  }
  return ok;
};
// --------------------------------------------------------------------------------------- save_json
//fm: View.save_json (json_str,style)
// přebarví v elementech view pozadí změněných hodnot podle barvy dané řetězem p1:c1
View.prototype.save_json= function(json_str,style) {
  json_str= json_str.replace(/\n/,"\\n").replace(/\r/,"\\r");
  var obj= JSON.decode(json_str);
  // převod styles na index a barvu
  var avs= style.split(':');
  var icolor= avs[0], color= avs[1];
  // projdi změněné elementy tohoto view
//   $each(this.owner.part,function(field,id) {
  for (let id in this.owner.part) { let field= this.owner.part[id];
    if ( field.changed && field.changed() && field.data && field._save && field.view==this ) {
      obj[id]= [field.value,icolor];
      field.DOM_Input.css('background-color',color);
    }
  }
  var ret= JSON.encode(obj);
  return ret;
};
/*
// --------------------------------------------------------------------------------------- set_notes
//fm: View.set_notes ([pairs,css])
// pairs :: { name:val, ...]
// zobrazí hodnoty 'val' přes elementy dané jménem 'name', aplikuje přitom styl zadaný 'css'
// bezparametrická funkce pouze vymaže přidané texty
// View.prototype.set_notes= function(pairs,css) {
//   if ( this.notes ) {
//     this.notes.each(function(note){note.destroy();});
//     this.notes= [];
//   }
//   else {
//     this.notes= [];
//   }
//   if ( pairs ) {
// //     $each(this.owner.part,function(field,id) {
//     for (let id in this.owner.part) { let field= this.owner.part[id];
//       if ( field.data && pairs[field.data.id] && field.view==this ) {
//         this.notes.push(new Element('div',{'class':css,html:pairs[field.data.id],styles:{
//           position:'absolute',left:field._l,top:field._t+(field._h||16)+2
//         }}).inject(this.owner.DOM_Block));
//       }
//     }
//   }
//   return 1;
// };
*/
// =====================================================================================> Ezer.Label
// ------------------------------------------------------------------------------------ image_filter
//fm: Label.image_filter (filter)
//      aplikuje zadaný filtr na obsažené obrázky, povolené hodnoty jsou: gray, sepia, sharpen
Label.prototype.image_filter= function(filter) {
  if ( !Ezer.canvas ) try {
    Ezer.canvas= fx.canvas();         // volání fce knihovny glfx.js
  } catch(e) { Ezer.canvas= null; }
  if ( Ezer.canvas && ['gray','sepia','sharpen'].contains(filter) ) {
    this.DOM_Block.find('img').each(function(i,image){
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
    });
  }
  return 1;
};

// ------------------------------------------------------------------------------------ img_filter
// pomocná funkce aplikuje zadaný filtr na obsažené obrázky, povolené hodnoty jsou: gray, sepia, sharpen
function img_filter (image,filter) {
  if ( !Ezer.canvas ) try {
    Ezer.canvas= fx.canvas();         // volání fce knihovny glfx.js
  } catch(e) { Ezer.canvas= null; }
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
    for(let i= 0; i<tags.length; i++) if ( tags[i] ) {
      let tag= tags[i];
      form.display(1,tag);
      let bounds= form.property({down:sum_h,return:'bounds'},tag);
      sum_h+= bounds._t==undefined ? 0
        : ((!i ? bounds._t : 0) + (bounds._h ? bounds._h + space : 0));
    }
  }
  else if ( smer=='up' ) {
    var h, top= [];
    top[0]= form.options._h;
    for(let i= 0; i<tags.length; i++) if ( tags[i] ) {
      let bounds= form.property({return:'bounds'},tags[i]);
      sum_h+= h= bounds._h ? bounds._h + space : 0;
      top[i+1]= top[i]+h;
    }
    for(let i= 0; i<tags.length; i++) if ( tags[i] ) {
      let tag= tags[i];
      form.display(1,tag);
      form.property({down:top[i]-sum_h},tag);
    }
  }
  return sum_h;
};
// --------------------------------------------------------------------------------------- load_json
//ff: db.make_rc (narozeni,sex)
//      vytvoří první část rodného čísla z narození a pohlaví
Ezer.fce.make_rc= function (narozeni,sex) {
  var rc='', dmy= narozeni.split('.');
  if ( dmy.length==3 ) {
    rc= dmy[2].substr(-2,2)
      + (sex==2 ? padNum(parseInt(dmy[1])+50,2) : padNum(dmy[1],2))
      + padNum(dmy[0],2);
  }
  return rc;
};

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
var roku= 
Ezer.fce.roku= function (dat,rok) {
  var roku= '', now= new Date();
  if ( dat ) {
    var m= dat.split('.');
    if ( m.length==3 ) {
      let nar= new Date(m[2],m[1]-1,m[0]);
      roku= (now-nar)/(1000*60*60*24*365.2425);
      roku= roku<10 ? Math.round(10*roku)/10 : Math.round(roku);
      if ( !roku ) roku= 1;       // první rok počítej i načatý
    }
    else if ( m.length==1 ) {
      roku= now.getFullYear() - dat;
      if ( !roku ) roku= 1;       // první rok počítej i načatý
    }
    else if ( dat=='0000-00-00' ) {
      roku= '';
    }
    else {
      m= dat.split('-');        // sql-formát
      if ( m.length==3 ) {
        let nar= new Date(m[0],m[1]-1,m[2]);
        roku= (now-nar)/(1000*60*60*24*365.2425);
        // do 20 let desetiny
        roku= roku<20 ? roku.toFixed(1) : roku.toFixed(0);
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
/*
// ------------------------------------------------------------------------------------------ roku_k
//ff: ys.roku_k (dat[,kdatu=now])
//      vrací počet roku uplynulých od daného data do daného data (neni-li uvedeno tak od běžného)
//s: ys
//Ezer.fce.roku_k= function (dat,kdatu) {
//  var str2date= function(s) {
//    var m= s.split('.');      // formát d.m.Y
//    if ( m.length==3 )
//      d= new Date(m[2],m[1]-1,m[0]);
//    else {
//      m= s.split('-');        // sql-formát
//      d= m.length==3 ? new Date(m[0],m[1]-1,m[2]) : 0;
//    }
//    return d;
//  };
//  if ( kdatu==undefined ) kdatu= new Date();
//  var roku= '';
//  var k= str2date(kdatu), d= str2date(dat);
//  if ( d && k ) {
//    // přibližně
//    roku= (k-d)/(1000*60*60*24*365.2425);
//    // přesně
//    var kd= k.getDate(),  dd= d.getDate(),
//        km= k.getMonth(), dm= d.getMonth(),
//        ky= k.getYear(),  dy= d.getYear();
//    roku= (km<dm || (km==dm && kd<dd)) ? ky-dy-1 : ky-dy;
//  }
//  return roku;
//};
 
// --------------------------------------------------------------------------------- set css_changed
//function web_changes(cases,css,flds) {
////   cases= [[form,table_id,key],...]    form se může opakovat pro různé table_id+key
////   chngs= [[table_id,key,field],...]
////   flds= {table -> { key -> [fld, ...]}}
//
//  // nejprve zrušíme předchozí obarvení
//  for (let elem of jQuery('.'+css)) {
//    jQuery(elem).removeClass(css);
//  }
//  // pokud byla změna, označ ji
//  if ( flds ) {
//    for (let ci= 0; ci<cases.length; ci++) {
//      let form= cases[ci][0].type=='var' ? cases[ci][0].value : cases[ci][0],
//          f_table= cases[ci][1],
//          f_key= cases[ci][2];
//      for (let table in flds) { // projdeme tabulky
//        if (table!=f_table) continue;
//        for (let key in flds[table]) {
//          if (key!=f_key) continue;
//          for (let fld of flds[table][key]) {
//            // projdeme elementy formuláře
//            for (let pi in form.part) {
//              let p= form.part[pi];
//              // a pro elementy
//              if ( p instanceof Elem && p.DOM_Block && p.data ) {
//                // pokud data=table.field a klíč
//                if ( p.data.id==fld && p.table && p.table.id==table ) {
//                  // přidáme css
//                  p.DOM_Block.addClass(css);
//                }
//              }
//            }
//          }
//        }
//      }
//    }
//  }
//}
*/
// --------------------------------------------------------------------------------- set css_changed
//ff: ys.set_css_changed (cases,css[,chngs])
// pokud je cases=null
//   odstraní všechna css
// pokud je definováno chngs
//   odstraní css v daných formulářích a poté je obnoví u změněných dat podle pole
//   cases= [[form,table_id,key],...]    form se může opakovat pro různé table_id+key
//   chngs= [[table_id,key,field],...]
// pokud není definováno chngs
//   cases= [form,...]  seznam formulářů, ve kterých má být odstraněné css
//s: ys
//Ezer.obj.set_css_changed= null;
Ezer.obj.set_css_changed_title= null; // 
var set_css_changed= 
Ezer.fce.set_css_changed= function (cases,css,chngs) {
  // vymazání starých poznámek
  if (!cases) {
    // zrušíme obarvení
    jQuery('.'+css).not('td').removeClass(css);
    Ezer.obj.set_css_changed_title= null;
    return;
  }
//  if ( Ezer.obj.set_css_changed ) {
//     Ezer.obj.set_css_changed.forEach(function(note){note.remove();});
//    Ezer.obj.set_css_changed= null;
//  }
//  Ezer.obj.set_css_changed= [];
  // vymazání starých title
  let ts= [], ti= 0;
  for (let ci= 0; ci<cases.length; ci++) {
    if (!ts.includes(cases[ci][1]))
      ts[ti++]= cases[ci][1];
  }
  if (!Ezer.obj.set_css_changed_title) 
    Ezer.obj.set_css_changed_title= [];
  for (let ti= 0; ti<ts.length; ti++) {
    let t= ts[ti];
    if ( Ezer.obj.set_css_changed_title?.[ti] ) {
      Ezer.obj.set_css_changed_title[ti].forEach(function(block){block.attr('title','');});
      Ezer.obj.set_css_changed_title[ti]= null;
    }
  }
  // vymazání css z pole formulářů
  var forms;
  if ( chngs ) {
    forms= [];
    for (let ci= 0; ci<cases.length; ci++) {
        forms.push(cases[ci][0]);
    }
  }
  else {
    forms= cases;
  }
  // zrušení css ve formuláři
  for (let fi= 0; fi<forms.length; fi++) {
    let form= forms[fi].type=='var' ? forms[fi].value : forms[fi];
    for (let pi in form.part) {
      let p= form.part[pi];
      if ( p.data ) {
        if ( p instanceof Elem && p.DOM_Block ) {
          p.DOM_Block.removeClass(css);
        }
      }
    }
  }
  // pokud byla změna, označ ji
  if ( chngs ) {
    for (let ci= 0; ci<cases.length; ci++) {
      let form= cases[ci][0].type=='var' ? cases[ci][0].value : cases[ci][0],
          table= cases[ci][1],
          key= cases[ci][2];
      // aplikace css podle změn
      for (let di= 0; di<chngs.length; di++) {
        var t= chngs[di][0],    // tabulka změněné věty
            k= chngs[di][1];    // s klíčem
        // pokud je klíč shodný s aktivním
        if ( key==k ) {
          var f= chngs[di][2],  // změněné table.field
              w= chngs[di][3],  // pachatel
              d= chngs[di][4];  // čas změny
          // projdeme elementy formuláře
          for (let pi in form.part) {
            let p= form.part[pi];
            // a pro elementy
            if ( p instanceof Elem && p.DOM_Block && p.data ) {
              // pokud data=table.field a klíč
              if ( p.data.id==f && p.table && p.table.id==table ) {
                // přidáme css
                p.DOM_Block.addClass(css);
                // změny do title
                p.DOM_Block.attr('title',`${w} ${d}`); 
                if (!Ezer.obj.set_css_changed_title?.[t]) 
                  Ezer.obj.set_css_changed_title[t]= [];
                Ezer.obj.set_css_changed_title[t].push(p.DOM_Block);
                // změny do span
//                Ezer.obj.set_css_changed.push(
//                  jQuery(`<span class="${css}" title="${d}">${w}</span>`)
//                    .css({left:p._l,top:p._t+(p._h||16)-1})
//                    .appendTo(p.owner.DOM_Block)
//                );
              }
            }
          }
        }
      }
    }
  }
  return 1;
};
