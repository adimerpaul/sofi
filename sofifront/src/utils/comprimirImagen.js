// Achica una foto en el navegador antes de subirla: la del celular pesa 3-6 MB
// y asi viaja en unos cientos de KB. El backend la vuelve a pasar a WebP, asi
// que si el navegador no puede (formato raro, sin WebP) se manda la original.
export async function comprimirImagen (archivo, lado = 1600, calidad = 0.75) {
  if (!archivo || !String(archivo.type).startsWith('image/')) return archivo
  try {
    // createImageBitmap respeta la orientacion EXIF: la foto no sale girada.
    const bitmap = await createImageBitmap(archivo)
    const escala = Math.min(1, lado / Math.max(bitmap.width, bitmap.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(bitmap.width * escala)
    canvas.height = Math.round(bitmap.height * escala)
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height)
    bitmap.close && bitmap.close()

    let blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/webp', calidad))
    // Safari viejo no genera WebP y devuelve PNG (mas pesado): ahi va JPEG.
    if (!blob || blob.type !== 'image/webp') {
      blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', calidad))
    }
    if (!blob || blob.size >= archivo.size) return archivo

    const extension = blob.type === 'image/webp' ? '.webp' : '.jpg'
    const nombre = String(archivo.name || 'foto').replace(/\.[^.]+$/, '') + extension
    return new File([blob], nombre, {type: blob.type})
  } catch (e) {
    return archivo
  }
}

export function comprimirImagenes (archivos, lado, calidad) {
  return Promise.all(Array.from(archivos || []).map(f => comprimirImagen(f, lado, calidad)))
}
