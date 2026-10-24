<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md">
        <div class="text-h6 text-weight-bold">Productos</div>
        <div class="text-caption text-grey-7">Catálogo con precios y stock disponible</div>
      </div>
      <div class="col-auto">
        <q-chip square dense color="primary" text-color="white" icon="inventory_2">
          <span class="text-caption">Resultados:</span>&nbsp;<b>{{ pagination.rowsNumber }}</b>
        </q-chip>
      </div>
      <div class="col-auto">
        <q-btn
          dense unelevated no-caps color="indigo" icon="swap_vert"
          label="Entradas y salidas" @click="abrirEntradasSalidas(null)"
        >
          <q-tooltip>Movimientos de stock de todos los productos, con Excel</q-tooltip>
        </q-btn>
      </div>
      <div class="col-auto">
        <q-btn
          dense unelevated no-caps color="orange-8" icon="sync"
          label="Actualizar pollos" :loading="actualizandoPollos"
          @click="actualizarPollos"
        />
      </div>
      <div class="col-auto">
        <q-btn
          dense unelevated no-caps color="primary" icon="add"
          label="Nuevo producto" @click="abrirCreacion"
        />
      </div>
    </div>

    <q-card flat bordered class="q-pa-sm q-mb-md">
      <div class="row q-col-gutter-sm">
        <div class="col-12 col-md-3">
          <q-input
            v-model="filtros.search"
            dense outlined clearable debounce="400"
            label="Buscar por código o nombre"
            @update:model-value="recargar"
          >
            <template v-slot:append><q-icon name="search"/></template>
          </q-input>
        </div>
        <div class="col-12 col-sm-6 col-md-2">
          <q-select
            v-model="filtros.tipo"
            dense outlined clearable emit-value map-options
            label="Tipo"
            :options="opcionesTipo"
            @update:model-value="recargar"
          />
        </div>
        <div class="col-12 col-sm-6 col-md-2">
          <q-select
            v-model="filtros.grupo"
            dense outlined clearable emit-value map-options
            label="Grupo"
            :options="grupos"
            @update:model-value="recargar"
          />
        </div>
        <div class="col-12 col-sm-6 col-md-2">
          <q-select
            v-model="filtros.unidad"
            dense outlined clearable
            label="Unidad"
            :options="unidades"
            @update:model-value="recargar"
          />
        </div>
        <div class="col-12 col-md-3 row items-center q-gutter-x-md">
          <q-toggle
            v-model="filtros.conStock"
            dense label="Solo con stock" color="primary"
            @update:model-value="recargar"
          />
          <q-toggle
            v-model="filtros.incluirInactivos"
            dense label="Ver eliminados" color="grey-7"
            @update:model-value="recargar"
          />
        </div>
      </div>
    </q-card>

    <q-table
      flat bordered dense
      class="tabla-compacta"
      :rows="productos"
      :columns="columns"
      row-key="CodAut"
      v-model:pagination="pagination"
      :loading="loading"
      :visible-columns="visibleColumns"
      :rows-per-page-options="[15, 30, 50, 100, 0]"
      binary-state-sort
      @request="onRequest"
    >
      <template v-slot:top-right>
        <q-btn-dropdown
          dense unelevated no-caps color="green-8" icon="table_chart"
          label="Excel" class="q-mr-sm" :loading="exportando === 'excel'"
        >
          <q-list dense>
            <q-item clickable v-close-popup @click="exportar('excel')">
              <q-item-section avatar><q-icon name="inventory_2"/></q-item-section>
              <q-item-section>
                <q-item-label>Exportar productos</q-item-label>
                <q-item-label caption>Catálogo con precios y stock</q-item-label>
              </q-item-section>
            </q-item>
            <q-item clickable v-close-popup @click="exportar('excel', true)">
              <q-item-section avatar><q-icon name="warehouse"/></q-item-section>
              <q-item-section>
                <q-item-label>Productos para almacén</q-item-label>
                <q-item-label caption>Para cargar en Almacén (con GRUPOS)</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>
        <q-btn
          dense unelevated no-caps color="red-7" icon="picture_as_pdf"
          label="PDF" class="q-mr-sm" :loading="exportando === 'pdf'"
          @click="exportar('pdf')"
        />
        <q-select
          v-model="visibleColumns"
          multiple dense outlined options-dense emit-value map-options
          display-value="Columnas"
          :options="columnasOpcionales"
          option-value="name"
          style="min-width: 140px"
        />
      </template>

      <template v-slot:body-cell-cantidad="props">
        <q-td :props="props" class="text-right">
          <q-badge :color="Number(props.value) > 0 ? 'green-6' : 'grey-5'" text-color="white">
            {{ num(props.value) }}
          </q-badge>
        </q-td>
      </template>

      <template v-slot:body-cell-tipo_nombre="props">
        <q-td :props="props" class="text-center">
          <q-badge v-if="props.value" :color="props.row.tipo_color || 'primary'" :label="props.value"/>
        </q-td>
      </template>

      <!-- Trozado: solo se ve el check, el texto 'SI'/'NO' queda en la tabla. -->
      <template v-slot:body-cell-trozado="props">
        <q-td :props="props" class="text-center">
          <q-icon
            :name="esTrozado(props.row) ? 'check_box' : 'check_box_outline_blank'"
            :color="esTrozado(props.row) ? 'primary' : 'grey-5'" size="18px"
          />
        </q-td>
      </template>

      <!-- Lo eliminado (solo se ve con "Ver eliminados") va tachado y marcado. -->
      <template v-slot:body-cell-Producto="props">
        <q-td :props="props" :class="{ 'producto-eliminado': esEliminado(props.row) }">
          {{ props.value }}
          <q-badge v-if="esEliminado(props.row)" color="negative" class="q-ml-xs" label="ELIMINADO">
            <q-tooltip v-if="props.row.deleted_at">Eliminado el {{ fechaHora(props.row.deleted_at) }}</q-tooltip>
          </q-badge>
        </q-td>
      </template>

      <!-- Opciones: primera columna para que no queden fuera de pantalla. -->
      <template v-slot:body-cell-acciones="props">
        <q-td :props="props" style="white-space: nowrap">
          <q-btn-dropdown
            color="primary" size="sm" dense no-caps icon="menu" label="Opciones"
            :loading="subiendo === props.row.cod_prod"
          >
            <q-list dense style="min-width: 190px">
              <q-item clickable v-close-popup @click="abrirEdicion(props.row)">
                <q-item-section avatar><q-icon name="edit" color="primary"/></q-item-section>
                <q-item-section>Actualizar datos</q-item-section>
              </q-item>

              <q-item clickable v-close-popup @click="elegirImagen(props.row)">
                <q-item-section avatar><q-icon name="photo_camera" color="teal"/></q-item-section>
                <q-item-section>
                  {{ props.row.imagen ? 'Cambiar imagen' : 'Subir imagen' }}
                </q-item-section>
              </q-item>

              <q-item
                clickable v-close-popup
                :disable="!props.row.imagen"
                @click="quitarImagen(props.row)"
              >
                <q-item-section avatar><q-icon name="hide_image" color="orange-8"/></q-item-section>
                <q-item-section>Quitar imagen</q-item-section>
              </q-item>

              <q-item clickable v-close-popup @click="abrirEntradasSalidas(props.row)">
                <q-item-section avatar><q-icon name="swap_vert" color="indigo"/></q-item-section>
                <q-item-section>
                  Entradas y salidas
                  <q-item-label caption>Movimientos de stock, con Excel</q-item-label>
                </q-item-section>
              </q-item>

              <q-separator/>

              <!-- Eliminar es borrado logico: el eliminado se puede restaurar. -->
              <q-item v-if="esEliminado(props.row)" clickable v-close-popup @click="restaurar(props.row)">
                <q-item-section avatar><q-icon name="restore_from_trash" color="positive"/></q-item-section>
                <q-item-section>
                  Restaurar
                  <q-item-label caption>Vuelve al catálogo</q-item-label>
                </q-item-section>
              </q-item>
              <q-item v-else clickable v-close-popup @click="eliminar(props.row)">
                <q-item-section avatar><q-icon name="delete" color="negative"/></q-item-section>
                <q-item-section>
                  Eliminar
                  <q-item-label caption>Se puede restaurar después</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
          </q-btn-dropdown>
        </q-td>
      </template>

      <!-- Clic o arrastrar una imagen encima para cambiar la foto. -->
      <template v-slot:body-cell-imagen="props">
        <q-td :props="props" class="text-center">
          <div
            class="foto-zona"
            :class="{ 'foto-zona--activa': arrastrando === props.row.cod_prod }"
            @click="elegirImagen(props.row)"
            @dragenter.prevent.stop="arrastrando = props.row.cod_prod"
            @dragover.prevent.stop="arrastrando = props.row.cod_prod"
            @dragleave.prevent.stop="arrastrando = null"
            @drop.prevent.stop="soltarImagen($event, props.row)"
          >
            <img v-if="props.row.imagen" :src="urlImagen(props.row.imagen)" alt=""/>
            <q-icon v-else name="add_a_photo" size="20px" color="grey-6"/>

            <q-inner-loading :showing="subiendo === props.row.cod_prod">
              <q-spinner size="18px" color="primary"/>
            </q-inner-loading>

            <q-tooltip>
              {{ props.row.imagen ? 'Clic o arrastra una imagen para cambiarla' : 'Clic o arrastra una imagen aquí' }}
            </q-tooltip>
          </div>

          <q-btn
            v-if="props.row.imagen"
            dense flat round size="xs" icon="close" color="negative"
            @click.stop="quitarImagen(props.row)"
          >
            <q-tooltip>Quitar foto</q-tooltip>
          </q-btn>
        </q-td>
      </template>

      <template v-slot:no-data>
        <div class="full-width row flex-center q-pa-md text-grey-7">
          <q-icon name="inventory_2" size="20px" class="q-mr-sm"/>
          No se encontraron productos con esos filtros
        </div>
      </template>
    </q-table>

    <!-- Selector de archivo oculto: lo dispara la miniatura de la tabla. -->
    <input
      ref="fileImagen"
      type="file"
      accept="image/*"
      class="hidden"
      @change="subirImagen"
    />

    <q-dialog v-model="dialogEditar">
      <q-card style="width: 640px; max-width: 96vw">
        <q-card-section class="bg-primary text-white q-py-sm">
          <div class="text-subtitle1 text-weight-bold">{{ creando ? 'Nuevo producto' : 'Actualizar producto' }}</div>
          <div class="text-caption">{{ creando ? 'Producto nuevo' : edicion.cod_prod }}</div>
        </q-card-section>

        <q-card-section class="row q-col-gutter-sm">
          <!-- El codigo solo se elige al dar de alta: cambiarlo despues dejaria
               huerfanas las lineas de tbventas que lo referencian. -->
          <q-input
            v-if="creando"
            v-model.trim="edicion.cod_prod" outlined dense class="col-12 col-sm-4"
            label="Código" hint="Vacío = se asigna el siguiente"
          />
          <q-input
            v-model.trim="edicion.Producto" outlined dense :class="creando ? 'col-12 col-sm-8' : 'col-12'"
            label="Producto" :rules="[v => !!v || 'El nombre es obligatorio']"
          />
          <q-input v-model.trim="edicion.Nomcomer" outlined dense class="col-12" label="Nombre comercial"/>

          <div class="col-12 col-sm-6 row no-wrap items-start">
            <q-select
              v-model="edicion.cod_grup" outlined dense clearable emit-value map-options
              use-input fill-input hide-selected input-debounce="0"
              class="col" :label="creando ? 'Grupo *' : 'Grupo'"
              :options="gruposFiltrados" @filter="filtrarGrupos" @new-value="nuevoGrupo"
            >
              <template #option="scope">
                <q-item v-bind="scope.itemProps" dense>
                  <q-item-section>{{ scope.opt.label }}</q-item-section>
                  <q-item-section side class="text-caption">{{ scope.opt.value }}</q-item-section>
                </q-item>
              </template>
            </q-select>
            <q-btn unelevated color="primary" icon="add" class="q-ml-xs" style="height: 40px" @click="abrirNuevoGrupo('')">
              <q-tooltip>Agregar grupo</q-tooltip>
            </q-btn>
          </div>
          <q-select
            v-model="edicion.codUnid" outlined dense clearable
            class="col-6 col-sm-3" :label="creando ? 'Unidad *' : 'Unidad'" :options="unidades"
          />
          <!-- Tipo administrable: el backend copia su "forma de pedido" a
               tbproductos.tipo, que es lo que usan pedidos y facturacion. -->
          <div class="col-6 col-sm-3 row no-wrap items-start">
            <q-select
              v-model="edicion.tipo_producto_id" outlined dense emit-value map-options
              class="col" label="Tipo" :options="opcionesTipo"
            >
              <template #option="scope">
                <q-item v-bind="scope.itemProps" dense>
                  <q-item-section avatar style="min-width: 0">
                    <q-badge rounded :color="scope.opt.color" style="width: 10px; height: 10px; padding: 0"/>
                  </q-item-section>
                  <q-item-section>{{ scope.opt.label }}</q-item-section>
                  <q-item-section side class="text-caption">{{ scope.opt.modo }}</q-item-section>
                </q-item>
              </template>
            </q-select>
            <q-btn unelevated color="primary" icon="tune" class="q-ml-xs" style="height: 40px; padding: 0 6px" @click="abrirTipos">
              <q-tooltip>Administrar tipos</q-tooltip>
            </q-btn>
          </div>

          <!-- Lo trozado se entrega en piezas: en la boleta la columna Cant
               sale con un guion en vez del numero. -->
          <q-checkbox v-model="edicion.trozado" class="col-12" label="Trozado (en la impresión no se muestra la cantidad)"/>

          <!-- Stock y compra: la existencia se corrige a mano; la venta y la
               compra la siguen moviendo despues. -->
          <q-input
            v-model.number="edicion.stock_actual" outlined dense type="number" step="0.001"
            class="col-6 col-sm-4" label="Stock" bg-color="green-1"
            :suffix="String(edicion.codUnid || '').toLowerCase()"
          />
          <q-input
            v-model.number="edicion.precio_compra" outlined dense type="number" step="0.01" min="0"
            class="col-6 col-sm-4" label="Precio de compra" prefix="Bs" bg-color="green-1"
            @update:model-value="v => { if (v === '') edicion.precio_compra = null }"
          />
          <q-input v-model.number="edicion.precioAprox" outlined dense type="number" step="0.01" min="0" class="col-6 col-sm-4" label="Precio aprox." prefix="Bs"/>

          <div class="col-12 text-caption text-grey-7 q-mt-xs">Precios</div>
          <q-input v-model.number="edicion.Precio" outlined dense type="number" step="0.01" min="0" class="col-6 col-sm-3" label="Precio 1" prefix="Bs"/>
          <q-input v-model.number="edicion.Precio_Costo" outlined dense type="number" step="0.01" min="0" class="col-6 col-sm-3" label="Precio costo" prefix="Bs"/>
          <q-input
            v-for="n in [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]" :key="n"
            v-model.number="edicion['Precio' + n]" outlined dense type="number" step="0.01" min="0"
            class="col-6 col-sm-3" :label="'Precio ' + n" prefix="Bs"
          />
        </q-card-section>

        <q-separator/>
        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense no-caps label="Cancelar" v-close-popup/>
          <q-btn
            color="positive" dense unelevated no-caps icon="save" label="Guardar"
            :disable="!edicion.Producto || (creando && (!edicion.cod_grup || !edicion.codUnid))"
            :loading="guardando" @click="guardarEdicion"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Grupo nuevo: el codigo lo arma el backend con el del grupo padre. -->
    <q-dialog v-model="dialogGrupo">
      <q-card style="width: 360px; max-width: 92vw">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Agregar grupo</div>
          <q-space/>
          <q-btn icon="close" flat round dense v-close-popup/>
        </q-card-section>
        <q-form @submit="crearGrupo">
          <q-card-section class="q-gutter-sm">
            <q-input
              v-model="nuevo.descripcion" outlined dense autofocus label="Nombre del grupo *" maxlength="50"
              :rules="[v => !!(v || '').trim() || 'Escribí el nombre']" hide-bottom-space
              @update:model-value="v => { nuevo.descripcion = (v || '').toUpperCase() }"
            />
            <q-select
              v-model="nuevo.padre" outlined dense emit-value map-options label="Grupo padre *"
              :options="padres" :option-label="p => p.label + ' (' + p.value + ')'"
              :rules="[v => !!v || 'Elegí el grupo padre']" hide-bottom-space
            />
            <div class="text-caption text-grey-7">El código sale del grupo padre (ej. del 3 con 31 y 32 → 33).</div>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn flat no-caps label="Cancelar" v-close-popup/>
            <q-btn unelevated no-caps color="primary" icon="add" label="Agregar" type="submit" :loading="creandoGrupo"/>
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <!-- Tipos de producto: se ven como chips en el pedido de Visita. La forma
         de pedido decide que formulario se abre al pedir (piezas de pollo,
         cerdo, res) o si se pide solo por cantidad (NORMAL). -->
    <q-dialog v-model="tipos.abierto">
      <q-card style="width: 520px; max-width: 96vw">
        <q-card-section class="bg-primary text-white q-py-sm row items-center">
          <div class="text-subtitle1 text-weight-bold">Tipos de producto</div>
          <q-space/>
          <q-btn icon="close" flat round dense v-close-popup/>
        </q-card-section>

        <q-list separator dense>
          <q-item v-for="t in tiposProducto" :key="t.id">
            <q-item-section avatar>
              <q-badge :color="t.color" :label="t.nombre"/>
            </q-item-section>
            <q-item-section>
              <q-item-label caption>Pedido: {{ modoTexto(t.modo) }} · {{ t.productos }} productos</q-item-label>
            </q-item-section>
            <q-item-section side class="row no-wrap">
              <div>
                <q-btn flat round dense size="sm" icon="edit" color="primary" @click="editarTipo(t)"/>
                <q-btn flat round dense size="sm" icon="delete" color="negative" :disable="t.productos > 0" @click="borrarTipo(t)">
                  <q-tooltip v-if="t.productos > 0">Lo usan productos: no se puede borrar</q-tooltip>
                </q-btn>
              </div>
            </q-item-section>
          </q-item>
        </q-list>

        <q-separator/>
        <q-form @submit="guardarTipo">
          <q-card-section class="q-gutter-sm">
            <div class="text-caption text-grey-7">{{ tipos.form.id ? 'Editar ' + tipos.form.original : 'Nuevo tipo' }}</div>
            <div class="row q-col-gutter-sm">
              <q-input
                v-model="tipos.form.nombre" outlined dense class="col-12 col-sm-5" label="Nombre *" maxlength="30"
                :rules="[v => !!(v || '').trim() || 'Escribí el nombre']" hide-bottom-space
                @update:model-value="v => { tipos.form.nombre = (v || '').toUpperCase() }"
              />
              <q-select
                v-model="tipos.form.modo" outlined dense emit-value map-options class="col-7 col-sm-4"
                label="Forma de pedido" :options="modos"
              />
              <q-select
                v-model="tipos.form.color" outlined dense emit-value map-options class="col-5 col-sm-3"
                label="Color" :options="colores"
              >
                <template #selected>
                  <q-badge :color="tipos.form.color" :label="tipos.form.nombre || 'ABC'"/>
                </template>
                <template #option="scope">
                  <q-item v-bind="scope.itemProps" dense>
                    <q-item-section><q-badge :color="scope.opt.value" :label="scope.opt.label"/></q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>
            <div v-if="tipos.form.id" class="text-caption text-orange-9">
              Cambiar la forma de pedido cambia el formulario de todos los productos de este tipo.
            </div>
          </q-card-section>
          <q-card-actions align="right">
            <q-btn v-if="tipos.form.id" flat no-caps label="Cancelar edición" @click="limpiarTipo"/>
            <q-btn
              unelevated no-caps color="primary" type="submit" :loading="tipos.guardando"
              :icon="tipos.form.id ? 'save' : 'add'" :label="tipos.form.id ? 'Guardar' : 'Agregar'"
            />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <!-- Entradas y salidas de inventario, en el formato de la planilla de
         caja: de un producto (menu Opciones) o de todos (boton de arriba). -->
    <q-dialog v-model="es.abierto" maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="column no-wrap">
        <q-card-section class="bg-primary text-white q-py-sm row items-center no-wrap">
          <q-icon name="swap_vert" size="22px" class="q-mr-sm"/>
          <div class="col">
            <div class="text-subtitle1 text-weight-bold">Entradas y salidas</div>
            <div class="text-caption ellipsis">
              {{ es.producto ? es.producto.cod_prod + ' · ' + es.producto.Producto : 'Todos los productos que se movieron en el rango' }}
            </div>
          </div>
          <q-btn icon="close" flat round dense v-close-popup/>
        </q-card-section>

        <q-card-section class="q-pa-sm">
          <div class="row items-center q-gutter-sm">
            <q-input v-model="es.desde" type="date" dense outlined label="Desde" style="width: 160px"
                     @update:model-value="cargarEntradasSalidas"/>
            <q-input v-model="es.hasta" type="date" dense outlined label="Hasta" style="width: 160px"
                     @update:model-value="cargarEntradasSalidas"/>
            <q-btn color="primary" icon="refresh" label="Actualizar" no-caps unelevated :loading="es.cargando"
                   @click="cargarEntradasSalidas"/>
            <q-btn color="green-8" icon="table_chart" label="Exportar Excel" no-caps unelevated
                   :loading="es.exportando" :disable="!es.datos.movimientos.length" @click="exportarEntradasSalidas"/>
            <q-space/>
            <q-input v-model="es.buscar" dense outlined clearable debounce="300" style="width: 260px"
                     placeholder="Código, producto, comanda, motivo…">
              <template v-slot:append><q-icon name="search"/></template>
            </q-input>
          </div>

          <div class="row q-col-gutter-sm q-mt-xs">
            <div v-if="!es.producto" class="col-6 col-sm">
              <q-card flat bordered class="tarjeta-es">
                <div class="tarjeta-es-titulo">Productos</div>
                <div class="tarjeta-es-valor">{{ es.datos.totales.productos }}</div>
              </q-card>
            </div>
            <div class="col-6 col-sm">
              <q-card flat bordered class="tarjeta-es">
                <div class="tarjeta-es-titulo">Movimientos</div>
                <div class="tarjeta-es-valor">{{ es.datos.totales.movimientos }}</div>
              </q-card>
            </div>
            <div class="col-6 col-sm">
              <q-card flat bordered class="tarjeta-es">
                <div class="tarjeta-es-titulo">Entradas</div>
                <div class="tarjeta-es-valor text-green-9">{{ num(es.datos.totales.entradas) }}</div>
              </q-card>
            </div>
            <div class="col-6 col-sm">
              <q-card flat bordered class="tarjeta-es">
                <div class="tarjeta-es-titulo">Salidas</div>
                <div class="tarjeta-es-valor text-negative">{{ num(es.datos.totales.salidas) }}</div>
              </q-card>
            </div>
            <div v-if="es.producto" class="col-6 col-sm">
              <q-card flat bordered class="tarjeta-es">
                <div class="tarjeta-es-titulo">Existencia final</div>
                <div class="tarjeta-es-valor text-blue-9">{{ num(existenciaFinalEs) }}</div>
                <div class="tarjeta-es-sub">Stock del sistema: {{ num(es.datos.stock_sistema) }}</div>
              </q-card>
            </div>
          </div>
        </q-card-section>

        <q-card-section class="col q-pa-sm q-pt-none" style="min-height: 0">
          <q-table
            class="tabla-es" flat bordered dense virtual-scroll
            :rows="es.datos.movimientos" :columns="columnasEs" :filter="es.buscar"
            :rows-per-page-options="[0]" row-key="_k" :loading="es.cargando"
            no-data-label="Sin entradas ni salidas en este rango"
          >
            <template v-slot:body="props">
              <q-tr :props="props" :class="{ 'fila-saldo': props.row.saldo }">
                <q-td v-for="col in props.cols" :key="col.name" :props="props"
                      :class="{
                        'text-green-9': col.name === 'entrada' && props.row.entrada > 0 && !props.row.saldo,
                        'text-negative': col.name === 'salida' && props.row.salida > 0 && !props.row.saldo,
                        'text-weight-bold': col.name === 'existencia'
                      }">
                  {{ col.value }}
                </q-td>
              </q-tr>
            </template>
          </q-table>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script>
