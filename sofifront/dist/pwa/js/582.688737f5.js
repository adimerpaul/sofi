"use strict";(self["webpackChunksofi"]=self["webpackChunksofi"]||[]).push([[582],{2140:(e,a,t)=>{t.r(a),t.d(a,{default:()=>le});var l=t(3673),o=t(2323);const u=(0,l.Uk)("Sofia"),n=(0,l._)("div",null,null,-1),r=(0,l.Uk)("Opciones"),i=(0,l.Uk)("Principal "),d=(0,l.Uk)(" Principal "),s=(0,l.Uk)("Ingresar"),m=(0,l.Uk)(" Ingresar al sistema "),c=(0,l.Uk)("Realizar Visita "),w=(0,l.Uk)(" Realizar Visita "),f=(0,l.Uk)("Mis pedidos "),_=(0,l.Uk)(" Mis pedidos "),k=(0,l.Uk)("Lista Clientes "),W=(0,l.Uk)(" Habilitar Cliente "),g=(0,l.Uk)("Pedidos Pendientes "),p=(0,l.Uk)(" Faltantes "),b=(0,l.Uk)("Cobros realizados "),U=(0,l.Uk)(" Cobros realizados "),v=(0,l.Uk)("Cobranzas"),q=(0,l.Uk)(" Cobro a cliente "),h=(0,l.Uk)("Mis Cobros"),y=(0,l.Uk)(" Mis Cobros "),$=(0,l.Uk)("Exportar excel"),x=(0,l.Uk)(" Exportar excel "),C=(0,l.Uk)("Ruta de pedidos"),j=(0,l.Uk)(" Ruta de pedidos "),Z=(0,l.Uk)("Reporte entrega"),Q=(0,l.Uk)(" Clientes entregas "),z=(0,l.Uk)("Asignar preventista"),D=(0,l.Uk)(" Modifica al preventista "),I=(0,l.Uk)("Monitoreo"),L=(0,l.Uk)(" Monitoreo "),M=(0,l.Uk)("Salir"),O=(0,l.Uk)(" Salir del sistema ");function H(e,a,t,H,P,R){const V=(0,l.up)("q-btn"),S=(0,l.up)("q-toolbar-title"),T=(0,l.up)("q-toolbar"),A=(0,l.up)("q-header"),E=(0,l.up)("q-item-label"),F=(0,l.up)("q-icon"),N=(0,l.up)("q-item-section"),Y=(0,l.up)("q-item"),B=(0,l.up)("q-list"),G=(0,l.up)("q-drawer"),J=(0,l.up)("router-view"),K=(0,l.up)("q-page-container"),X=(0,l.up)("q-layout");return(0,l.wg)(),(0,l.j4)(X,{view:"lHh Lpr lFf"},{default:(0,l.w5)((()=>[(0,l.Wm)(A,{elevated:""},{default:(0,l.w5)((()=>[(0,l.Wm)(T,null,{default:(0,l.w5)((()=>[(0,l.Wm)(V,{flat:"",dense:"",round:"",icon:"menu","aria-label":"Menu",onClick:R.toggleLeftDrawer},null,8,["onClick"]),(0,l.Wm)(S,null,{default:(0,l.w5)((()=>[void 0==e.$store.getters["login/user"].Nombre1?((0,l.wg)(),(0,l.iD)(l.HY,{key:0},[u],64)):((0,l.wg)(),(0,l.iD)(l.HY,{key:1},[(0,l.Uk)((0,o.zw)(e.$store.getters["login/user"].Nombre1)+" "+(0,o.zw)(e.$store.getters["login/user"].App1),1)],64))])),_:1}),n])),_:1})])),_:1}),(0,l.Wm)(G,{modelValue:P.leftDrawerOpen,"onUpdate:modelValue":a[0]||(a[0]=e=>P.leftDrawerOpen=e),"show-if-above":"",bordered:""},{default:(0,l.w5)((()=>[(0,l.Wm)(B,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,{header:""},{default:(0,l.w5)((()=>[r])),_:1}),(0,l.Wm)(Y,{clickable:"",exact:"",to:"/"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"home"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[i])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[d])),_:1})])),_:1})])),_:1}),e.$store.getters["login/isLoggedIn"]?(0,l.kq)("",!0):((0,l.wg)(),(0,l.j4)(Y,{key:0,exact:"",to:"login"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"login"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[s])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[m])),_:1})])),_:1})])),_:1})),P.vendores.includes(e.$store.getters["login/user"].ci)?((0,l.wg)(),(0,l.j4)(Y,{key:1,clickable:"",exact:"",to:"visita"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"map"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[c])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[w])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),P.vendores.includes(e.$store.getters["login/user"].ci)?((0,l.wg)(),(0,l.j4)(Y,{key:2,clickable:"",exact:"",to:"mispedidos"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"list"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[f])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[_])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),"7329536"==e.$store.getters["login/user"].ci?((0,l.wg)(),(0,l.j4)(Y,{key:3,clickable:"",exact:"",to:"clientes"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"people"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[k])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[W])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),"7329536"==e.$store.getters["login/user"].ci?((0,l.wg)(),(0,l.j4)(Y,{key:4,clickable:"",exact:"",to:"pendientes"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"local_grocery_store"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[g])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[p])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),"7329536"==e.$store.getters["login/user"].ci?((0,l.wg)(),(0,l.j4)(Y,{key:5,clickable:"",exact:"",to:"cobrosrealizados"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"monetization_on"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[b])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[U])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),P.vendores.includes(e.$store.getters["login/user"].ci)?((0,l.wg)(),(0,l.j4)(Y,{key:6,clickable:"",exact:"",to:"cobranza"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"receipt"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[v])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[q])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),P.vendores.includes(e.$store.getters["login/user"].ci)?((0,l.wg)(),(0,l.j4)(Y,{key:7,clickable:"",exact:"",to:"miscobranzas"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"money"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[h])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[y])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),P.encargados.includes(e.$store.getters["login/user"].ci)?((0,l.wg)(),(0,l.j4)(Y,{key:8,clickable:"",exact:"",to:"generar"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"money"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[$])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[x])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),P.despachador.includes(e.$store.getters["login/user"].ci)?((0,l.wg)(),(0,l.j4)(Y,{key:9,clickable:"",exact:"",to:"ruta"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"map"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[C])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[j])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),P.encargados.includes(e.$store.getters["login/user"].ci)?((0,l.wg)(),(0,l.j4)(Y,{key:10,clickable:"",exact:"",to:"reporte"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"list"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[Z])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[Q])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),"1234567"==e.$store.getters["login/user"].ci?((0,l.wg)(),(0,l.j4)(Y,{key:11,clickable:"",exact:"",to:"modifica"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"people"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[z])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[D])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),"1234567"==e.$store.getters["login/user"].ci?((0,l.wg)(),(0,l.j4)(Y,{key:12,clickable:"",exact:"",to:"monitoreo"},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"computer"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[I])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[L])),_:1})])),_:1})])),_:1})):(0,l.kq)("",!0),e.$store.getters["login/isLoggedIn"]?((0,l.wg)(),(0,l.j4)(Y,{key:13,clickable:"",onClick:R.logout},{default:(0,l.w5)((()=>[(0,l.Wm)(N,{avatar:""},{default:(0,l.w5)((()=>[(0,l.Wm)(F,{name:"logout"})])),_:1}),(0,l.Wm)(N,null,{default:(0,l.w5)((()=>[(0,l.Wm)(E,null,{default:(0,l.w5)((()=>[M])),_:1}),(0,l.Wm)(E,{caption:""},{default:(0,l.w5)((()=>[O])),_:1})])),_:1})])),_:1},8,["onClick"])):(0,l.kq)("",!0)])),_:1})])),_:1},8,["modelValue"]),(0,l.Wm)(K,null,{default:(0,l.w5)((()=>[(0,l.Wm)(J)])),_:1})])),_:1})}const P={data(){return{leftDrawerOpen:!1,vendores:["12612870","1593578","33555433","3520335","5676554","7422201","9876785","7360035"],encargados:["0","123321","22222222","2754612"],despachador:["7386961","9688418","2754612","7417239","12810781"]}},methods:{validar(){return this.vendores.includes()},toggleLeftDrawer(){this.leftDrawerOpen=!this.leftDrawerOpen},logout(){this.$q.loading.show(),this.$store.dispatch("login/logout").then((()=>{this.$q.loading.hide(),this.$router.push("/login")}))}}};var R=t(4260),V=t(4267),S=t(3812),T=t(9570),A=t(8240),E=t(3747),F=t(4842),N=t(6873),Y=t(7011),B=t(2350),G=t(3414),J=t(2035),K=t(4554),X=t(2652),ee=t(7518),ae=t.n(ee);const te=(0,R.Z)(P,[["render",H]]),le=te;ae()(P,"components",{QLayout:V.Z,QHeader:S.Z,QToolbar:T.Z,QBtn:A.Z,QToolbarTitle:E.Z,QInput:F.Z,QDrawer:N.Z,QList:Y.Z,QItemLabel:B.Z,QItem:G.Z,QItemSection:J.Z,QIcon:K.Z,QPageContainer:X.Z})}}]);