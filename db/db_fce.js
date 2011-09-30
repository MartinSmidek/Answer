// uživatelské funkce aplikace YS
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