import { date } from 'quasar'

export default {
  name: 'ProductosPage',
  data () {
    const hoy = date.formatDate(Date.now(), 'YYYY-MM-DD')
    return {
      // Entradas y salidas: producto null = todos.
      es: {
        abierto: false,
        producto: null,
        desde: hoy,
        hasta: hoy,
        buscar: '',
        cargando: false,
        exportando: false,
        datos: { movimientos: [], totales: { productos: 0, movimientos: 0, entradas: 0, salidas: 0 }, stock_sistema: 0 }
      },
      columnasEs: [
        { name: 'cod_prod', label: 'Código', field: 'cod_prod', align: 'left' },
        { name: 'producto', label: 'Producto', field: 'producto', align: 'left' },
        { name: 'fecha', label: 'Fecha registro', field: 'fecha', align: 'left', format: v => this.fechaHora(v) },
        { name: 'entrada', label: 'Entrada', field: 'entrada', align: 'right', format: v => this.num(v) },
        { name: 'salida', label: 'Salida', field: 'salida', align: 'right', format: v => this.num(v) },
        { name: 'motivo', label: 'Motivo ingre/egre', field: 'motivo', align: 'left' },
        { name: 'existencia', label: 'Existencia', field: 'existencia', align: 'right', format: v => this.num(v) },
        { name: 'comanda', label: 'Nro comanda', field: 'comanda', align: 'right', format: v => v || '' },
        { name: 'factura', label: 'Nro factura', field: 'factura', align: 'right', format: v => v || '' },
        { name: 'cliente', label: 'Cliente', field: 'cliente', align: 'left' },
        { name: 'motivo_stock', label: 'Motivo ingr. stock', field: 'motivo_stock', align: 'left' }
      ],
      productos: [],
      tiposProducto: [],
      tipos: {
        abierto: false,
        guardando: false,
        form: { id: null, original: '', nombre: '', modo: 'NORMAL', color: 'primary' }
      },
      modos: [
        { value: 'NORMAL', label: 'Por cantidad' },
        { value: 'POLLO', label: 'Piezas de pollo' },
        { value: 'CERDO', label: 'Piezas de cerdo' },
        { value: 'RES', label: 'Piezas de res' }
      ],
      colores: ['primary', 'orange-8', 'pink-6', 'red-9', 'brown-6', 'amber-9', 'teal-6', 'green-7', 'blue-7', 'purple-6', 'indigo-6', 'cyan-8', 'grey-7']
        .map(c => ({ value: c, label: c })),
      grupos: [],
      padres: [],
      dialogGrupo: false,
      creandoGrupo: false,
      nuevo: { descripcion: '', padre: null },
      unidades: [],
      loading: false,
      filtros: {
        search: '',
        grupo: null,
        tipo: null,
        unidad: null,
        conStock: false,
        incluirInactivos: false
      },
      pagination: {
        sortBy: 'Producto',
        descending: false,
        page: 1,
        rowsPerPage: 15,
        rowsNumber: 0
      },
      // Precio3..Precio13 quedan ocultas por defecto: son 11 columnas que
      // hacen ilegible la tabla, pero el usuario puede activarlas.
      visibleColumns: ['acciones', 'imagen', 'cod_prod', 'Producto', 'grupo', 'tipo_nombre', 'codUnid', 'trozado', 'precio_compra', 'Precio', 'Precio_Costo', 'precioAprox', 'cantidad'],
      // Producto cuya foto se está subiendo, para el spinner de la miniatura.
      subiendo: null,
      productoImagen: null,
      // Fila sobre la que se está arrastrando una imagen.
      arrastrando: null,
      dialogEditar: false,
      // El mismo diálogo sirve para alta y edición; esto decide cuál.
      creando: false,
      guardando: false,
      edicion: {},
      // Fila de la tabla que se está editando, para refrescarla al guardar.
      filaEditada: null,
      exportando: null,
      actualizandoPollos: false,
      gruposFiltrados: [],
      columns: [
        { name: 'acciones', label: 'Opciones', field: 'acciones', align: 'left' },
        { name: 'imagen', label: 'Foto', field: 'imagen', align: 'center' },
        { name: 'cod_prod', label: 'Código', field: 'cod_prod', align: 'left', sortable: true },
        { name: 'Producto', label: 'Producto', field: 'Producto', align: 'left', sortable: true },
        { name: 'grupo', label: 'Grupo', field: 'grupo', align: 'left', sortable: true },
        { name: 'tipo_nombre', label: 'Tipo', field: 'tipo_nombre', align: 'center' },
        { name: 'codUnid', label: 'Unidad', field: 'codUnid', align: 'center', sortable: true },
        { name: 'trozado', label: 'Trozado', field: 'trozado', align: 'center' },
        // El de la ultima compra; sin compras se ve un guion, no 0.
        { name: 'precio_compra', label: 'P. Compra', field: 'precio_compra', align: 'right', sortable: true, format: v => v === null || v === undefined ? '—' : Number(v).toFixed(2) },
        { name: 'Precio', label: 'Precio', field: 'Precio', align: 'right', sortable: true, format: v => Number(v || 0).toFixed(2) },
        { name: 'Precio_Costo', label: 'P. Costo', field: 'Precio_Costo', align: 'right', format: v => Number(v || 0).toFixed(2) },
        { name: 'precioAprox', label: 'P. Aprox.', field: 'precioAprox', align: 'right', sortable: true, format: v => Number(v || 0).toFixed(2) },
        { name: 'PreCosto', label: 'PreCosto', field: 'PreCosto', align: 'right', sortable: true, format: v => Number(v || 0).toFixed(2) },
        { name: 'cantidad', label: 'Stock', field: 'cantidad', align: 'right', sortable: true }
      ]
    }
  },
  computed: {
    opcionesTipo () {
      return this.tiposProducto.map(t => ({ value: t.id, label: t.nombre, modo: t.modo, color: t.color }))
    },
    columnasOpcionales () {
      return this.columns.map(c => ({ name: c.name, label: c.label }))
    },
    // La existencia de la ultima fila: con cuanto termina el rango.
    existenciaFinalEs () {
      const lista = this.es.datos.movimientos
      return lista.length ? lista[lista.length - 1].existencia : 0
    }
  },
  created () {
    // Precio3..Precio13 se agregan por código para no repetir 11 definiciones.
    for (let i = 3; i <= 13; i++) {
      this.columns.push({
        name: 'Precio' + i,
        label: 'Precio ' + i,
        field: 'Precio' + i,
        align: 'right',
        format: v => Number(v || 0).toFixed(2)
      })
    }
    this.cargarFiltros()
    this.cargarTipos()
    this.onRequest({ pagination: this.pagination })
  },
  methods: {
    cargarTipos () {
      return this.$api.get('tipos-producto').then(res => { this.tiposProducto = res.data })
        .catch(() => {})
    },
    modoTexto (modo) {
      const m = this.modos.find(x => x.value === modo)
      return m ? m.label : modo
    },
    abrirTipos () {
      this.limpiarTipo()
      this.cargarTipos()
      this.tipos.abierto = true
    },
    limpiarTipo () {
      this.tipos.form = { id: null, original: '', nombre: '', modo: 'NORMAL', color: 'primary' }
    },
    editarTipo (t) {
      this.tipos.form = { id: t.id, original: t.nombre, nombre: t.nombre, modo: t.modo, color: t.color }
    },
    guardarTipo () {
      const f = this.tipos.form
      const datos = { nombre: f.nombre.trim(), modo: f.modo, color: f.color }
      const peticion = f.id ? this.$api.put('tipos-producto/' + f.id, datos) : this.$api.post('tipos-producto', datos)
      this.tipos.guardando = true
      peticion.then(res => {
        this.$q.notify({ message: res.data.message, color: 'positive', icon: 'check_circle', position: 'top' })
        // Un tipo recien creado queda elegido en el producto que se edita.
        if (!f.id && this.dialogEditar) this.edicion.tipo_producto_id = res.data.tipo.id
        this.limpiarTipo()
        this.cargarTipos()
        // El nombre o el color se ven en la tabla.
        this.onRequest({ pagination: this.pagination })
      }).catch(err => { this.avisarError(err, 'No se pudo guardar el tipo') })
        .finally(() => { this.tipos.guardando = false })
    },
    borrarTipo (t) {
      this.$q.dialog({
        title: 'Borrar tipo',
        message: '¿Borrar el tipo ' + t.nombre + '?',
        cancel: { flat: true, noCaps: true, label: 'Cancelar' },
        ok: { label: 'Borrar', color: 'negative', noCaps: true, unelevated: true }
      }).onOk(() => {
        this.$api.delete('tipos-producto/' + t.id)
          .then(res => {
            this.$q.notify({ message: res.data.message, color: 'positive', icon: 'check_circle', position: 'top' })
            this.cargarTipos()
          })
          .catch(err => { this.avisarError(err, 'No se pudo borrar el tipo') })
      })
    },
    // producto null = todos los productos que se movieron.
    abrirEntradasSalidas (producto) {
      this.es.producto = producto ? { cod_prod: producto.cod_prod, Producto: producto.Producto } : null
      this.es.buscar = ''
      this.es.datos = { movimientos: [], totales: { productos: 0, movimientos: 0, entradas: 0, salidas: 0 }, stock_sistema: 0 }
      this.es.abierto = true
      this.cargarEntradasSalidas()
    },
    paramsEntradasSalidas () {
      return {
        cod_prod: this.es.producto ? String(this.es.producto.cod_prod).trim() : '',
        desde: this.es.desde,
        hasta: this.es.hasta || this.es.desde
      }
    },
    cargarEntradasSalidas () {
      if (!this.es.desde) return
      this.es.cargando = true
      this.$api.get('reportes/entradas-salidas', { params: this.paramsEntradasSalidas() })
        .then(res => {
          // Clave propia: un mismo producto tiene muchas filas.
          res.data.movimientos.forEach((m, i) => { m._k = i })
          this.es.datos = res.data
        })
        .catch(err => {
          this.$q.notify({ type: 'negative', message: err.response?.data?.message || 'No se pudo cargar el movimiento' })
        })
        .finally(() => { this.es.cargando = false })
    },
    async exportarEntradasSalidas () {
      this.es.exportando = true
      try {
        const params = Object.assign({ formato: 'excel' }, this.paramsEntradasSalidas())
        const res = await this.$api.get('reportes/entradas-salidas', { params, responseType: 'blob' })
        const enlace = document.createElement('a')
        enlace.href = window.URL.createObjectURL(res.data)
        const rango = params.desde === params.hasta ? params.desde : params.desde + '_a_' + params.hasta
        enlace.download = 'entradas_y_salidas_' + (params.cod_prod ? params.cod_prod + '_' : '') + rango + '.xlsx'
        enlace.click()
        window.URL.revokeObjectURL(enlace.href)
      } catch (e) {
        this.$q.notify({ type: 'negative', message: 'No se pudo exportar el Excel' })
      } finally {
        this.es.exportando = false
      }
    },
    fechaHora (valor) {
      return valor ? date.formatDate(String(valor).replace(' ', 'T'), 'DD/MM/YYYY HH:mm') : ''
    },
    num (v) {
      return Number(v || 0).toFixed(2)
    },

    // tbproductos guarda banderas como texto; se acepta lo que ya pueda haber
    // cargado a mano en la tabla ('SI', '1', 'X') y no solo lo que grabamos.
    esTrozado (row) {
      return ['SI', '1', 'X', 'TRUE'].includes(String(row.trozado || '').trim().toUpperCase())
    },

    // Las fotos se sirven desde public/, no desde /api/, de ahi el recorte.
    urlImagen (ruta) {
      return String(this.$url || '').replace(/api\/?$/, '') + ruta
    },

    elegirImagen (row) {
      this.productoImagen = row
      // Se limpia el valor para que elegir la misma foto vuelva a disparar
      // el change.
      this.$refs.fileImagen.value = ''
      this.$refs.fileImagen.click()
    },

    // Arrastrar la imagen desde el explorador sobre la miniatura.
    soltarImagen (evento, row) {
      this.arrastrando = null

      const archivo = evento.dataTransfer?.files?.[0]
      if (!archivo) {
        return
      }

      if (!archivo.type.startsWith('image/')) {
        this.$q.notify({
          message: 'Ese archivo no es una imagen',
          color: 'warning',
          icon: 'info',
          position: 'top'
        })
        return
      }

      this.enviarImagen(archivo, row)
    },

    subirImagen (evento) {
      const archivo = evento.target.files && evento.target.files[0]
      const row = this.productoImagen

      if (!archivo || !row) {
        return
      }

      this.enviarImagen(archivo, row)
    },

    enviarImagen (archivo, row) {
      const datos = new FormData()
      datos.append('imagen', archivo)

      this.subiendo = row.cod_prod

      this.$api.post('productos/' + encodeURIComponent(row.cod_prod) + '/imagen', datos, {
        headers: { 'Content-Type': 'multipart/form-data' }
      }).then(res => {
        row.imagen = res.data.imagen
        this.$q.notify({
          message: res.data.message,
          color: 'positive',
          icon: 'check_circle',
          position: 'top'
        })
      }).catch(err => {
        this.avisarError(err, 'No se pudo subir la imagen')
      }).finally(() => {
        this.subiendo = null
        this.productoImagen = null
      })
    },

    // Los mismos filtros que la tabla, para que la exportación coincida.
    filtrosActuales () {
      return {
        search: this.filtros.search || '',
        grupo: this.filtros.grupo || '',
        tipo_producto_id: this.filtros.tipo || '',
        unidad: this.filtros.unidad || '',
        conStock: this.filtros.conStock ? 1 : 0,
        incluirInactivos: this.filtros.incluirInactivos ? 1 : 0
      }
    },

    actualizarPollos () {
      this.$q.dialog({
        title: 'Actualizar pollos',
        message: 'Los productos 501600, 501601, 501604, 501606, 501704, 502102, 502108, 502106 y 502109 ' +
          'pasarán a tipo POLLO y unidad CAJA. ¿Continuar?',
        cancel: true,
        persistent: true
      }).onOk(() => {
        this.actualizandoPollos = true
        this.$api.post('productos/actualizarPollos').then(res => {
          this.$q.notify({ message: res.data.message, color: 'positive', icon: 'check', position: 'top' })
          this.cargarFiltros()
          this.recargar()
        }).catch(err => {
          this.avisarError(err, 'No se pudieron actualizar los pollos')
        }).finally(() => {
          this.actualizandoPollos = false
        })
      })
    },

    // almacen: el Excel con la columna GRUPOS que se sube en /almacen.
    exportar (formato, almacen = false) {
      this.exportando = formato

      this.$api.get('productos/' + formato, {
        params: Object.assign(this.filtrosActuales(), almacen ? { almacen: 1 } : {}),
        responseType: 'blob'
      }).then(res => {
        const tipo = formato === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        const url = window.URL.createObjectURL(new Blob([res.data], { type: tipo }))

        if (formato === 'pdf') {
          // El PDF se abre para verlo; si el navegador bloquea, se descarga.
          if (window.open(url, '_blank')) {
            setTimeout(() => window.URL.revokeObjectURL(url), 60000)
            return
          }
        }

        const link = document.createElement('a')
        link.href = url
        link.download = (almacen ? 'productos_almacen.' : 'productos.') + (formato === 'pdf' ? 'pdf' : 'xlsx')
        link.click()
        setTimeout(() => window.URL.revokeObjectURL(url), 60000)
      }).catch(err => {
        this.avisarError(err, 'No se pudo exportar')
      }).finally(() => {
        this.exportando = null
      })
    },

    // Eliminado con deleted_at, o dado de baja a la antigua (INACTIVO en el nombre).
    esEliminado (row) {
      return !!row.deleted_at || Number(row.eliminado) === 1 || /inactivo/i.test(row.Producto || '')
    },
    // Para no borrar uno sin querer hay que escribir su codigo. Igual es
    // borrado logico: se puede restaurar desde "Ver eliminados".
    eliminar (row) {
      const codigo = String(row.cod_prod).trim()
      this.$q.dialog({
        title: 'Eliminar producto',
        message: '¿Eliminar "' + row.Producto + '"? Deja de aparecer en el catálogo y en los pedidos; ' +
          'el historial de ventas se conserva y se puede restaurar desde "Ver eliminados".<br><br>' +
          'Para confirmar escriba el código <b>' + codigo + '</b>:',
        html: true,
        prompt: { model: '', type: 'text', isValid: v => String(v).trim() === codigo, outlined: true, dense: true },
        cancel: { flat: true, noCaps: true, label: 'Cancelar' },
        persistent: true,
        ok: { label: 'Eliminar', color: 'negative', noCaps: true, unelevated: true }
      }).onOk(() => {
        this.$api.delete('productos/' + encodeURIComponent(row.cod_prod))
          .then(res => {
            this.$q.notify({
              message: res.data.message,
              color: 'positive',
              icon: 'check_circle',
              position: 'top'
            })
            this.onRequest({ pagination: this.pagination })
          })
          .catch(err => { this.avisarError(err, 'No se pudo eliminar') })
      })
    },

    restaurar (row) {
      this.$q.dialog({
        title: 'Restaurar producto',
        message: '¿Restaurar "' + row.Producto + '"? Vuelve al catálogo y a los pedidos.',
        cancel: { flat: true, noCaps: true, label: 'Cancelar' },
        ok: { label: 'Restaurar', color: 'positive', noCaps: true, unelevated: true }
      }).onOk(() => {
        this.$api.post('productos/' + encodeURIComponent(String(row.cod_prod).trim()) + '/restaurar')
          .then(res => {
            this.$q.notify({ message: res.data.message, color: 'positive', icon: 'restore_from_trash', position: 'top' })
            this.onRequest({ pagination: this.pagination })
          })
          .catch(err => { this.avisarError(err, 'No se pudo restaurar') })
      })
    },

    abrirCreacion () {
      // Mismos campos que la edición, en blanco. El código puede quedar vacío:
      // el backend asigna el siguiente libre.
      this.edicion = {
        cod_prod: '',
        Producto: '',
        Nomcomer: '',
        cod_grup: this.filtros.grupo || '',
        codUnid: this.filtros.unidad || '',
        tipo: 'NORMAL',
        tipo_producto_id: (this.tiposProducto.find(t => t.nombre === 'EMBUTIDO') || {}).id || null,
        Precio: 0,
        Precio_Costo: 0,
        precioAprox: 0,
        Precio3: 0,
        Precio4: 0,
        Precio5: 0,
        Precio6: 0,
        Precio7: 0,
        Precio8: 0,
        Precio9: 0,
        Precio10: 0,
        Precio11: 0,
        Precio12: 0,
        Precio13: 0,
        precio_compra: null,
        stock_actual: 0,
        trozado: false
      }
      this.filaEditada = null
      this.gruposFiltrados = this.grupos
      this.creando = true
      this.dialogEditar = true
    },

    abrirEdicion (row) {
      // Se edita una copia: si cancela, la fila de la tabla queda intacta.
      // Los textos van con '' y no null: las columnas de tbproductos son
      // NOT NULL y un campo vacío llegaría al backend como null.
      this.edicion = {
        cod_prod: row.cod_prod,
        Producto: row.Producto || '',
        Nomcomer: row.Nomcomer || '',
        cod_grup: row.cod_grup || '',
        codUnid: row.codUnid || '',
        tipo: row.tipo || '',
        tipo_producto_id: row.tipo_producto_id ? Number(row.tipo_producto_id) : null,
        Precio: Number(row.Precio) || 0,
        Precio_Costo: Number(row.Precio_Costo) || 0,
        precioAprox: Number(row.precioAprox) || 0,
        Precio3: Number(row.Precio3) || 0,
        Precio4: Number(row.Precio4) || 0,
        Precio5: Number(row.Precio5) || 0,
        Precio6: Number(row.Precio6) || 0,
        Precio7: Number(row.Precio7) || 0,
        Precio8: Number(row.Precio8) || 0,
        Precio9: Number(row.Precio9) || 0,
        Precio10: Number(row.Precio10) || 0,
        Precio11: Number(row.Precio11) || 0,
        Precio12: Number(row.Precio12) || 0,
        Precio13: Number(row.Precio13) || 0,
        // Vacío = sin precio de compra cargado.
        precio_compra: row.precio_compra === null || row.precio_compra === undefined ? null : Number(row.precio_compra),
        // La columna "Stock" de la tabla es tbproductos.stock_actual.
        stock_actual: Number(row.cantidad) || 0,
        // En la tabla es texto ('SI' / 'NO'); en el formulario, un check.
        trozado: this.esTrozado(row)
      }
      this.filaEditada = row
      this.gruposFiltrados = this.grupos
      this.creando = false
      this.dialogEditar = true
    },

    filtrarGrupos (texto, update) {
      update(() => {
        const busca = (texto || '').toLowerCase().trim()
        this.gruposFiltrados = busca === ''
          ? this.grupos
          : this.grupos.filter(g => String(g.label || g).toLowerCase().includes(busca))
      })
    },

    // Un nombre que no esta en la lista crea un grupo: se elige de que grupo
    // padre cuelga y el codigo lo arma el backend.
    nuevoGrupo (texto, done) {
      const descripcion = (texto || '').trim().toUpperCase()
      done()
      if (!descripcion) return
      const existente = this.grupos.find(g => String(g.label).toUpperCase() === descripcion)
      if (existente) {
        this.edicion.cod_grup = existente.value
        return
      }
      this.abrirNuevoGrupo(descripcion)
    },
    abrirNuevoGrupo (descripcion) {
      this.nuevo = { descripcion: (descripcion || '').toUpperCase(), padre: null }
      this.dialogGrupo = true
    },
    crearGrupo () {
      this.creandoGrupo = true
      this.$api.post('productos/grupos', { descripcion: this.nuevo.descripcion.trim(), cod_pdr: this.nuevo.padre })
        .then(res => {
          const grupo = res.data.grupo
          if (!this.grupos.some(g => g.value === grupo.value)) {
            this.grupos = [...this.grupos, grupo].sort((a, b) => String(a.label).localeCompare(String(b.label)))
          }
          this.gruposFiltrados = this.grupos
          // Queda elegido en el producto que se esta editando.
          this.edicion.cod_grup = grupo.value
          this.dialogGrupo = false
          this.$q.notify({ message: res.data.message, color: 'positive', icon: 'check_circle', position: 'top' })
        })
        .catch(err => { this.avisarError(err, 'No se pudo crear el grupo') })
        .finally(() => { this.creandoGrupo = false })
    },

    guardarEdicion () {
      this.guardando = true

      const peticion = this.creando
        ? this.$api.post('productos', this.edicion)
        : this.$api.put('productos/' + encodeURIComponent(this.edicion.cod_prod), this.edicion)

      peticion
        .then(res => {
          this.$q.notify({
            message: res.data.message,
            color: 'positive',
            icon: 'check_circle',
            position: 'top'
          })
          this.dialogEditar = false
          // Se recarga en vez de parchear la fila: la columna Grupo sale de un
          // join y el stock de tbstock, asi que copiar solo los campos
          // editados dejaba la tabla mostrando el grupo anterior.
          this.onRequest({ pagination: this.pagination })
          // Un grupo o una unidad nuevos tienen que aparecer en los filtros.
          if (this.creando) this.cargarFiltros()
        })
        .catch(err => {
          this.avisarError(err, this.creando ? 'No se pudo crear el producto' : 'No se pudo actualizar el producto')
        })
        .finally(() => { this.guardando = false })
    },

    quitarImagen (row) {
      this.$q.dialog({
        title: 'Quitar foto',
        message: '¿Quitar la foto de ' + row.Producto + '?',
        cancel: true,
        persistent: true
      }).onOk(() => {
        this.$api.delete('productos/' + encodeURIComponent(row.cod_prod) + '/imagen')
          .then(() => { row.imagen = null })
          .catch(err => { this.avisarError(err, 'No se pudo quitar la imagen') })
      })
    },

    async avisarError (err, porDefecto) {
      let mensaje = err.response?.data?.message

      // En las descargas la respuesta viaja como Blob, así que el motivo real
      // del error hay que leerlo del propio Blob.
      if (err.response?.data instanceof Blob) {
        try {
          mensaje = JSON.parse(await err.response.data.text()).message
        } catch (e) {
          mensaje = null
        }
      }

      this.$q.notify({
        message: mensaje || porDefecto,
        color: 'negative',
        icon: 'error',
        position: 'top'
      })
    },
    cargarFiltros () {
      this.$api.get('filtrosProducto').then(res => {
        this.grupos = res.data.grupos || []
        this.padres = res.data.padres || []
        this.unidades = res.data.unidades || []
      }).catch(() => {
        // Los selects quedan vacíos; la tabla sigue siendo usable con la búsqueda.
      })
    },
    recargar () {
      this.pagination.page = 1
      this.onRequest({ pagination: this.pagination })
    },
    onRequest (props) {
      const { page, rowsPerPage, sortBy, descending } = props.pagination
      this.loading = true

      this.$api.get('productosPaginado', {
        params: {
          page,
          perPage: rowsPerPage,
          sortBy,
          descending,
          search: this.filtros.search || '',
          grupo: this.filtros.grupo || '',
          tipo_producto_id: this.filtros.tipo || '',
          unidad: this.filtros.unidad || '',
          conStock: this.filtros.conStock ? 1 : 0,
          incluirInactivos: this.filtros.incluirInactivos ? 1 : 0
        }
      }).then(res => {
        this.productos = res.data.data
        this.pagination.page = res.data.current_page
        // Con "Todos" (0) el backend responde con su tope real; hay que
        // conservar el 0 o el selector se queda sin opción marcada.
        this.pagination.rowsPerPage = rowsPerPage === 0 ? 0 : Number(res.data.per_page)
        this.pagination.rowsNumber = res.data.total
        this.pagination.sortBy = sortBy
        this.pagination.descending = descending
      }).catch(err => {
        this.$q.notify({
          message: err.response?.data?.message || 'No se pudo cargar el catálogo de productos',
          color: 'negative',
          icon: 'error',
          position: 'top'
        })
      }).finally(() => {
        this.loading = false
      })
    }
  }
}
</script>

