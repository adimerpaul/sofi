/*
export function someGetter (state) {
}
*/
export function isLoggedIn (state) {
  return !!state.token
}

export function authStatus (state) {
  return state.status
}
export function user (state) {
  return state.user
}
export function permisos (state) {
  return state.permisos
}
export function can (state) {
  return (permiso) => state.permisos.includes(permiso)
}
