import{o as m,c as _,B as s,C as i,M as b,J as h,D as o,Q as N,bN as j,L as w,F as v,H as u,E as x,ay as D,N as L,I as c,aB as S,G as k,az as M,aA as P,ak as T,aC as I}from"./vendor-BwiFWSlS.js";import{Q as R}from"./QSelect-BGkC1LZX.js";import{Q as H}from"./QSpace-B8q9nEec.js";import{Q as Y}from"./QTooltip-CC19AcSe.js";import{Q as B}from"./QBadge-CbCFSM91.js";import{Q as A}from"./QTd-CDDmDgbE.js";import{Q as V,a as y,b as O}from"./QItemLabel-DLVo52mE.js";import{Q as K}from"./QList-B_5MMAVl.js";import{Q as G}from"./QMenu-CtPMg9NS.js";import{Q as J}from"./QTable-Dq5nNHer.js";import{_ as W,e as X,Q as Z}from"./index-BcilTAza.js";import{Q as $}from"./QMarkupTable-BEOWw-2b.js";import{C as E}from"./ClosePopup-CHcxEkvh.js";import{p as ee}from"./index-DPxdJb2F.js";import"./QChip-DLRCf8W2.js";import"./QLinearProgress-Cca-_kgv.js";let Q;const n=l=>String(l??"").replace(/[&<>"']/g,e=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"})[e]);let U;function te(){return U||(U=fetch(new URL("logo-area-fresca.png",window.location.href).href).then(l=>l.ok?l.blob():Promise.reject(new Error("sin logo"))).then(l=>new Promise(e=>{const p=new FileReader;p.onload=()=>e(p.result),p.readAsDataURL(l)})).catch(()=>"")),U}const oe=`
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
`;function z(){const l=I.formatDate(new Date,"YYYY-MM-DD");return{desde:l,hasta:l,buscar:"",estado:null,usuario:null,producto:""}}const ae={name:"ComprasLista",data(){return{compras:[],sel:{},dialogDetalle:!1,dialogAnular:!1,motivo:"",anulando:!1,imprimiendo:null,exportando:!1,usuarios:[],loading:!1,filtros:z(),pagination:{page:1,rowsPerPage:15,rowsNumber:0},columns:[{name:"acciones",label:"Opciones",field:"acciones",align:"left"},{name:"id",label:"Nº",field:"id",align:"left"},{name:"fecha",label:"Fecha",field:"fecha",align:"left",format:l=>String(l||"").substr(0,10)},{name:"hora",label:"Hora",field:"hora",align:"left"},{name:"proveedor",label:"Proveedor",field:"proveedor",align:"left"},{name:"nro_factura",label:"Factura",field:"nro_factura",align:"left"},{name:"tipo_pago",label:"Pago",field:"tipo_pago",align:"center"},{name:"usuario",label:"Registrado por",field:l=>l.usuario?this.nombreUsuario(l.usuario):"—",align:"left"},{name:"estado",label:"Estado",field:"estado",align:"center"},{name:"total",label:"Total Bs.",field:"total",align:"right",format:l=>Number(l||0).toFixed(2)}]}},computed:{can(){return this.$store.getters["login/can"]}},created(){this.onRequest({pagination:this.pagination}),this.$api.get("compras/usuarios").then(l=>{this.usuarios=l.data}).catch(()=>{})},methods:{money(l){return Number(l||0).toFixed(2)},fechaCorta(l){if(!l)return"—";const e=String(l).substr(0,10).split("-");return e.length===3?e[2]+"/"+e[1]+"/"+e[0]:l},limpiar(){this.filtros=z(),this.recargar()},recargar(){this.pagination.page=1,this.onRequest({pagination:this.pagination})},params(){return{desde:this.filtros.desde||"",hasta:this.filtros.hasta||"",buscar:this.filtros.buscar||"",estado:this.filtros.estado||"",usuario:this.filtros.usuario||"",producto:(this.filtros.producto||"").trim()}},onRequest(l){const{page:e,rowsPerPage:p}=l.pagination;this.loading=!0,this.$api.get("compras",{params:{...this.params(),page:e,perPage:p}}).then(r=>{this.compras=r.data.data,this.pagination.page=r.data.current_page,this.pagination.rowsPerPage=p,this.pagination.rowsNumber=r.data.total}).catch(r=>{this.avisar(r,"No se pudieron cargar las compras")}).finally(()=>{this.loading=!1})},async exportarExcel(){this.exportando=!0;try{const l=await this.$api.get("compras/excel",{params:this.params(),responseType:"blob"}),e=window.URL.createObjectURL(l.data),p=document.createElement("a");p.href=e;const{desde:r,hasta:t}=this.filtros;p.download="compras_"+(r||"inicio")+(r===t?"":"_a_"+(t||"hoy"))+".xlsx",p.click(),window.URL.revokeObjectURL(e)}catch(l){let e;try{e=JSON.parse(await l.response.data.text()).message}catch{}this.avisar({response:{data:{message:e}}},"No se pudo generar el Excel")}finally{this.exportando=!1}},verDetalle(l){this.sel=l,this.dialogDetalle=!0,this.$api.get("compras/"+l.id).then(e=>{this.sel=e.data}).catch(()=>{})},nombreUsuario(l){return[l.Nombre1,l.App1,l.Apm].map(e=>String(e||"").trim()).filter(Boolean).join(" ")||"—"},imprimirCompra(l){this.imprimiendo=l.id,Promise.all([this.$api.get("compras/"+l.id),te()]).then(([e,p])=>{const r=e.data,t=r.empresa||{},d=r.proveedor_rel||{},a=r.usuario?this.nombreUsuario(r.usuario):"—",g=(f,C=2)=>Number(f||0).toLocaleString("en-US",{minimumFractionDigits:C,maximumFractionDigits:C}),F=(r.detalles||[]).map((f,C)=>`<tr>
              <td class="num">${C+1}</td>
              <td class="cod">${n(f.cod_prod)}</td>
              <td>${n(f.nombre)}</td>
              <td>${n(f.unidad||"")}</td>
              <td class="num">${g(f.cantidad,f.unidad==="KG"?3:0)}</td>
              <td class="num">${g(f.precio)}</td>
              <td class="num"><b>${g(f.subtotal)}</b></td>
              <td>${n(f.lote||"—")}</td>
              <td>${n(this.fechaCorta(f.fecha_vencimiento))}</td>
            </tr>`).join(""),q=document.createElement("div");q.innerHTML=`
            <div class="cab">
              ${p?`<img src="${p}" alt="">`:""}
              <div class="emp">
                <div class="emp-nom">${n(t.nombre||"")}</div>
                <div class="emp-dato">
                  ${n(t.sucursal||"")} · NIT ${n(t.nit||"")}<br>
                  ${n(t.direccion||"")}<br>
                  Telf. ${n(t.telefono||"")} · ${n(t.ciudad||"")}
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
              <div class="ancho"><span class="et2">Registrado por</span><b>${n(a)}</b></div>
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
                <tr><td>Subtotal Bs.</td><td class="num">${g(r.subtotal)}</td></tr>
                <tr><td>Descuento Bs.</td><td class="num">${g(r.descuento)}</td></tr>
                <tr class="final"><td>TOTAL Bs.</td><td class="num">${g(r.total)}</td></tr>
              </table>
            </div>

            <div class="firmas">
              <div><b>${n(r.proveedor||"")}&nbsp;</b>Entregado por (proveedor)</div>
              <div><b>${n(a)}</b>Recibido por</div>
            </div>

            <div class="legal">
              ${n(t.nombre||"")} · nota de compra Nº ${n(r.id)} · impresa el ${I.formatDate(new Date,"DD/MM/YYYY HH:mm")}
            </div>`,Q||(Q=new ee.Printd),Q.print(q,[oe])}).catch(e=>{this.avisar(e,"No se pudo cargar el detalle para imprimir")}).finally(()=>{this.imprimiendo=null})},pedirAnulacion(l){this.sel=l,this.motivo="",this.dialogAnular=!0},anular(){this.anulando=!0,this.$api.put("compras/"+this.sel.id+"/anular",{motivo:this.motivo}).then(l=>{this.$q.notify({message:l.data.message,color:"positive",icon:"check_circle",position:"top",timeout:6e3}),this.dialogAnular=!1,this.onRequest({pagination:this.pagination})}).catch(l=>{this.avisar(l,"No se pudo anular")}).finally(()=>{this.anulando=!1})},avisar(l,e){this.$q.notify({message:l.response?.data?.message||e,color:"negative",icon:"error",position:"top"})}}},le={class:"row items-center q-col-gutter-sm q-mb-md"},se={class:"col-auto"},ie={class:"col-6 col-md-2"},re={class:"col-6 col-md-2"},ne={class:"col-12 col-md-2"},de={class:"col-6 col-md-2"},ce={class:"col-6 col-md-2"},pe={class:"col-6 col-md-2"},ue={class:"col-12 row items-center q-gutter-sm"},me={class:"text-caption text-grey-7"},fe={key:1,class:"text-grey-6"},ge={class:"full-width row flex-center q-pa-md text-grey-7"},be={class:"text-subtitle1 text-weight-bold"},he={class:"text-caption"},ve={key:0},xe={key:0,class:"text-caption"},ye={class:"text-left"},_e={class:"text-left"},we={class:"text-right"},ke={class:"text-right"},Ce={class:"text-right text-weight-bold"},Ae={class:"text-left"},Ne={class:"text-left"},De={class:"text-caption text-grey-7"},Ve={class:"text-weight-bold"},Qe={class:"text-subtitle1 text-weight-bold"};function Ue(l,e,p,r,t,d){return m(),_(Z,{class:"q-pa-md"},{default:s(()=>[i("div",le,[e[12]||(e[12]=i("div",{class:"col-12 col-md"},[i("div",{class:"text-h6 text-weight-bold"},"Compras"),i("div",{class:"text-caption text-grey-7"}," Ingresos de mercadería a proveedores; cada compra suma al stock ")],-1)),i("div",se,[d.can("comprasNueva")?(m(),_(b,{key:0,color:"positive",unelevated:"","no-caps":"",icon:"add_shopping_cart",label:"Nueva compra",to:"/compras/nueva"})):h("",!0)])]),o(N,{flat:"",bordered:"",class:"q-pa-sm q-mb-md"},{default:s(()=>[i("div",{class:"row q-col-gutter-sm",onKeyup:e[6]||(e[6]=j((...a)=>d.recargar&&d.recargar(...a),["enter"]))},[i("div",ie,[o(w,{modelValue:t.filtros.desde,"onUpdate:modelValue":e[0]||(e[0]=a=>t.filtros.desde=a),type:"date",dense:"",outlined:"",label:"Desde"},null,8,["modelValue"])]),i("div",re,[o(w,{modelValue:t.filtros.hasta,"onUpdate:modelValue":e[1]||(e[1]=a=>t.filtros.hasta=a),type:"date",dense:"",outlined:"",label:"Hasta"},null,8,["modelValue"])]),i("div",ne,[o(w,{modelValue:t.filtros.buscar,"onUpdate:modelValue":e[2]||(e[2]=a=>t.filtros.buscar=a),dense:"",outlined:"",clearable:"",label:"Proveedor, NIT o factura"},{append:s(()=>[o(v,{name:"search"})]),_:1},8,["modelValue"])]),i("div",de,[o(R,{modelValue:t.filtros.estado,"onUpdate:modelValue":e[3]||(e[3]=a=>t.filtros.estado=a),dense:"",outlined:"",clearable:"",label:"Estado",options:["ACTIVO","ANULADO"]},null,8,["modelValue"])]),i("div",ce,[o(R,{modelValue:t.filtros.usuario,"onUpdate:modelValue":e[4]||(e[4]=a=>t.filtros.usuario=a),dense:"",outlined:"",clearable:"","emit-value":"","map-options":"",label:"Registrado por",options:t.usuarios,"option-value":"id","option-label":"nombre"},null,8,["modelValue","options"])]),i("div",pe,[o(w,{modelValue:t.filtros.producto,"onUpdate:modelValue":e[5]||(e[5]=a=>t.filtros.producto=a),dense:"",outlined:"",clearable:"",label:"Producto (código o nombre)"},null,8,["modelValue"])]),i("div",ue,[o(b,{loading:t.loading,color:"primary",icon:"search","no-caps":"",label:"Buscar",onClick:d.recargar},null,8,["loading","onClick"]),o(b,{flat:"",color:"grey-7",icon:"layers_clear","no-caps":"",label:"Limpiar",onClick:d.limpiar},null,8,["onClick"]),o(H),o(b,{color:"green-8",unelevated:"","no-caps":"",icon:"grid_on",label:"Exportar Excel",loading:t.exportando,onClick:d.exportarExcel},{default:s(()=>[o(Y,null,{default:s(()=>[...e[13]||(e[13]=[u("Compras, detalle y resúmenes por producto, fecha, usuario y proveedor, con los filtros de arriba",-1)])]),_:1})]),_:1},8,["loading","onClick"])])],32)]),_:1}),o(J,{flat:"",bordered:"",dense:"",rows:t.compras,columns:t.columns,"row-key":"id",pagination:t.pagination,"onUpdate:pagination":e[7]||(e[7]=a=>t.pagination=a),loading:t.loading,"rows-per-page-options":[15,30,50,100],onRequest:d.onRequest},{"body-cell-id":s(a=>[o(A,{props:a},{default:s(()=>[o(B,{color:"indigo-5","text-color":"white"},{default:s(()=>[u("#"+c(a.value),1)]),_:2},1024)]),_:2},1032,["props"])]),"body-cell-estado":s(a=>[o(A,{props:a,class:"text-center"},{default:s(()=>[o(B,{color:a.value==="ANULADO"?"negative":"positive","text-color":"white"},{default:s(()=>[u(c(a.value),1)]),_:2},1032,["color"])]),_:2},1032,["props"])]),"body-cell-proveedor":s(a=>[o(A,{props:a},{default:s(()=>[a.value?(m(),x(D,{key:0},[u(c(a.value)+" ",1),i("div",me,"NIT "+c(a.row.nit||"—"),1)],64)):(m(),x("span",fe,"Sin proveedor"))]),_:2},1032,["props"])]),"body-cell-acciones":s(a=>[o(A,{props:a,style:{"white-space":"nowrap"}},{default:s(()=>[o(b,{dense:"",flat:"",round:"",size:"sm",icon:"more_vert",color:"grey-8",loading:t.imprimiendo===a.row.id},{default:s(()=>[o(G,{"auto-close":""},{default:s(()=>[o(K,{dense:"",style:{"min-width":"230px"}},{default:s(()=>[o(V,{clickable:"",onClick:g=>d.verDetalle(a.row)},{default:s(()=>[o(y,{avatar:""},{default:s(()=>[o(v,{name:"visibility",color:"primary"})]),_:1}),o(y,null,{default:s(()=>[...e[14]||(e[14]=[u("Ver detalle",-1)])]),_:1})]),_:1},8,["onClick"]),o(V,{clickable:"",disable:a.row.estado==="ANULADO",onClick:g=>d.imprimirCompra(a.row)},{default:s(()=>[o(y,{avatar:""},{default:s(()=>[o(v,{name:"print",color:"secondary"})]),_:1}),o(y,null,{default:s(()=>[e[16]||(e[16]=u(" Imprimir nota de compra ",-1)),a.row.estado==="ANULADO"?(m(),_(O,{key:0,caption:""},{default:s(()=>[...e[15]||(e[15]=[u("Anulada: no se imprime",-1)])]),_:1})):h("",!0)]),_:2},1024)]),_:2},1032,["disable","onClick"]),d.can("comprasAnular")&&a.row.estado!=="ANULADO"?(m(),x(D,{key:0},[o(L),o(V,{clickable:"",onClick:g=>d.pedirAnulacion(a.row)},{default:s(()=>[o(y,{avatar:""},{default:s(()=>[o(v,{name:"block",color:"negative"})]),_:1}),o(y,null,{default:s(()=>[e[18]||(e[18]=u(" Anular ",-1)),o(O,{caption:""},{default:s(()=>[...e[17]||(e[17]=[u("Devuelve el stock que había ingresado",-1)])]),_:1})]),_:1})]),_:1},8,["onClick"])],64)):h("",!0)]),_:2},1024)]),_:2},1024)]),_:2},1032,["loading"])]),_:2},1032,["props"])]),"no-data":s(()=>[i("div",ge,[o(v,{name:"inventory",size:"20px",class:"q-mr-sm"}),e[19]||(e[19]=u(" No hay compras en este rango ",-1))])]),_:1},8,["rows","columns","pagination","loading","onRequest"]),o(S,{modelValue:t.dialogDetalle,"onUpdate:modelValue":e[9]||(e[9]=a=>t.dialogDetalle=a)},{default:s(()=>[o(N,{style:{"min-width":"340px","max-width":"720px",width:"100%"}},{default:s(()=>[o(k,{class:"bg-primary text-white q-py-sm"},{default:s(()=>[i("div",be,"Compra #"+c(t.sel.id),1),i("div",he,[u(c(t.sel.proveedor||"Sin proveedor")+" · "+c(String(t.sel.fecha||"").substr(0,10))+" "+c(t.sel.hora)+" ",1),t.sel.nro_factura?(m(),x("span",ve," · Factura "+c(t.sel.nro_factura),1)):h("",!0)]),t.sel.usuario?(m(),x("div",xe,[o(v,{name:"person",size:"14px"}),u(" Registrado por "+c(d.nombreUsuario(t.sel.usuario)),1)])):h("",!0)]),_:1}),t.sel.estado==="ANULADO"?(m(),_(k,{key:0,class:"q-py-sm"},{default:s(()=>[o(X,{dense:"",rounded:"",class:"bg-red-1 text-red-9"},{avatar:s(()=>[o(v,{name:"block"})]),default:s(()=>[u(" Anulada: "+c(t.sel.motivo_anulacion)+" — el stock fue devuelto. ",1)]),_:1})]),_:1})):h("",!0),o(k,{class:"q-pa-none"},{default:s(()=>[o($,{dense:"",flat:"","wrap-cells":""},{default:s(()=>[e[20]||(e[20]=i("thead",null,[i("tr",{class:"bg-grey-2"},[i("th",{class:"text-left"},"Código"),i("th",{class:"text-left"},"Producto"),i("th",{class:"text-right"},"Cant."),i("th",{class:"text-right"},"Costo"),i("th",{class:"text-right"},"Subtotal"),i("th",{class:"text-left"},"Lote"),i("th",{class:"text-left"},"Vence")])],-1)),i("tbody",null,[(m(!0),x(D,null,M(t.sel.detalles||[],a=>(m(),x("tr",{key:a.id},[i("td",ye,c(a.cod_prod),1),i("td",_e,c(a.nombre),1),i("td",we,c(Number(a.cantidad).toFixed(a.unidad==="KG"?3:0)),1),i("td",ke,c(d.money(a.precio)),1),i("td",Ce,c(d.money(a.subtotal)),1),i("td",Ae,c(a.lote||"—"),1),i("td",Ne,c(d.fechaCorta(a.fecha_vencimiento)),1)]))),128))])]),_:1})]),_:1}),o(L),o(P,{align:"between",class:"q-px-md"},{default:s(()=>[i("div",null,[i("div",De," Subtotal Bs "+c(d.money(t.sel.subtotal))+" · Descuento Bs "+c(d.money(t.sel.descuento)),1),i("div",Ve,"Total: Bs "+c(d.money(t.sel.total)),1)]),i("div",null,[t.sel.estado!=="ANULADO"?(m(),_(b,{key:0,flat:"","no-caps":"",icon:"print",label:"Imprimir",color:"secondary",loading:t.imprimiendo===t.sel.id,onClick:e[8]||(e[8]=a=>d.imprimirCompra(t.sel))},null,8,["loading"])):h("",!0),T(o(b,{flat:"","no-caps":"",label:"Cerrar",color:"primary"},null,512),[[E]])])]),_:1})]),_:1})]),_:1},8,["modelValue"]),o(S,{modelValue:t.dialogAnular,"onUpdate:modelValue":e[11]||(e[11]=a=>t.dialogAnular=a)},{default:s(()=>[o(N,{style:{"min-width":"340px"}},{default:s(()=>[o(k,{class:"q-py-sm"},{default:s(()=>[i("div",Qe,"Anular compra #"+c(t.sel.id),1),e[21]||(e[21]=i("div",{class:"text-caption text-grey-7"}," Se devolverá al stock todo lo que había ingresado. ",-1))]),_:1}),o(k,{class:"q-pt-none"},{default:s(()=>[o(w,{modelValue:t.motivo,"onUpdate:modelValue":e[10]||(e[10]=a=>t.motivo=a),modelModifiers:{trim:!0},outlined:"",dense:"",autofocus:"",autogrow:"",label:"Motivo"},null,8,["modelValue"])]),_:1}),o(P,{align:"right",class:"q-pa-sm"},{default:s(()=>[T(o(b,{flat:"",dense:"","no-caps":"",label:"Cancelar"},null,512),[[E]]),o(b,{color:"negative",dense:"",unelevated:"","no-caps":"",label:"Anular",disable:!t.motivo,loading:t.anulando,onClick:d.anular},null,8,["disable","loading","onClick"])]),_:1})]),_:1})]),_:1},8,["modelValue"])]),_:1})}const Ke=W(ae,[["render",Ue]]);export{Ke as default};