<style scoped>
.producto-eliminado {
  color: #9e9e9e;
  text-decoration: line-through;
}
/* Entradas y salidas: la tabla ocupa el resto del dialogo, encabezado fijo. */
.tabla-es {
  height: 100%;
}
.tabla-es :deep(thead tr th) {
  position: sticky;
  top: 0;
  z-index: 1;
  background: #1f3864;
  color: #fff;
}
.tabla-es :deep(td),
.tabla-es :deep(th) {
  font-size: 11px;
  padding: 2px 6px;
  height: 22px;
}
.tabla-es :deep(tr.fila-saldo td) {
  background: #ddebf7;
  font-weight: 700;
}
.tarjeta-es {
  padding: 4px 8px;
  text-align: center;
  line-height: 1.2;
}
.tarjeta-es-titulo,
.tarjeta-es-sub {
  font-size: 10px;
  color: rgba(0, 0, 0, 0.55);
}
.tarjeta-es-titulo {
  text-transform: uppercase;
}
.tarjeta-es-valor {
  font-size: 18px;
  font-weight: 700;
}
/* Tabla compacta: con 1.050 productos y muchas columnas, cuanto mas quepa
   por pantalla menos hay que paginar y desplazarse. */
.tabla-compacta :deep(thead th) {
  font-size: 11px;
  padding: 4px 8px;
  white-space: nowrap;
}
.tabla-compacta :deep(tbody td) {
  font-size: 12px;
  padding: 2px 8px;
  height: auto;
}
.tabla-compacta :deep(tbody tr) {
  height: 40px;
}

/* Miniatura que ademas es zona de arrastre. */
.foto-zona {
  position: relative;
  width: 44px;
  height: 44px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px dashed #cfcfcf;
  border-radius: 6px;
  background: #fafafa;
  overflow: hidden;
  cursor: pointer;
  transition: border-color .15s, background .15s;
}
.foto-zona:hover {
  border-color: var(--q-primary);
}
/* Mientras se arrastra encima, para que se vea dónde se va a soltar. */
.foto-zona--activa {
  border-color: var(--q-primary);
  border-style: solid;
  background: #e3f2fd;
}
.foto-zona img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  /* Sin esto el navegador arrastra la propia miniatura al soltar. */
  pointer-events: none;
}
</style>
