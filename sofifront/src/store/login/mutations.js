/*
export function someMutation (state) {
}
*/
export function auth_request(state){
  state.status = 'loading'
}
export function auth_success(state, data){
  state.status = 'success'
  state.token = data.token
  state.user = data.user
  // state.usuarios=data.user.permisos.find(p=>p.id===1)!=undefined
  // state.clientes=data.user.permisos.find(p=>p.id===2)!=undefined
  // state.productos=data.user.permisos.find(p=>p.id===3)!=undefined
  // state.inventario=data.user.permisos.find(p=>p.id===4)!=undefined
  // state.ventadetalle=data.user.permisos.find(p=>p.id===5)!=undefined
  // state.historialventadetalle=data.user.permisos.find(p=>p.id===6)!=undefined
  // state.ventalocal=data.user.permisos.find(p=>p.id===7)!=undefined
  // state.historialventalocal=data.user.permisos.find(p=>p.id===8)!=undefined
  // state.empleados=data.user.permisos.find(p=>p.id===9)!=undefined
  // state.reportes=data.user.permisos.find(p=>p.id===10)!=undefined
  // state.gastos=data.user.permisos.find(p=>p.id===11)!=undefined
  // state.historialprestamo=data.user.permisos.find(p=>p.id===12)!=undefined
  // state.historialventa=data.user.permisos.find(p=>p.id===13)!=undefined
  // state.anularventa=data.user.permisos.find(p=>p.id===14)!=undefined
  // state.ruta=data.user.permisos.find(p=>p.id===15)!=undefined
  // state.anularprestamo=data.user.permisos.find(p=>p.id===16)!=undefined
  // state.reimpresion=data.user.permisos.find(p=>p.id===17)!=undefined
  // state.eliminargasto=data.user.permisos.find(p=>p.id===18)!=undefined
  // state.cobrardetalle=data.user.permisos.find(p=>p.id===19)!=undefined
  // state.cobrarlocal=data.user.permisos.find(p=>p.id===20)!=undefined
  // state.cobrarruta=data.user.permisos.find(p=>p.id===21)!=undefined
}
export function   auth_error(state){
  state.status = 'error'
}
export function salir(state){
  state.status = ''
  state.token = ''
  state.user = {}
  // state.usuarios=false
  // state.clientes=false
  // state.productos=false
  // state.inventario=false
  // state.ventadetalle=false
  // state.historialventadetalle=false
  // state.ventalocal=false
  // state.historialventalocal=false
  // state.empleados=false
  // state.reportes=false
  // state.gastos=false
  // state.historialprestamo=false
  // state.historialventa=false
  // state.anularventa=false
  // state.ruta=false
  // state.anularprestamo=false
  // state.reimpresion=false
  // state.eliminargasto=false
  // state.cobrardetalle=false
  // state.cobrarlocal=false
  // state.cobrarruta=false
}
