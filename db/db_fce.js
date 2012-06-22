// uživatelské funkce aplikace YS
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
      Ezer.obj.google_maps.mark.push(
        new google.maps.Marker({position:ll,map:Ezer.obj.google_maps.map}));
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
    var texture= Ezer.canvas.texture(image);
    var data= Ezer.canvas.draw(texture);
    switch (filter) {
    case 'gray':    data.hueSaturation(-1,-1); break;
    case 'sepia':   data.sepia(1);             break;
    case 'sharpen':
      var radius= arguments[2]||1, strength= arguments[3]||1;
      data.unsharpMask(radius,strength);
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
      roku= Math.floor((now-nar)/(1000*60*60*24*365.26));
    }
  }
  return roku;
};
// ------------------------------------------------------------------------------------------------- roku
//ff: ys.roku (dat)
//      vrací zaokrouhlený počet roku uplynulých od daného data, první rok počítá i načatý
//s: ys
Ezer.fce.roku= function (dat,rok) {
  var roku= '', now= new Date();
  if ( dat ) {
    var m= dat.split('.');
    if ( m.length==3 ) {
      var nar= new Date(m[2],m[1]-1,m[0]);
      roku= (now-nar)/(1000*60*60*24*365.26);
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
        roku= (now-nar)/(1000*60*60*24*365.26);
        roku= roku<10 ? Math.round(10*roku)/10 : Math.round(roku);
        if ( !roku ) roku= 1;       // první rok počítej i načatý
      }
    }
  }
  else if ( rok ) {
    roku= now.getFullYear() - rok;
    if ( !roku ) roku= 1;       // první rok počítej i načatý
  }
  return roku;
};
