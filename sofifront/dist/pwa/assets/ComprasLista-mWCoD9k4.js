import{a0 as E,a2 as p,a3 as _,a4 as l,ac as i,a6 as b,ad as h,a5 as a,ax as D,aZ as j,ai as C,ae as v,aJ as M,ak as H,a9 as u,an as A,b2 as Y,aP as K,ag as N,ah as y,a$ as P,a7 as x,a8 as V,a_ as U,aa as c,aC as Q,aR as T,aI as w,b0 as G,ay as J,af as Z,aS as B,J as I,aQ as z,ao as W,aG as R,aD as X}from"./index-DzwCwwpR.js";let q;const n=s=>String(s??"").replace(/[&<>"']/g,e=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[e]);let S;function $(){return S||(S=fetch(new URL("logo-area-fresca.png",window.location.href).href).then(s=>s.ok?s.blob():Promise.reject(new Error("sin logo"))).then(s=>new Promise(e=>{const g=new FileReader;g.onload=()=>e(g.result),g.readAsDataURL(s)})).catch(()=>"")),S}const ee=`
  @page { size: letter portrait; margin: 12mm 11mm }
  * { box-sizing: border-box }
  body { font: 10.5px Arial, Helvetica, sans-serif; color: #222; margin: 0 }
  .cab { display: flex; align-items: flex-start; gap: 12px }
  .cab img { width: 110px }
  .emp { flex: 1 }
  .emp-nom { font-size: 16px; font-weight: bold; color: #c1272d; letter-spacing: .5px }
  .emp-dato { font-size: 9.5px; color: #555; line-height: 1.5 }
  .caja { width: 210px; border: 1.5px solid #c1272d; border-radius: 4px; overflow: hidden }
  .caja .tit { background: #c1272d; color: #fff; text-align: center; font-weight: bold;
               letter-spacing: 1px; padding: 4px; font-size: 11px }
  .caja table { width: 100%; border-collapse: collapse }
  .caja td { padding: 2px 8px; font-size: 10px }
  .caja .et { color: #666 }
  .caja .nro { font-size: 18px; font-weight: bold; color: #c1272d; text-align: right }
  .datos { display: grid; grid-template-columns: 2fr 1fr 1fr; margin-top: 10px;
           border: 1px solid #ccc; border-radius: 4px }
  .datos div { padding: 5px 8px; border-bottom: 1px solid #eee }
  .datos .ancho { grid-column: span 3 }
  .et2 { display: block; font-size: 8.5px; color: #777; text-transform: uppercase; letter-spacing: .3px }
  table.det { width: 100%; border-collapse: collapse; margin-top: 10px }
  table.det th { background: #37474f; color: #fff; font-size: 9px; text-transform: uppercase;
                 letter-spacing: .4px; padding: 6px 5px; text-align: left }
  table.det td { padding: 5px; border-bottom: 1px solid #e4e4e4 }
  table.det tr:nth-child(even) td { background: #fafafa }
  .num { text-align: right !important; white-space: nowrap }
  .cod { color: #666 }
  .pie { display: flex; gap: 12px; margin-top: 10px; align-items: flex-start }
  .obs { flex: 1; border: 1px solid #ddd; padding: 7px 9px; line-height: 1.5 }
  .tot { width: 38%; border-collapse: collapse }
  .tot td { padding: 4px 9px; border-bottom: 1px solid #eee }
  .tot .final td { background: #37474f; color: #fff; font-size: 13px; font-weight: bold; border: 0 }
  .firmas { display: flex; gap: 30px; margin-top: 55px }
  .firmas div { flex: 1; border-top: 1px solid #999; padding-top: 4px; text-align: center; font-size: 9.5px; color: #555 }
  .firmas b { display: block; color: #222; font-size: 10px }
  .legal { margin-top: 18px; text-align: center; font-size: 8.5px; color: #888 }
`;function O(){const s=R.formatDate(new Date,"YYYY-MM-DD");return{desde:s,hasta:s,buscar:"",estado:null}}const te={name:"ComprasLista",data(){return{compras:[],sel:{},dialogDetalle:!1,dialogAnular:!1,motivo:"",anulando:!1,imprimiendo:null,loading:!1,filtros:O(),pagination:{page:1,rowsPerPage:15,rowsNumber:0},columns:[{name:"acciones",label:"Opciones",field:"acciones",align:"left"},{name:"id",label:"Nº",field:"id",align:"left"},{name:"fecha",label:"Fecha",field:"fecha",align:"left",format:s=>String(s||"").substr(0,10)},{name:"hora",label:"Hora",field:"hora",align:"left"},{name:"proveedor",label:"Proveedor",field:"proveedor",align:"left"},{name:"nro_factura",label:"Factura",field:"nro_factura",align:"left"},{name:"tipo_pago",label:"Pago",field:"tipo_pago",align:"center"},{name:"estado",label:"Estado",field:"estado",align:"center"},{name:"total",label:"Total Bs.",field:"total",align:"right",format:s=>Number(s||0).toFixed(2)}]}},computed:{can(){return this.$store.getters["login/can"]}},created(){this.onRequest({pagination:this.pagination})},methods:{money(s){return Number(s||0).toFixed(2)},fechaCorta(s){if(!s)return"—";const e=String(s).substr(0,10).split("-");return e.length===3?e[2]+"/"+e[1]+"/"+e[0]:s},limpiar(){this.filtros=O(),this.recargar()},recargar(){this.pagination.page=1,this.onRequest({pagination:this.pagination})},onRequest(s){const{page:e,rowsPerPage:g}=s.pagination;this.loading=!0,this.$api.get("compras",{params:{desde:this.filtros.desde||"",hasta:this.filtros.hasta||"",buscar:this.filtros.buscar||"",estado:this.filtros.estado||"",page:e,perPage:g}}).then(r=>{this.compras=r.data.data,this.pagination.page=r.data.current_page,this.pagination.rowsPerPage=g,this.pagination.rowsNumber=r.data.total}).catch(r=>{this.avisar(r,"No se pudieron cargar las compras")}).finally(()=>{this.loading=!1})},verDetalle(s){this.sel=s,this.dialogDetalle=!0,this.$api.get("compras/"+s.id).then(e=>{this.sel=e.data}).catch(()=>{})},nombreUsuario(s){return[s.Nombre1,s.App1,s.Apm].map(e=>String(e||"").trim()).filter(Boolean).join(" ")||"—"},imprimirCompra(s){this.imprimiendo=s.id,Promise.all([this.$api.get("compras/"+s.id),$()]).then(([e,g])=>{const r=e.data,o=r.empresa||{},d=r.proveedor_rel||{},t=r.usuario?this.nombreUsuario(r.usuario):"—",f=(m,k=2)=>Number(m||0).toLocaleString("en-US",{minimumFractionDigits:k,maximumFractionDigits:k}),F=(r.detalles||[]).map((m,k)=>`<tr>
              <td class="num">${k+1}</td>
              <td class="cod">${n(m.cod_prod)}</td>
              <td>${n(m.nombre)}</td>
              <td>${n(m.unidad||"")}</td>
              <td class="num">${f(m.cantidad,m.unidad==="KG"?3:0)}</td>
              <td class="num">${f(m.precio)}</td>
              <td class="num"><b>${f(m.subtotal)}</b></td>
              <td>${n(m.lote||"—")}</td>
              <td>${n(this.fechaCorta(m.fecha_vencimiento))}</td>
            </tr>`).join(""),L=document.createElement("div");L.innerHTML=`
            <div class="cab">
              ${g?`<img src="${g}" alt="">`:""}
              <div class="emp">
                <div class="emp-nom">${n(o.nombre||"")}</div>
                <div class="emp-dato">
                  ${n(o.sucursal||"")} · NIT ${n(o.nit||"")}<br>
                  ${n(o.direccion||"")}<br>
                  Telf. ${n(o.telefono||"")} · ${n(o.ciudad||"")}
                </div>
              </div>
              <div class="caja">
                <div class="tit">NOTA DE COMPRA</div>
                <table>
                  <tr><td class="et">Nro</td><td class="nro">${n(r.id)}</td></tr>
                  <tr><td class="et">Fecha</td><td class="num">${n(this.fechaCorta(r.fecha))}</td></tr>
                  <tr><td class="et">Hora</td><td class="num">${n(r.hora||"")}</td></tr>
                </table>
              </div>
            </div>

            <div class="datos">
              <div><span class="et2">Proveedor</span><b>${n(r.proveedor||"Sin proveedor")}</b></div>
              <div><span class="et2">NIT</span>${n(r.nit||"—")}</div>
              <div><span class="et2">Teléfono</span>${n(String(d.TELF||"").trim()||"—")}</div>
              <div><span class="et2">Dirección</span>${n(String(d.DIRECCION||"").trim()||"—")}</div>
              <div><span class="et2">Factura del proveedor</span>${n(r.nro_factura||"—")}</div>
              <div><span class="et2">Forma de pago</span><b>${n(r.tipo_pago||"—")}</b></div>
              <div class="ancho"><span class="et2">Registrado por</span><b>${n(t)}</b></div>
            </div>

            <table class="det">
              <thead><tr>
                <th class="num" style="width:4%">#</th><th style="width:9%">Código</th><th>Producto</th>
                <th style="width:6%">Unid</th><th class="num" style="width:9%">Cantidad</th>
                <th class="num" style="width:10%">Costo Bs</th><th class="num" style="width:11%">Subtotal Bs</th>
                <th style="width:9%">Lote</th><th style="width:9%">Vence</th>
              </tr></thead>
              <tbody>${F}</tbody>
            </table>

            <div class="pie">
              <div class="obs">
                <b>${(r.detalles||[]).length} producto(s)</b><br>
                <b>Observación:</b> ${n(r.observacion||"—")}
              </div>
              <table class="tot">
                <tr><td>Subtotal Bs.</td><td class="num">${f(r.subtotal)}</td></tr>
                <tr><td>Descuento Bs.</td><td class="num">${f(r.descuento)}</td></tr>
                <tr class="final"><td>TOTAL Bs.</td><td class="num">${f(r.total)}</td></tr>
              </table>
            </div>

            <div class="firmas">
              <div><b>${n(r.proveedor||"")}&nbsp;</b>Entregado por (proveedor)</div>
              <div><b>${n(t)}</b>Recibido por</div>
            </div>

            <div class="legal">
              ${n(o.nombre||"")} · nota de compra Nº ${n(r.id)} · impresa el ${R.formatDate(new Date,"DD/MM/YYYY HH:mm")}
            </div>`,q||(q=new X.Printd),q.print(L,[ee])}).catch(e=>{this.avisar(e,"No se pudo cargar el detalle para imprimir")}).finally(()=>{this.imprimiendo=null})},pedirAnulacion(s){this.sel=s,this.motivo="",this.dialogAnular=!0},anular(){this.anulando=!0,this.$api.put("compras/"+this.sel.id+"/anular",{motivo:this.motivo}).then(s=>{this.$q.notify({message:s.data.message,color:"positive",icon:"check_circle",position:"top",timeout:6e3}),this.dialogAnular=!1,this.onRequest({pagination:this.pagination})}).catch(s=>{this.avisar(s,"No se pudo anular")}).finally(()=>{this.anulando=!1})},avisar(s,e){this.$q.notify({message:s.response?.data?.message||e,color:"negative",icon:"error",position:"top"})}}},ae={class:"row items-center q-col-gutter-sm q-mb-md"},oe={class:"col-auto"},le={class:"col-6 col-md-2"},se={class:"col-6 col-md-2"},ie={class:"col-12 col-md-3"},re={class:"col-6 col-md-2"},ne={class:"col-12 col-md row items-center q-gutter-sm"},de={class:"text-caption text-grey-7"},ce={key:1,class:"text-grey-6"},pe={class:"full-width row flex-center q-pa-md text-grey-7"},ue={class:"text-subtitle1 text-weight-bold"},me={class:"text-caption"},ge={key:0},fe={key:0,class:"text-caption"},be={class:"text-left"},he={class:"text-left"},ve={class:"text-right"},xe={class:"text-right"},ye={class:"text-right text-weight-bold"},_e={class:"text-left"},we={class:"text-left"},ke={class:"text-caption text-grey-7"},Ce={class:"text-weight-bold"},Ae={class:"text-subtitle1 text-weight-bold"};function De(s,e,g,r,o,d){return p(),_(W,{class:"q-pa-md"},{default:l(()=>[i("div",ae,[e[10]||(e[10]=i("div",{class:"col-12 col-md"},[i("div",{class:"text-h6 text-weight-bold"},"Compras"),i("div",{class:"text-caption text-grey-7"}," Ingresos de mercadería a proveedores; cada compra suma al stock ")],-1)),i("div",oe,[d.can("comprasNueva")?(p(),_(b,{key:0,color:"positive",unelevated:"","no-caps":"",icon:"add_shopping_cart",label:"Nueva compra",to:"/compras/nueva"})):h("",!0)])]),a(D,{flat:"",bordered:"",class:"q-pa-sm q-mb-md"},{default:l(()=>[i("div",{class:"row q-col-gutter-sm",onKeyup:e[4]||(e[4]=j((...t)=>d.recargar&&d.recargar(...t),["enter"]))},[i("div",le,[a(C,{modelValue:o.filtros.desde,"onUpdate:modelValue":e[0]||(e[0]=t=>o.filtros.desde=t),type:"date",dense:"",outlined:"",label:"Desde"},null,8,["modelValue"])]),i("div",se,[a(C,{modelValue:o.filtros.hasta,"onUpdate:modelValue":e[1]||(e[1]=t=>o.filtros.hasta=t),type:"date",dense:"",outlined:"",label:"Hasta"},null,8,["modelValue"])]),i("div",ie,[a(C,{modelValue:o.filtros.buscar,"onUpdate:modelValue":e[2]||(e[2]=t=>o.filtros.buscar=t),dense:"",outlined:"",clearable:"",label:"Proveedor, NIT o factura"},{append:l(()=>[a(v,{name:"search"})]),_:1},8,["modelValue"])]),i("div",re,[a(M,{modelValue:o.filtros.estado,"onUpdate:modelValue":e[3]||(e[3]=t=>o.filtros.estado=t),dense:"",outlined:"",clearable:"",label:"Estado",options:["ACTIVO","ANULADO"]},null,8,["modelValue"])]),i("div",ne,[a(b,{loading:o.loading,color:"primary",icon:"search","no-caps":"",label:"Buscar",onClick:d.recargar},null,8,["loading","onClick"]),a(b,{flat:"",color:"grey-7",icon:"layers_clear","no-caps":"",label:"Limpiar",onClick:d.limpiar},null,8,["onClick"])])],32)]),_:1}),a(H,{flat:"",bordered:"",dense:"",rows:o.compras,columns:o.columns,"row-key":"id",pagination:o.pagination,"onUpdate:pagination":e[5]||(e[5]=t=>o.pagination=t),loading:o.loading,"rows-per-page-options":[15,30,50,100],onRequest:d.onRequest},{"body-cell-id":l(t=>[a(A,{props:t},{default:l(()=>[a(Q,{color:"indigo-5","text-color":"white"},{default:l(()=>[u("#"+c(t.value),1)]),_:2},1024)]),_:2},1032,["props"])]),"body-cell-estado":l(t=>[a(A,{props:t,class:"text-center"},{default:l(()=>[a(Q,{color:t.value==="ANULADO"?"negative":"positive","text-color":"white"},{default:l(()=>[u(c(t.value),1)]),_:2},1032,["color"])]),_:2},1032,["props"])]),"body-cell-proveedor":l(t=>[a(A,{props:t},{default:l(()=>[t.value?(p(),x(V,{key:0},[u(c(t.value)+" ",1),i("div",de,"NIT "+c(t.row.nit||"—"),1)],64)):(p(),x("span",ce,"Sin proveedor"))]),_:2},1032,["props"])]),"body-cell-acciones":l(t=>[a(A,{props:t,style:{"white-space":"nowrap"}},{default:l(()=>[a(b,{dense:"",flat:"",round:"",size:"sm",icon:"more_vert",color:"grey-8",loading:o.imprimiendo===t.row.id},{default:l(()=>[a(Y,{"auto-close":""},{default:l(()=>[a(K,{dense:"",style:{"min-width":"230px"}},{default:l(()=>[a(N,{clickable:"",onClick:f=>d.verDetalle(t.row)},{default:l(()=>[a(y,{avatar:""},{default:l(()=>[a(v,{name:"visibility",color:"primary"})]),_:1}),a(y,null,{default:l(()=>[...e[11]||(e[11]=[u("Ver detalle",-1)])]),_:1})]),_:1},8,["onClick"]),a(N,{clickable:"",disable:t.row.estado==="ANULADO",onClick:f=>d.imprimirCompra(t.row)},{default:l(()=>[a(y,{avatar:""},{default:l(()=>[a(v,{name:"print",color:"secondary"})]),_:1}),a(y,null,{default:l(()=>[e[13]||(e[13]=u(" Imprimir nota de compra ",-1)),t.row.estado==="ANULADO"?(p(),_(P,{key:0,caption:""},{default:l(()=>[...e[12]||(e[12]=[u("Anulada: no se imprime",-1)])]),_:1})):h("",!0)]),_:2},1024)]),_:2},1032,["disable","onClick"]),d.can("comprasAnular")&&t.row.estado!=="ANULADO"?(p(),x(V,{key:0},[a(U),a(N,{clickable:"",onClick:f=>d.pedirAnulacion(t.row)},{default:l(()=>[a(y,{avatar:""},{default:l(()=>[a(v,{name:"block",color:"negative"})]),_:1}),a(y,null,{default:l(()=>[e[15]||(e[15]=u(" Anular ",-1)),a(P,{caption:""},{default:l(()=>[...e[14]||(e[14]=[u("Devuelve el stock que había ingresado",-1)])]),_:1})]),_:1})]),_:1},8,["onClick"])],64)):h("",!0)]),_:2},1024)]),_:2},1024)]),_:2},1032,["loading"])]),_:2},1032,["props"])]),"no-data":l(()=>[i("div",pe,[a(v,{name:"inventory",size:"20px",class:"q-mr-sm"}),e[16]||(e[16]=u(" No hay compras en este rango ",-1))])]),_:1},8,["rows","columns","pagination","loading","onRequest"]),a(T,{modelValue:o.dialogDetalle,"onUpdate:modelValue":e[7]||(e[7]=t=>o.dialogDetalle=t)},{default:l(()=>[a(D,{style:{"min-width":"340px","max-width":"720px",width:"100%"}},{default:l(()=>[a(w,{class:"bg-primary text-white q-py-sm"},{default:l(()=>[i("div",ue,"Compra #"+c(o.sel.id),1),i("div",me,[u(c(o.sel.proveedor||"Sin proveedor")+" · "+c(String(o.sel.fecha||"").substr(0,10))+" "+c(o.sel.hora)+" ",1),o.sel.nro_factura?(p(),x("span",ge," · Factura "+c(o.sel.nro_factura),1)):h("",!0)]),o.sel.usuario?(p(),x("div",fe,[a(v,{name:"person",size:"14px"}),u(" Registrado por "+c(d.nombreUsuario(o.sel.usuario)),1)])):h("",!0)]),_:1}),o.sel.estado==="ANULADO"?(p(),_(w,{key:0,class:"q-py-sm"},{default:l(()=>[a(G,{dense:"",rounded:"",class:"bg-red-1 text-red-9"},{avatar:l(()=>[a(v,{name:"block"})]),default:l(()=>[u(" Anulada: "+c(o.sel.motivo_anulacion)+" — el stock fue devuelto. ",1)]),_:1})]),_:1})):h("",!0),a(w,{class:"q-pa-none"},{default:l(()=>[a(J,{dense:"",flat:"","wrap-cells":""},{default:l(()=>[e[17]||(e[17]=i("thead",null,[i("tr",{class:"bg-grey-2"},[i("th",{class:"text-left"},"Código"),i("th",{class:"text-left"},"Producto"),i("th",{class:"text-right"},"Cant."),i("th",{class:"text-right"},"Costo"),i("th",{class:"text-right"},"Subtotal"),i("th",{class:"text-left"},"Lote"),i("th",{class:"text-left"},"Vence")])],-1)),i("tbody",null,[(p(!0),x(V,null,Z(o.sel.detalles||[],t=>(p(),x("tr",{key:t.id},[i("td",be,c(t.cod_prod),1),i("td",he,c(t.nombre),1),i("td",ve,c(Number(t.cantidad).toFixed(t.unidad==="KG"?3:0)),1),i("td",xe,c(d.money(t.precio)),1),i("td",ye,c(d.money(t.subtotal)),1),i("td",_e,c(t.lote||"—"),1),i("td",we,c(d.fechaCorta(t.fecha_vencimiento)),1)]))),128))])]),_:1})]),_:1}),a(U),a(B,{align:"between",class:"q-px-md"},{default:l(()=>[i("div",null,[i("div",ke," Subtotal Bs "+c(d.money(o.sel.subtotal))+" · Descuento Bs "+c(d.money(o.sel.descuento)),1),i("div",Ce,"Total: Bs "+c(d.money(o.sel.total)),1)]),i("div",null,[o.sel.estado!=="ANULADO"?(p(),_(b,{key:0,flat:"","no-caps":"",icon:"print",label:"Imprimir",color:"secondary",loading:o.imprimiendo===o.sel.id,onClick:e[6]||(e[6]=t=>d.imprimirCompra(o.sel))},null,8,["loading"])):h("",!0),I(a(b,{flat:"","no-caps":"",label:"Cerrar",color:"primary"},null,512),[[z]])])]),_:1})]),_:1})]),_:1},8,["modelValue"]),a(T,{modelValue:o.dialogAnular,"onUpdate:modelValue":e[9]||(e[9]=t=>o.dialogAnular=t)},{default:l(()=>[a(D,{style:{"min-width":"340px"}},{default:l(()=>[a(w,{class:"q-py-sm"},{default:l(()=>[i("div",Ae,"Anular compra #"+c(o.sel.id),1),e[18]||(e[18]=i("div",{class:"text-caption text-grey-7"}," Se devolverá al stock todo lo que había ingresado. ",-1))]),_:1}),a(w,{class:"q-pt-none"},{default:l(()=>[a(C,{modelValue:o.motivo,"onUpdate:modelValue":e[8]||(e[8]=t=>o.motivo=t),modelModifiers:{trim:!0},outlined:"",dense:"",autofocus:"",autogrow:"",label:"Motivo"},null,8,["modelValue"])]),_:1}),a(B,{align:"right",class:"q-pa-sm"},{default:l(()=>[I(a(b,{flat:"",dense:"","no-caps":"",label:"Cancelar"},null,512),[[z]]),a(b,{color:"negative",dense:"",unelevated:"","no-caps":"",label:"Anular",disable:!o.motivo,loading:o.anulando,onClick:d.anular},null,8,["disable","loading","onClick"])]),_:1})]),_:1})]),_:1},8,["modelValue"])]),_:1})}const Ve=E(te,[["render",De]]);export{Ve as default};
