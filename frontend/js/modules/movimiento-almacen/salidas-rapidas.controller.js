/**
 * SalidasRapidas - Controller para el módulo de Salidas Rápidas
 * Tab 2 de dashboard-movimiento-almacen.html
 * API base: /api/movimiento-almacen/
 */
const SalidasRapidas = (() => {
    const BASE = (window.AppConfig?.apiUrl || '/plantaincubacion/backend/index.php/api') + '/movimiento-almacen';

    // Estado interno
    let _initialized = false;
    let _productos    = [];   // lista completa cargada del servidor
    let _seleccion    = [];   // [{...producto, tcantid, rowIdx}]
    let _almacenes    = [];
    let _clientes     = [];   // [{tprocli, nombre}] para autocomplete
    let _lineas       = [];   // [{codigo, descri}] líneas de producto
    let _tregActual       = null;
    let _rowSelMs         = null;  // treg seleccionado en Mis Salidas
    let _editarData       = null;  // datos completos del movimiento en edición
    let _editingIdx       = -1;   // índice en _seleccion que está en edición
    let _listaKbIdx       = -1;   // fila resaltada por teclado en lista izquierda
    let _listaFiltradaKb  = [];   // snapshot de filas visibles para nav teclado
    let _selKbIdx         = -1;   // fila activa en panel derecho (teclado)

    // ─────────────────────────────────────────────────────────────────
    // INIT
    // ─────────────────────────────────────────────────────────────────
    async function init() {
        if (_initialized) return;
        _initialized = true;

        _fechaHoy();
        await Promise.all([_cargarAlmacenes(), _cargarAlmacenesDestino(), _cargarClientes(), _cargarLineas()]);
        cambiarTipo('S003'); // Consumo por defecto (oculta destino)
        await _obtenerNuevoReg();
        _initKeyboardNav();

        // Wiring autocomplete de nombre
        const nomInput = document.getElementById('sr-nombre');
        if (nomInput) {
            nomInput.addEventListener('input', _onNombreInput);
            nomInput.addEventListener('blur', function() { setTimeout(_hideClienteDropdown, 200); });
        }
    }

    function _fechaHoy() {
        const el = document.getElementById('sr-fecha-hoy');
        if (el) {
            const d = new Date();
            el.textContent = d.toLocaleDateString('es-PE', { weekday:'short', day:'2-digit', month:'short', year:'numeric' });
        }
        // Poner fecha de hoy en el filtro de Mis Salidas
        const msFecha = document.getElementById('ms-fecha');
        if (msFecha) {
            const hoy = new Date();
            const y = hoy.getFullYear();
            const m = String(hoy.getMonth() + 1).padStart(2, '0');
            const day = String(hoy.getDate()).padStart(2, '0');
            msFecha.value = y + '-' + m + '-' + day;
        }
    }

    async function _cargarAlmacenes() {
        try {
            const res = await fetch(BASE + '/almacenes', { credentials: 'include' });
            const json = await res.json();
            _almacenes = (json.data || []);
            const sel = document.getElementById('sr-alma');
            if (!sel) return;
            sel.innerHTML = '<option value="">-- Almacén --</option>';
            _almacenes.forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.codalm;
                opt.textContent = a.codalm + ' - ' + a.descri;
                sel.appendChild(opt);
            });
        } catch (e) { console.error('Error cargando almacenes:', e); }
    }

    async function _cargarAlmacenesDestino() {
        // Los almacenes de destino se filtran a partir de _almacenes (ya cargados),
        // excluyendo el almacén origen seleccionado. Se rellena al cambiar origen.
        _refrescarDestinoOptions();
    }

    function _refrescarDestinoOptions() {
        const sel = document.getElementById('sr-alma-destino');
        if (!sel) return;
        const almaOrigen = document.getElementById('sr-alma')?.value || '';
        sel.innerHTML = '<option value="">-- Destino --</option>';
        _almacenes
            .filter(a => a.codalm !== almaOrigen)
            .forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.codalm;
                opt.textContent = a.codalm + ' - ' + a.descri;
                sel.appendChild(opt);
            });
    }

    async function _obtenerNuevoReg() {
        try {
            const res = await fetch(BASE + '/nuevo-reg', { credentials: 'include' });
            const json = await res.json();
            _tregActual = json.data?.treg ?? json.data;
            const el = document.getElementById('sr-num-reg');
            if (el) el.textContent = _tregActual || '--';
        } catch (e) { console.error('Error obteniendo reg:', e); }
    }

    // ─────────────────────────────────────────────────────────────────
    // CAMBIAR ALMACÉN
    // ─────────────────────────────────────────────────────────────────
    function cambiarAlmacen(alma) {
        // Si hay productos seleccionados, no permitir cambiar almacén
        if (_seleccion.length > 0) {
            const almaActual = document.getElementById('sr-alma').value;
            // Restaurar el select al valor anterior
            document.getElementById('sr-alma').value = almaActual;
            Notification.warning(
                'Hay ' + _seleccion.length + ' producto(s) seleccionado(s) del almacén actual. ' +
                'Quita todos los productos de la lista antes de cambiar de almacén.',
                5000
            );
            return;
        }
        const dispEl = document.getElementById('sr-alma-display');
        if (dispEl) {
            const almObj = _almacenes.find(a => a.codalm === alma);
            dispEl.value = alma ? (alma + ' - ' + (almObj ? almObj.descri : '')) : '';
        }
        // Actualizar destinos excluyendo el nuevo origen
        _refrescarDestinoOptions();
        if (alma) buscarProductos();
        else _renderLista([]);
    }

    // ─────────────────────────────────────────────────────────────────
    // BUSCAR PRODUCTOS (servidor)
    // ─────────────────────────────────────────────────────────────────
    async function buscarProductos() {
        const alma = document.getElementById('sr-alma').value;
        if (!alma) { _renderLista([]); return; }
        const q = document.getElementById('sr-buscar').value.trim();
        const soloStock = document.getElementById('sr-solo-stock').checked;
        const linea = (document.getElementById('sr-linea')?.value || '').trim();
        _renderLista(null); // loading state
        try {
            let url = BASE + '/salida-rapida/productos?alma=' + encodeURIComponent(alma);
            if (q)     url += '&q='     + encodeURIComponent(q);
            if (linea) url += '&linea=' + encodeURIComponent(linea);
            const res = await fetch(url, { credentials: 'include' });
            const json = await res.json();
            let rows = json.data ? json.data.rows : [];
            if (soloStock) rows = rows.filter(r => parseFloat(r.stock) > 0);
            _productos = rows;
            _renderLista(rows);
        } catch (e) {
            console.error('Error cargando productos:', e);
            _renderLista([]);
        }
    }

    // Filtro local (sin ir al servidor)
    function filtrarLocal(termino) {
        const t = termino.toLowerCase().trim();
        const soloStock = document.getElementById('sr-solo-stock').checked;
        let rows = _productos;
        if (soloStock) rows = rows.filter(r => parseFloat(r.stock) > 0);
        if (t) rows = rows.filter(r =>
            (r.codigo || '').toLowerCase().includes(t) ||
            (r.descripcion || '').toLowerCase().includes(t) ||
            (r.lote || '').toLowerCase().includes(t)
        );
        _renderLista(rows, true);
    }

    function _renderLista(rows, noUpdateState) {
        const tbody = document.getElementById('sr-lista-tbody');
        if (!tbody) return;
        if (rows === null) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#6b7280;padding:16px;">Cargando...</td></tr>';
            return;
        }
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:16px;">Sin resultados</td></tr>';
            _listaFiltradaKb = [];
            if (!noUpdateState) _listaKbIdx = -1;
            return;
        }
        _listaFiltradaKb = rows;
        if (!noUpdateState) _listaKbIdx = -1;
        tbody.innerHTML = rows.map((r, i) => {
            const stock = parseFloat(r.stock) || 0;
            const kg    = parseFloat(r.peso_stock) || 0;
            const enSel = _seleccion.some(s => s.codigo === r.codigo && s.lote === r.lote);
            const isKb  = i === _listaKbIdx;
            const rowStyle = isKb
                ? 'background:#dbeafe;outline:2px solid #2563eb;'
                : (enSel ? 'background:#bbf7d0;' : '');
            return '<tr onclick="SalidasRapidas._agregarItem(' + i + ')" ' +
                   (rowStyle ? 'style="' + rowStyle + '"' : '') + '>' +
                   '<td style="color:#9ca3af;">' + (i + 1) + '</td>' +
                   '<td style="font-weight:600;">' + (r.codigo || '') + '</td>' +
                   '<td title="' + (r.descripcion || '') + '">' + _trunc(r.descripcion, 30) + '</td>' +
                   '<td>' + (r.unidad || '') + '</td>' +
                   '<td style="color:#6b7280;font-size:11px;">' + (r.lote || '') + '</td>' +
                   '<td style="text-align:right;font-weight:600;color:' + (stock > 0 ? '#16a34a' : '#dc2626') + ';">' + _num(stock) + '</td>' +
                   '<td style="text-align:right;color:#6b7280;">' + _num(kg) + '</td>' +
                   '</tr>';
        }).join('');
        // Scroll la fila resaltada a la vista
        if (_listaKbIdx >= 0) {
            const kbRow = tbody.querySelectorAll('tr')[_listaKbIdx];
            if (kbRow) kbRow.scrollIntoView({ block: 'nearest' });
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // AGREGAR ÍTEM (click en lista izquierda)
    // ─────────────────────────────────────────────────────────────────
    function _agregarItem(listaIdx) {
        // Obtener fila filtrada actual
        const t = (document.getElementById('sr-buscar').value || '').toLowerCase().trim();
        const soloStock = document.getElementById('sr-solo-stock').checked;
        let rows = _productos;
        if (soloStock) rows = rows.filter(r => parseFloat(r.stock) > 0);
        if (t) rows = rows.filter(r =>
            (r.codigo || '').toLowerCase().includes(t) ||
            (r.descripcion || '').toLowerCase().includes(t) ||
            (r.lote || '').toLowerCase().includes(t)
        );
        const r = rows[listaIdx];
        if (!r) return;

        // Si ya está en selección, iniciar edición
        const existeIdx = _seleccion.findIndex(s => s.codigo === r.codigo && s.lote === r.lote);
        if (existeIdx >= 0) {
            _iniciarEdicion(existeIdx);
            return;
        }
        _seleccion.push({
            alm: r.alm || document.getElementById('sr-alma').value,
            codigo: r.codigo,
            descripcion: r.descripcion,
            unidad: r.unidad,
            lote: r.lote || '00000000',
            stock: parseFloat(r.stock) || 0,
            peso_stock: parseFloat(r.peso_stock) || 0,
            tcantid: 1,
        });
        _renderSeleccion();
        _actualizarTotal();
        _refreshListaMarcas();
    }

    // ─────────────────────────────────────────────────────────────────
    // RENDER SELECCIONADOS
    // ─────────────────────────────────────────────────────────────────
    function _renderSeleccion() {
        const tbody = document.getElementById('sr-sel-tbody');
        const emptyRow = document.getElementById('sr-sel-empty');
        const countEl = document.getElementById('sr-count-sel');
        if (!tbody) return;

        if (countEl) countEl.textContent = _seleccion.length;

        if (!_seleccion.length) {
            tbody.innerHTML = '<tr id="sr-sel-empty"><td colspan="8" style="text-align:center;color:#9ca3af;padding:20px;font-size:12px;">Haz clic en un producto para agregarlo</td></tr>';
            return;
        }

        tbody.innerHTML = _seleccion.map((s, i) => {
            const isEdit  = i === _editingIdx;
            const isKbSel = !isEdit && i === _selKbIdx;
            const rowCls  = isEdit ? 'editing' : (isKbSel ? 'sr-sel-kb' : '');
            const cantCell = isEdit
                ? '<td style="text-align:right;"><input type="number" id="sr-edit-cant" value="' + s.tcantid + '" min="0.01" step="0.001" style="width:60px;text-align:right;border:1px solid #f59e0b;border-radius:4px;padding:2px 4px;font-size:12px;" onblur="SalidasRapidas._confirmarEdicion(' + i + ')" onkeydown="SalidasRapidas._keyEdicion(event,' + i + ')"></td>'
                : '<td style="text-align:right;font-weight:600;color:#1d4ed8;cursor:pointer;" onclick="SalidasRapidas._iniciarEdicion(' + i + ')">' + _num(s.tcantid) + '</td>';
            return '<tr onclick="SalidasRapidas._selRow(event,' + i + ')" ' + (rowCls ? 'class="' + rowCls + '"' : '') + '>' +
                '<td style="color:#9ca3af;">' + (i + 1) + '</td>' +
                '<td style="font-weight:600;font-size:11px;">' + (s.codigo || '') + '</td>' +
                '<td title="' + (s.descripcion || '') + '">' + _trunc(s.descripcion, 22) + '</td>' +
                '<td>' + (s.unidad || '') + '</td>' +
                cantCell +
                '<td style="text-align:right;color:#6b7280;">' + _num(s.peso_stock) + '</td>' +
                '<td style="text-align:right;color:#16a34a;">' + _num(s.stock) + '</td>' +
                '<td><button onclick="SalidasRapidas._quitarIdx(' + i + ');event.stopPropagation();" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:14px;line-height:1;">&#10006;</button></td>' +
                '</tr>';
        }).join('');

        // Focus input si está en edición
        if (_editingIdx >= 0) {
            setTimeout(() => {
                const inp = document.getElementById('sr-edit-cant');
                if (inp) { inp.focus(); inp.select(); }
            }, 30);
        } else if (_selKbIdx >= 0) {
            // Scroll fila activa a la vista
            const selTbody = document.getElementById('sr-sel-tbody');
            if (selTbody) {
                const r = selTbody.querySelectorAll('tr')[_selKbIdx];
                if (r) r.scrollIntoView({ block: 'nearest' });
            }
        }
    }

    function _selRow(e, idx) {
        // solo activar edición si no se hizo clic en la celda de cantidad directamente
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON') return;
        _iniciarEdicion(idx);
    }

    function _iniciarEdicion(idx) {
        _editingIdx = idx;
        _renderSeleccion();
    }

    function _confirmarEdicion(idx) {
        const inp = document.getElementById('sr-edit-cant');
        if (!inp) { _editingIdx = -1; return; }
        const val = parseFloat(inp.value);
        if (!isNaN(val) && val > 0) {
            const stockMax = parseFloat(_seleccion[idx].stock) || 0;
            if (stockMax > 0 && val > stockMax) {
                inp.style.border = '2px solid #dc2626';
                inp.title = 'Stock insuficiente. Disponible: ' + _num(stockMax);
                // Mostrar mensaje inline bajo el input
                let msg = document.getElementById('sr-stock-err');
                if (!msg) {
                    msg = document.createElement('div');
                    msg.id = 'sr-stock-err';
                    msg.style.cssText = 'position:absolute;background:#fef2f2;border:1px solid #fca5a5;color:#dc2626;font-size:11px;padding:4px 8px;border-radius:4px;z-index:999;white-space:nowrap;';
                    inp.parentNode.style.position = 'relative';
                    inp.parentNode.appendChild(msg);
                }
                msg.textContent = 'Máx: ' + _num(stockMax);
                inp.focus(); inp.select();
                return; // no confirmar, dejar el input activo
            }
            // Quitar mensaje de error si existía
            const oldMsg = document.getElementById('sr-stock-err');
            if (oldMsg) oldMsg.remove();
            _seleccion[idx].tcantid = val;
        }
        _editingIdx = -1;
        _renderSeleccion();
        _actualizarTotal();
    }

    function _keyEdicion(e, idx) {
        if (e.key === 'Enter') { e.preventDefault(); _confirmarEdicion(idx); }
        if (e.key === 'Escape') { _editingIdx = -1; _renderSeleccion(); }
    }

    function _quitarIdx(idx) {
        _seleccion.splice(idx, 1);
        if (_editingIdx === idx) _editingIdx = -1;
        // Ajustar índice de teclado
        if (_selKbIdx >= _seleccion.length) _selKbIdx = _seleccion.length - 1;
        _renderSeleccion();
        _actualizarTotal();
        _refreshListaMarcas();
    }

    function quitarSeleccionado() {
        if (_editingIdx >= 0) { _quitarIdx(_editingIdx); return; }
        // Si hay alguno seleccionado via hover/click de fila, quitar el último
        if (_seleccion.length) { _quitarIdx(_seleccion.length - 1); }
    }

    function quitarTodo() {
        if (!_seleccion.length) return;
        if (!confirm('¿Quitar todos los productos seleccionados?')) return;
        _seleccion = [];
        _editingIdx = -1;
        _selKbIdx = -1;
        _renderSeleccion();
        _actualizarTotal();
        _refreshListaMarcas();
    }

    function _actualizarTotal() {
        const total = _seleccion.reduce((s, r) => s + (parseFloat(r.tcantid) || 0), 0);
        const el = document.getElementById('sr-total');
        if (el) el.textContent = _num(total);
    }

    function _refreshListaMarcas() {
        // Re-render lista izquierda para marcar/desmarcar en verde
        filtrarLocal(document.getElementById('sr-buscar').value || '');
    }

    // ─────────────────────────────────────────────────────────────────
    // NAVEGACIÓN POR TECLADO
    // ─────────────────────────────────────────────────────────────────
    function _moverListaKb(delta) {
        if (!_listaFiltradaKb.length) return;
        _listaKbIdx = Math.max(0, Math.min(_listaFiltradaKb.length - 1, _listaKbIdx + delta));
        _renderLista(_listaFiltradaKb, true);
    }

    function _moverSelKb(delta) {
        if (!_seleccion.length) return;
        _selKbIdx = Math.max(0, Math.min(_seleccion.length - 1, _selKbIdx + delta));
        _renderSeleccion();
    }

    function _kbdRow(key, desc) {
        return '<div style="padding:3px 0;"><kbd style="background:#0f2a4a;border:1px solid #2563eb;' +
            'border-radius:4px;padding:2px 7px;font-size:11px;color:#60a5fa;font-family:monospace;' +
            'white-space:nowrap;">' + key + '</kbd></div>' +
            '<div style="padding:3px 0;color:#e2e8f0;">' + desc + '</div>';
    }

    function _toggleAyudaTeclado() {
        const existing = document.getElementById('sr-kbd-help');
        if (existing) { existing.remove(); return; }
        const panel = document.createElement('div');
        panel.id = 'sr-kbd-help';
        panel.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);z-index:10000;' +
            'background:#1e3a5f;color:#fff;border-radius:12px;padding:18px 24px;min-width:440px;max-width:95vw;' +
            'box-shadow:0 8px 32px rgba(0,0,0,.55);font-family:Arial,sans-serif;font-size:13px;';
        panel.innerHTML =
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">' +
            '  <strong style="font-size:14px;color:#60a5fa;">&#9000; Atajos de Teclado &mdash; Salidas R&aacute;pidas</strong>' +
            '  <button onclick="document.getElementById(\'sr-kbd-help\').remove()" ' +
            '    style="background:none;border:none;color:#93c5fd;cursor:pointer;font-size:20px;line-height:1;">&times;</button>' +
            '</div>' +
            '<div style="display:grid;grid-template-columns:auto 1fr;gap:3px 18px;">' +
            _kbdRow('Alt + A', 'Ir al selector de Almac&eacute;n') +
            _kbdRow('Alt + B', 'Ir al buscador de productos') +
            _kbdRow('Alt + S', 'Ir al solicitante / RUC') +
            _kbdRow('Alt + G', 'Generar salida') +
            _kbdRow('Alt + V', 'Vista previa') +
            _kbdRow('Alt + N', 'Nuevo (limpiar formulario)') +
            _kbdRow('Alt + M', 'Mis Salidas') +
            _kbdRow('Alt + X', 'Quitar &iacute;tem activo de la lista') +
            _kbdRow('Alt + Q', 'Quitar todos los &iacute;tems') +
            '</div>' +
            '<div style="border-top:1px solid #2563eb;margin:10px 0 8px;"></div>' +
            '<div style="display:grid;grid-template-columns:auto 1fr;gap:3px 18px;">' +
            _kbdRow('&#8595; / &#8593; (en buscador)', 'Navegar lista de productos') +
            _kbdRow('Enter (en buscador)', 'Agregar producto resaltado') +
            _kbdRow('&#8595; / &#8593; (fuera de inputs)', 'Navegar lista de seleccionados') +
            _kbdRow('Enter (fuera de inputs)', 'Editar cantidad del &iacute;tem activo') +
            _kbdRow('Delete (fuera de inputs)', 'Quitar &iacute;tem activo') +
            _kbdRow('F1 &nbsp;/&nbsp; Alt+?', 'Mostrar / ocultar esta ayuda') +
            '</div>' +
            '<div style="margin-top:10px;font-size:11px;color:#93c5fd;">Pulsa <kbd style="background:#0f2a4a;border:1px solid #2563eb;border-radius:3px;padding:1px 5px;color:#60a5fa;">Esc</kbd> o <kbd style="background:#0f2a4a;border:1px solid #2563eb;border-radius:3px;padding:1px 5px;color:#60a5fa;">F1</kbd> para cerrar</div>';
        document.body.appendChild(panel);
        const closeEsc = function(e) {
            if (e.key === 'Escape' || e.key === 'F1') {
                e.preventDefault();
                panel.remove();
                document.removeEventListener('keydown', closeEsc);
            }
        };
        document.addEventListener('keydown', closeEsc);
    }

    function _initKeyboardNav() {
        // ── Flechas + Enter en el buscador de productos ──────────────
        const buscarInput = document.getElementById('sr-buscar');
        if (buscarInput) {
            buscarInput.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    _moverListaKb(1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    _moverListaKb(-1);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (_listaKbIdx >= 0 && _listaFiltradaKb[_listaKbIdx]) {
                        _agregarItem(_listaKbIdx);
                    } else if (_listaFiltradaKb.length === 1) {
                        _agregarItem(0);
                    }
                }
            });
        }

        // ── Atajos globales ───────────────────────────────────────────
        document.addEventListener('keydown', function(e) {
            const tag     = (document.activeElement?.tagName || '').toUpperCase();
            const enInput = tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA';
            const enBuscar = document.activeElement?.id === 'sr-buscar';

            // Alt + letra → acciones globales (siempre activas)
            if (e.altKey && !e.ctrlKey && !e.shiftKey) {
                switch (e.key.toLowerCase()) {
                    case 'a': e.preventDefault(); document.getElementById('sr-alma')?.focus(); break;
                    case 'b': e.preventDefault(); document.getElementById('sr-buscar')?.focus(); break;
                    case 's': e.preventDefault(); document.getElementById('sr-ruc')?.focus(); break;
                    case 'g': e.preventDefault(); generar(); break;
                    case 'v': e.preventDefault(); vistaPrevia(); break;
                    case 'n': e.preventDefault(); nuevo(); break;
                    case 'm': e.preventDefault(); misSalidas(); break;
                    case 'x': e.preventDefault(); quitarSeleccionado(); break;
                    case 'q': e.preventDefault(); quitarTodo(); break;
                    case '/': case '?': e.preventDefault(); _toggleAyudaTeclado(); break;
                }
                return;
            }

            // F1 → Ayuda
            if (e.key === 'F1') { e.preventDefault(); _toggleAyudaTeclado(); return; }

            // Flechas / Enter / Delete fuera de inputs → controlar panel derecho
            if (!enInput) {
                if (e.key === 'ArrowDown') {
                    e.preventDefault(); _moverSelKb(1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault(); _moverSelKb(-1);
                } else if (e.key === 'Enter' && _selKbIdx >= 0) {
                    e.preventDefault(); _iniciarEdicion(_selKbIdx);
                } else if (e.key === 'Delete' && _selKbIdx >= 0) {
                    e.preventDefault();
                    const idxToRemove = _selKbIdx;
                    _quitarIdx(idxToRemove);
                }
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────
    // CAMBIAR TIPO (Consumo / Transferencia)
    // ─────────────────────────────────────────────────────────────────
    function cambiarTipo(codtra) {
        const esTransfer = codtra === 'S005' || document.getElementById('sr-tipo-transfer').checked;
        const grupo = document.getElementById('sr-grupo-destino');
        if (grupo) grupo.style.display = esTransfer ? '' : 'none';
        const titulo = document.getElementById('sr-titulo-tipo');
        if (titulo) titulo.textContent = esTransfer ? 'TRANSFERENCIA' : 'CONSUMO';
    }

    // ─────────────────────────────────────────────────────────────────
    // CLIENTES/PROVEEDORES — precarga para autocomplete
    // ─────────────────────────────────────────────────────────────────
    async function _cargarClientes() {
        try {
            const res = await fetch(BASE + '/clientes-proveedores', { credentials: 'include' });
            const json = await res.json();
            _clientes = json.data || [];
        } catch (e) { /* silencioso — autocompletado simplemente no funciona */ }
    }

    async function _cargarLineas() {
        try {
            const res = await fetch(BASE + '/lineas', { credentials: 'include' });
            const json = await res.json();
            _lineas = json.data || [];
            const sel = document.getElementById('sr-linea');
            if (!sel) return;
            sel.innerHTML = '<option value="">Todas las líneas</option>';
            _lineas.forEach(function(l) {
                var opt = document.createElement('option');
                opt.value = l.codigo;
                opt.textContent = l.codigo + ' - ' + l.descri;
                sel.appendChild(opt);
            });
        } catch (e) { /* silencioso */ }
    }

    function _onNombreInput(e) {
        const term = (e.target.value || '').trim().toLowerCase();
        if (term.length < 2) { _hideClienteDropdown(); return; }
        const found = _clientes.filter(function(c) {
            return (c.nombre || '').toLowerCase().indexOf(term) >= 0;
        }).slice(0, 10);
        _showClienteDropdown(found);
    }

    function _showClienteDropdown(list) {
        var dd = document.getElementById('sr-cliente-dd');
        var wrap = document.getElementById('sr-nombre-wrap');
        
        if (!dd) {
            dd = document.createElement('div');
            dd.id = 'sr-cliente-dd';
            // FLOTA HACIA ARRIBA (bottom: 100%) CON MÁXIMA CAPA (z-index: 999999)
            dd.style.cssText = 'position:absolute; left:0; bottom:calc(100% + 4px); top:auto; width:100%; ' +
                'background:#fff; border:1px solid #cbd5e1; border-radius:8px; ' +
                'box-shadow: 0 -10px 25px -5px rgba(0, 0, 0, 0.2); z-index: 999999; ' +
                'max-height:180px; overflow-y:auto;';
            if (wrap) {
                wrap.appendChild(dd);
            }
        }
        if (!list.length) { dd.style.display = 'none'; return; }
        
        dd.innerHTML = list.map(function(c) {
            var code = (c.tprocli || '').replace(/"/g, '&quot;');
            var nom  = (c.nombre  || '').replace(/"/g, '&quot;');
            return '<div data-code="' + code + '" data-nombre="' + nom + '" ' +
                'style="padding:8px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #f1f5f9;" ' +
                'onmousedown="SalidasRapidas._seleccionarCliente(this)" ' +
                'onmouseenter="this.style.background=\'#eff6ff\'" onmouseleave="this.style.background=\'\'">' +
                '<span style="font-weight:700; color:#1d4ed8;">' + (c.tprocli || '') + '</span> — ' + (c.nombre || '') +
                '</div>';
        }).join('');
        dd.style.display = '';
    }

    function _hideClienteDropdown() {
        var dd = document.getElementById('sr-cliente-dd');
        if (dd) dd.style.display = 'none';
    }

    function _seleccionarCliente(el) {
        var rucEl = document.getElementById('sr-ruc');
        var nomEl = document.getElementById('sr-nombre');
        if (rucEl) rucEl.value = el.dataset.code;
        if (nomEl) nomEl.value = el.dataset.nombre;
        _hideClienteDropdown();
    }

    // ─────────────────────────────────────────────────────────────────
    // BUSCAR SOLICITANTE (por RUC/código — usa lista precargada)
    // ─────────────────────────────────────────────────────────────────
    function buscarSolicitante(ruc) {
        var rucTrim = (ruc || '').trim();
        if (!rucTrim) return;
        var found = _clientes.find(function(c) {
            return (c.tprocli || '').trim() === rucTrim;
        });
        if (found) {
            var nombreEl = document.getElementById('sr-nombre');
            if (nombreEl && !nombreEl.value.trim()) nombreEl.value = found.nombre || '';
        }
    }

    function nuevoSolicitante() {
        document.getElementById('sr-ruc').value = '';
        document.getElementById('sr-nombre').value = '';
        document.getElementById('sr-ruc').focus();
    }

    // ─────────────────────────────────────────────────────────────────
    // NUEVO (limpiar formulario)
    // ─────────────────────────────────────────────────────────────────
    async function nuevo() {
        _seleccion = [];
        _editingIdx = -1;
        _selKbIdx   = -1;
        _listaKbIdx = -1;
        _renderSeleccion();
        _actualizarTotal();
        _refreshListaMarcas();
        document.getElementById('sr-ruc').value = '';
        document.getElementById('sr-nombre').value = '';
        const r1 = document.getElementById('sr-tipo-consumo');
        if (r1) { r1.checked = true; cambiarTipo('S003'); }
        await _obtenerNuevoReg();
    }

    // ─────────────────────────────────────────────────────────────────
    // GENERAR (guardar movimiento)
    // ─────────────────────────────────────────────────────────────────
    async function generar() {
        // Validaciones iniciales
        const alma = document.getElementById('sr-alma').value;
        if (!alma) { Notification.error('Selecciona un almacén'); return; }
        if (!_seleccion.length) { Notification.error('Agrega al menos un producto'); return; }

        const codtra = document.querySelector('input[name="sr-tipo"]:checked')?.value || 'S003';
        const esTransfer = document.querySelector('input[name="sr-tipo"]:checked')?.value === 'S005';
        const almaDest = esTransfer ? document.getElementById('sr-alma-destino').value : '';
        if (esTransfer && !almaDest) { Notification.error('Selecciona un almacén destino para la transferencia'); return; }

        const ruc    = document.getElementById('sr-ruc').value.trim();
        const nombre = document.getElementById('sr-nombre').value.trim();

        if (codtra === 'S003' && !ruc) {
            Notification.warning('Selecciona una cuenta corriente (solicitante) antes de generar el consumo.');
            document.getElementById('sr-ruc').focus();
            return;
        }

        const errStock = _seleccion.find(s => (parseFloat(s.stock) || 0) > 0 && parseFloat(s.tcantid) > parseFloat(s.stock));
        if (errStock) {
            Notification.error('Stock insuficiente para "' + errStock.descripcion + '". Disponible: ' + _num(errStock.stock) + ' | Solicitado: ' + _num(errStock.tcantid), 6000);
            return;
        }

        const hoy = new Date();
        const tfectra = hoy.getFullYear() + '-' + String(hoy.getMonth() + 1).padStart(2, '0') + '-' + String(hoy.getDate()).padStart(2, '0');

        // ── 1. LEVANTAMOS EL SWEETALERT DE CARGA ANTES DE IR AL SERVIDOR ──
        Swal.fire({
            title: 'Procesando Salida',
            html: 'Guardando registro y preparando documento...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading(); // Activa el spinner de carga nativo
            },
            customClass: {
                popup: 'rounded-2xl shadow-2xl'
            }
        });

        // Verificar fecha antes de grabar
        try {
            const vRes = await fetch(BASE + '/verificar-fecha?fecha=' + encodeURIComponent(tfectra), { credentials: 'include' });
            const vJson = await vRes.json();
            if (!vJson.data?.valida) {
                Swal.close(); // Cerramos el loading si falla
                Notification.error('Fecha no válida: ' + (vJson.data?.mensaje || 'Período cerrado'));
                return;
            }
        } catch (e) {
            Swal.close();
            Notification.error('Error verificando fecha'); return;
        }

        // Obtener usuario actual desde sessionStorage
        let tuser = 'SYS';
        try {
            const uStr = sessionStorage.getItem('usuario');
            if (uStr) {
                const uObj = JSON.parse(uStr);
                tuser = uObj.username || uObj.usuario || uObj.user || 'SYS';
            }
        } catch (e) {}

        const detalle = _seleccion.map((s, i) => ({
            tcodigo: s.codigo,
            tlote:   s.lote || '00000000',
            talr:    almaDest || '',
            tcantid: parseFloat(s.tcantid) || 1,
            tpeso:   parseFloat(s.peso_stock) || 0,
            tpreuni: 0,
            timport: 0,
            tkardex: 0,
            tcencos: '',
            tcodproc: '', tcodsubproc: '', tcodacti: '', tcodtarea: '',
        }));

        const payload = {
            tfectra,
            tcodtra: codtra,
            talm:    alma,
            talr:    almaDest || '',
            tprocli: ruc || '00000000',
            tdoc:    '',
            tserie:  '',
            tnumfac: '',
            tfecfac: tfectra,
            tmon:    'S/.', tlib: '',
            tordcom: '', tglosa: nombre || 'SALIDA RAPIDA',
            tmotivo_traslado: '',
            tuser,
            detalle,
        };

        try {
            const res = await fetch(BASE + '/cabecera', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload),
            });
            const json = await res.json();
            
            if (json.success) {
                const treg = json.data?.treg || _tregActual;
                const fmt = document.querySelector('input[name="sr-formato"]:checked')?.value || 'A4';
                
                // Abrir ventana antes de que termine para evitar bloqueador de popups
                const printWin = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
                if (printWin) printWin.document.write('<html><body style="font-family:Arial;padding:20px;color:#555">Generando impresión&hellip;</body></html>');
                
                await nuevo();
                
                // ── 2. PROCESO TERMINADO: CERRAMOS EL LOADING Y PARAMOS EL SPINNER ──
                Swal.close(); 
                
                if (printWin) {
                    _cargarImpresionEnVentana(treg, fmt, printWin);
                } else {
                    Notification.info('Movimiento generado: REG ' + treg + ' — Habilita ventanas emergentes para imprimir');
                }
            } else {
                Swal.close(); // Cerramos el loading si el backend responde con error
                Notification.error('Error al generar: ' + (json.error || json.message || JSON.stringify(json)), 6000);
            }
        } catch (e) {
            Swal.close(); // Cerramos el loading si hay caída de red
            Notification.error('Error de comunicación: ' + e.message);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // VISTA PREVIA (sin guardar)
    // ─────────────────────────────────────────────────────────────────
    function vistaPrevia() {
        if (!_seleccion.length) { Notification.error('Agrega productos primero'); return; }
        const ahora = new Date();
        const fecha = ahora.toLocaleDateString('es-PE');
        const hora  = ahora.toLocaleTimeString('es-PE', { hour12: false });
        const total = _seleccion.reduce((s, x) => s + (parseFloat(x.tcantid) || 0), 0);
        const filas = _seleccion.map(function(s) {
            return '<tr>' +
                '<td>' + (s.codigo || '') + '</td>' +
                '<td>' + (s.descripcion || '') + '</td>' +
                '<td>' + (s.lote || '') + '</td>' +
                '<td style="text-align:center;">' + (s.unidad || '') + '</td>' +
                '<td style="text-align:right;font-weight:bold;">' + _num(s.tcantid) + '</td>' +
                '</tr>';
        }).join('');
        const html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Vista Previa</title>' +
            '<style>' +
            'body{font-family:Arial,sans-serif;font-size:11px;margin:14px 18px;}' +
            '.vp-top{display:flex;justify-content:space-between;font-size:10px;color:#555;margin-bottom:8px;}' +
            'h2{text-align:center;font-size:13px;text-transform:uppercase;margin:0 0 10px;' +
                'border-bottom:2px solid #1d4ed8;padding-bottom:5px;color:#1d4ed8;}' +
            'table{width:100%;border-collapse:collapse;}' +
            'th{background:#1e3a5f;color:#fff;padding:5px 8px;font-size:10px;text-align:left;}' +
            'td{padding:4px 8px;border-bottom:1px solid #e5e7eb;}' +
            'tr:nth-child(even) td{background:#f8fafc;}' +
            '.vp-footer{display:flex;justify-content:space-between;margin-top:10px;font-weight:bold;font-size:11px;border-top:1px solid #000;padding-top:6px;}' +
            '@media print{button{display:none;}}' +
            '</style></head><body>' +
            '<div class="vp-top"><span>' + fecha + '</span><span>' + hora + '</span></div>' +
            '<h2>VISTA PREVIA DE MIS PRODUCTOS A ENTREGAR</h2>' +
            '<table><thead><tr>' +
            '<th>CÓDIGO</th><th>DESCRIPCIÓN</th><th>LOTE</th>' +
            '<th style="text-align:center;">UND</th><th style="text-align:right;">CANT.</th>' +
            '</tr></thead><tbody>' + filas + '</tbody></table>' +
            '<div class="vp-footer">' +
            '<span>N&deg; Productos: ' + _seleccion.length + '</span>' +
            '<span>TOTAL: ' + _num(total) + '</span>' +
            '</div><br>' +
            '<button onclick="window.print()" style="padding:5px 18px;background:#1d4ed8;color:#fff;border:none;border-radius:4px;cursor:pointer;">&#128424; Imprimir</button>' +
            '</body></html>';
        const win = window.open('', '_blank', 'width=700,height=550,scrollbars=yes');
        if (win) { win.document.write(html); win.document.close(); }
    }

    // ─────────────────────────────────────────────────────────────────
    // EDITAR SALIDA RÁPIDA
    // ─────────────────────────────────────────────────────────────────
    async function abrirEditar(treg) {
        _editarData = null;
        const modal = document.getElementById('modal-editar-salida');
        if (!modal) return;

        // Limpiar estado
        document.getElementById('me-treg').textContent = treg;
        document.getElementById('me-ruc').value = '';
        document.getElementById('me-nombre').value = '';
        document.getElementById('me-error-msg').textContent = '';
        document.getElementById('me-info-bar').textContent = 'Cargando...';
        document.getElementById('me-detalle-tbody').innerHTML =
            '<tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:12px;">Cargando...</td></tr>';
        document.getElementById('me-btn-guardar').disabled = true;

        modal.style.display = 'flex';

        try {
            const res  = await fetch(BASE + '/cabecera/' + treg, { credentials: 'include' });
            const json = await res.json();
            if (!json.success) throw new Error(json.error || 'No se pudo cargar el movimiento');

            const cab    = json.data?.cabecera || {};
            const items  = json.data?.detalle  || [];

            _editarData = { cab, items: items.map(d => ({ ...d })) };

            // Info bar
            document.getElementById('me-info-bar').innerHTML =
                '<strong>' + (cab.tcodtra || '') + '</strong>' +
                ' &nbsp;|&nbsp; ALM: ' + (cab.talm || '') +
                (cab.talr ? ' &rarr; ' + cab.talr : '') +
                ' &nbsp;|&nbsp; Fecha: ' + (cab.tfectra || '');

            // Campos de cabecera
            document.getElementById('me-ruc').value    = cab.tprocli || '';
            document.getElementById('me-nombre').value = cab.tglosa  || '';

            // Detalle
            _renderEditarDetalle();
            document.getElementById('me-btn-guardar').disabled = false;

        } catch (e) {
            document.getElementById('me-info-bar').textContent = 'Error: ' + e.message;
            document.getElementById('me-detalle-tbody').innerHTML =
                '<tr><td colspan="8" style="text-align:center;color:#dc2626;padding:12px;">Error al cargar</td></tr>';
        }
    }

    function _renderEditarDetalle() {
        const tbody = document.getElementById('me-detalle-tbody');
        if (!tbody || !_editarData) return;
        const items = _editarData.items;

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:12px;">Sin items</td></tr>';
            return;
        }

        tbody.innerHTML = items.map((d, i) =>
            '<tr>' +
            '<td style="text-align:center;color:#6b7280;">' + (i + 1) + '</td>' +
            '<td style="font-weight:600;font-size:11px;">' + (d.tcodigo || '') + '</td>' +
            '<td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;" title="' + (d.nom_producto || '') + '">' + _trunc(d.nom_producto, 28) + '</td>' +
            '<td>' + (d.tlote || '') + '</td>' +
            '<td style="text-align:center;">' + (d.tunidad || '') + '</td>' +
            '<td style="text-align:right;">' +
                '<input type="number" class="me-cant-input" ' +
                'value="' + (parseFloat(d.tcantid) || 0) + '" ' +
                'min="0.001" step="0.001" ' +
                'onchange="SalidasRapidas._meEditCant(' + i + ',this.value)" ' +
                'onkeydown="if(event.key===\'Enter\')this.blur();">' +
            '</td>' +
            '<td style="text-align:right;color:#6b7280;">' + _num(d.tpeso) + '</td>' +
            '<td style="text-align:center;">' +
                '<button class="me-quitar-btn" ' +
                'onclick="SalidasRapidas._meQuitarItem(' + i + ')" ' +
                'title="Quitar item">&#10006;</button>' +
            '</td>' +
            '</tr>'
        ).join('');
    }

    function _meEditCant(idx, val) {
        if (!_editarData) return;
        const v = parseFloat(val);
        if (!isNaN(v) && v > 0) {
            _editarData.items[idx].tcantid = v;
        }
    }

    function _meQuitarItem(idx) {
        if (!_editarData) return;
        _editarData.items.splice(idx, 1);
        _renderEditarDetalle();
        if (_editarData.items.length === 0) {
            document.getElementById('me-error-msg').textContent = 'Debe haber al menos 1 item.';
        } else {
            document.getElementById('me-error-msg').textContent = '';
        }
    }

    function cerrarEditar() {
        const modal = document.getElementById('modal-editar-salida');
        if (modal) modal.style.display = 'none';
        _editarData = null;
    }

    async function guardarEdicion() {
        if (!_editarData) return;

        const errEl = document.getElementById('me-error-msg');
        errEl.textContent = '';

        const cab   = _editarData.cab;
        const items = _editarData.items;

        if (!items.length) {
            errEl.textContent = 'Debe haber al menos 1 item.';
            return;
        }

        // Leer valores editados del formulario antes de enviar
        const ruc    = (document.getElementById('me-ruc').value  || '').trim();
        const nombre = (document.getElementById('me-nombre').value || '').trim();

        // Leer cantidades actualizadas directo de los inputs por si no dispararon onchange
        const tbody = document.getElementById('me-detalle-tbody');
        if (tbody) {
            const inputs = tbody.querySelectorAll('.me-cant-input');
            inputs.forEach((inp, i) => {
                const v = parseFloat(inp.value);
                if (!isNaN(v) && v > 0 && items[i]) items[i].tcantid = v;
            });
        }

        const payload = {
            tfectra:          cab.tfectra,
            tcodtra:          cab.tcodtra,
            talm:             cab.talm,
            talr:             cab.talr   || '',
            tprocli:          ruc        || cab.tprocli || '00000000',
            tdoc:             cab.tdoc   || '',
            tserie:           cab.tserie || '',
            tnumfac:          cab.tnumfac || '',
            tfecfac:          cab.tfecfac || cab.tfectra,
            tmon:             cab.tmon   || 'S/.',
            tlib:             cab.tlib   || '',
            tordcom:          cab.tordcom || '',
            tglosa:           nombre     || cab.tglosa || 'SALIDA RAPIDA',
            tmotivo_traslado: cab.tmotivo_traslado || '',
            detalle: items.map(d => ({
                tcodigo:     d.tcodigo,
                tlote:       d.tlote   || '00000000',
                talr:        d.talr    || cab.talr || '',
                tcantid:     parseFloat(d.tcantid) || 0,
                tpeso:       parseFloat(d.tpeso)   || 0,
                tpreuni:     parseFloat(d.tpreuni) || 0,
                timport:     parseFloat(d.timport) || 0,
                tkardex:     parseFloat(d.tkardex) || 0,
                tcencos:     d.tcencos     || '',
                tcodproc:    d.tcodproc    || '',
                tcodsubproc: d.tcodsubproc || '',
                tcodacti:    d.tcodacti    || '',
                tcodtarea:   d.tcodtarea   || '',
            })),
        };

        const btn = document.getElementById('me-btn-guardar');
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        try {
            const res  = await fetch(BASE + '/cabecera/' + cab.treg, {
                method:  'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload),
            });
            const json = await res.json();

            if (json.success) {
                Notification.success('Movimiento REG ' + cab.treg + ' actualizado correctamente.');
                cerrarEditar();
                cargarMisSalidas(); // refrescar la lista
            } else {
                errEl.textContent = json.error || 'Error al guardar';
            }
        } catch (e) {
            errEl.textContent = 'Error de comunicación: ' + e.message;
        } finally {
            btn.disabled = false;
            btn.textContent = '✓ Guardar cambios';
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // MIS SALIDAS
    // ─────────────────────────────────────────────────────────────────
    function misSalidas() {
        const modal = document.getElementById('modal-mis-salidas');
        if (modal) modal.style.display = 'flex';
        cargarMisSalidas();
    }

    function cerrarMisSalidas() {
        const modal = document.getElementById('modal-mis-salidas');
        if (modal) modal.style.display = 'none';
    }

    async function cargarMisSalidas() {
        const fecha = document.getElementById('ms-fecha')?.value || '';
        const tbody = document.getElementById('ms-tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:12px;">Cargando...</td></tr>';
        _rowSelMs = null;
        const det = document.getElementById('ms-detalle');
        if (det) det.innerHTML = '<div style="color:#9ca3af;font-size:12px;text-align:center;padding:20px;">Selecciona una fila</div>';

        try {
            const url = BASE + '/salida-rapida/mis-salidas?fecha=' + encodeURIComponent(fecha);
            const res = await fetch(url, { credentials: 'include' });
            const json = await res.json();
            const rows = json.data?.rows || [];
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:16px;">Sin salidas en esta fecha</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map((r, i) =>
                '<tr onclick="SalidasRapidas._selMisSalida(' + r.treg + ',this)">' +
                '<td>' + (r.ttime || '') + '</td>' +
                '<td>' + (r.tfectra || '') + '</td>' +
                '<td>' + (r.tdoc || 'GI') + '</td>' +
                '<td>' + (r.tserie || '') + '</td>' +
                '<td style="text-align:right;">' + (r.tnumfac || '') + '</td>' +
                '<td style="font-weight:600;">' + (r.tcodtra || '') + '</td>' +
                '<td>' + (r.tprocli || '') + '</td>' +
                '<td title="' + (r.nom_solicitante || '') + '">' + _trunc(r.nom_solicitante, 22) + '</td>' +
                '<td style="text-align:center;">' +
                    '<button onclick="event.stopPropagation();SalidasRapidas.abrirEditar(' + r.treg + ')" ' +
                    'style="background:#d97706;border:none;border-radius:4px;color:#fff;cursor:pointer;' +
                    'padding:3px 8px;font-size:11px;font-weight:600;white-space:nowrap;" ' +
                    'title="Editar este movimiento">&#9998; Editar</button>' +
                '</td>' +
                '</tr>'
            ).join('');
        } catch (e) {
            if (tbody) tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#dc2626;padding:16px;">Error cargando datos</td></tr>';
        }
    }

    async function _selMisSalida(treg, trEl) {
        _rowSelMs = treg;
        // Marcar fila activa
        document.querySelectorAll('#ms-tbody tr').forEach(r => r.classList.remove('ms-active'));
        if (trEl) trEl.classList.add('ms-active');

        const det = document.getElementById('ms-detalle');
        if (det) det.innerHTML = '<div style="color:#9ca3af;font-size:12px;text-align:center;padding:16px;">Cargando detalle...</div>';
        try {
            const res = await fetch(BASE + '/cabecera/' + treg, { credentials: 'include' });
            const json = await res.json();
            const cab = json.data?.cabecera || {};
            const items = json.data?.detalle || [];

            const filas = items.map((d, i) =>
                '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td style="font-weight:600;font-size:11px;">' + (d.tcodigo || '') + '</td>' +
                '<td>' + (d.nom_producto || '') + '</td>' +
                '<td>' + (d.tlote || '') + '</td>' +
                '<td style="text-align:right;">' + _num(d.tpeso) + '</td>' +
                '<td>' + (d.tunidad || '') + '</td>' +
                '<td style="text-align:right;font-weight:bold;">' + _num(d.tcantid) + '</td>' +
                '</tr>'
            ).join('');

            if (det) det.innerHTML =
                '<div style="background:#1e3a5f;color:#fff;padding:6px 10px;font-size:11px;border-radius:4px;margin-bottom:6px;">' +
                '<strong>' + (cab.tcodtra || '') + '</strong> &nbsp;|&nbsp; ALM: ' + (cab.talm || '') +
                (cab.gentsa ? ' &rarr; ' + (cab.talr || '') : '') + ' &nbsp;|&nbsp; ' +
                (cab.tprocli || '') + ' ' + (cab.nom_solicitante || '') +
                '</div>' +
                '<table class="ms-tbl"><thead><tr>' +
                '<th>#</th><th>CÓDIGO</th><th>DESCRIPCIÓN</th><th>LOTE</th>' +
                '<th style="text-align:right;">KG</th><th>UND</th><th style="text-align:right;">CANT</th>' +
                '</tr></thead><tbody>' + filas + '</tbody></table>';
        } catch (e) {
            if (det) det.innerHTML = '<div style="color:#dc2626;font-size:12px;padding:12px;">Error cargando detalle</div>';
        }
    }

    function imprimirSeleccionado() {
        if (!_rowSelMs) { Notification.warning('Selecciona un movimiento de la lista primero'); return; }
        const fmt = document.querySelector('input[name="ms-formato"]:checked')?.value || 'A4';
        const win = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
        if (!win) { Notification.warning('Habilita ventanas emergentes para este sitio e intenta de nuevo'); return; }
        _cargarImpresionEnVentana(_rowSelMs, fmt, win);
    }

    // ─────────────────────────────────────────────────────────────────
    // IMPRESIÓN
    // ─────────────────────────────────────────────────────────────────
    async function _abrirImpresion(treg, fmt) {
        // Abrir ventana sinócronamente para evitar popup blocker
        const win = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
        if (!win) { Notification.warning('Habilita ventanas emergentes para este sitio e intenta de nuevo'); return; }
        await _cargarImpresionEnVentana(treg, fmt, win);
    }

    async function _cargarImpresionEnVentana(treg, fmt, win) {
        try {
            const res = await fetch(BASE + '/cabecera/' + treg, { credentials: 'include' });
            const json = await res.json();
            const cab   = json.data?.cabecera || {};
            const items = json.data?.detalle  || [];
            const fechaObj = cab.tfectra ? new Date(cab.tfectra + 'T00:00:00') : new Date();
            const tfectra  = fechaObj.toLocaleDateString('es-PE');
            const total    = items.reduce((s, d) => s + (parseFloat(d.tcantid) || 0), 0);
            const filas = items.map(function(d, i) {
                return '<tr>' +
                    '<td style="text-align:center;">' + (i + 1) + '</td>' +
                    '<td>' + (d.tcodigo || '') + '</td>' +
                    '<td>' + (d.nom_producto || '') + '</td>' +
                    '<td>' + (d.tlote || '') + '</td>' +
                    '<td style="text-align:center;">' + (d.tunidad || '') + '</td>' +
                    '<td style="text-align:right;font-weight:bold;">' + _num(d.tcantid) + '</td>' +
                    '</tr>';
            }).join('');
            const html = _buildPrintHtml({
                treg, tfectra, codtra: cab.tcodtra || '',
                alma: cab.talm || '', almaDest: cab.talr || '',
                ruc: cab.tprocli || '', nombre: cab.tglosa || '',
                filas, fmt, total, nItems: items.length
            });
            win.document.open();
            win.document.write(html);
            win.document.close();
        } catch (e) {
            win.close();
            Notification.error('Error al abrir impresión: ' + e.message);
        }
    }

    function _buildPrintHtml({ treg, tfectra, codtra, alma, almaDest, ruc, nombre, filas, fmt, total, nItems }) {
        const isTicket = fmt === '80mm';
        const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?data=' + encodeURIComponent(String(treg)) + '&size=' + (isTicket ? '80x80' : '100x100');
        const destHtml = almaDest ? '<div><strong>Destino:</strong> ' + almaDest + '</div>' : '';
        const totalStr = total !== undefined ? _num(total) : '';
        const nItemsStr = nItems !== undefined ? String(nItems) : '';

        if (isTicket) {
            return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>MOVIMIENTO SALIDA ' + treg + '</title>' +
                '<style>' +
                '@page{size:80mm auto;margin:4mm}' +
                'body{font-family:Arial,sans-serif;font-size:9px;width:72mm;margin:0 auto;}' +
                '.c{text-align:center;}.b{font-weight:bold;}' +
                '.sep{border-top:1px dashed #000;margin:4px 0;}' +
                'table{width:100%;border-collapse:collapse;}' +
                'th{font-size:8px;border-bottom:1px solid #000;padding:2px 2px;text-align:left;}' +
                'td{font-size:8px;padding:2px 2px;border-bottom:1px dotted #ccc;vertical-align:top;}' +
                '@media print{button{display:none;}}' +
                '</style></head><body>' +
                '<div style="text-align:center;margin-bottom:2px;">' +
                '  <img src="/plantaincubacion/frontend/assets/images/logo.png" alt="" style="max-height:40px;max-width:120px;object-fit:contain;">' +
                '</div>' +
                '<div class="c b" style="font-size:10px;">GRANJA RINCONADA DEL SUR S.A.</div>' +
                '<div class="c" style="font-size:8px;">LA MAR S/N LA JOYA-AQP &middot; TELF.: 492114</div>' +
                '<div class="sep"></div>' +
                '<div class="c b">MOVIMIENTO SALIDA</div>' +
                '<div class="c b">' + alma + ' &ndash; ' + treg + '</div>' +
                '<div class="sep"></div>' +
                '<div><b>RUC:</b> ' + (ruc || '--') + '</div>' +
                '<div><b>SE&Ntilde;OR(ES):</b> ' + (nombre || '--') + '</div>' +
                '<div><b>FECHA:</b> ' + tfectra + '</div>' +
                '<div><b>Almac&eacute;n:</b> ' + alma + '</div>' +
                destHtml +
                '<div><b>Codtra:</b> ' + codtra + '</div>' +
                '<div class="sep"></div>' +
                '<table><thead><tr>' +
                '<th style="text-align:center;">#</th>' +
                '<th>C&Oacute;DIGO</th>' +
                '<th>DESCRIPCI&Oacute;N</th>' +
                '<th>LOTE</th>' +
                '<th style="text-align:center;">UND</th>' +
                '<th style="text-align:right;">CANT</th>' +
                '</tr></thead><tbody>' + filas + '</tbody></table>' +
                '<div style="text-align:right;font-weight:bold;margin-top:4px;font-size:9px;">TOTAL: ' + totalStr + '</div>' +
                '<div class="sep"></div>' +
                '<div class="c" style="font-size:8px;">N&deg; &Iacute;tems: ' + nItemsStr + '</div>' +
                '<div style="text-align:center;margin-top:6px;"><img src="' + qrUrl + '" alt="QR"></div>' +
                '<div class="c" style="font-size:7px;color:#666;margin-top:2px;">REG: ' + treg + '</div>' +
                '<button onclick="window.print()" style="display:block;width:100%;margin-top:8px;padding:5px;background:#1d4ed8;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:10px;">&#128424; Imprimir</button>' +
                '</body></html>';
        }

        // ── Formato A4 ──────────────────────────────────────────────────────
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>MOVIMIENTO SALIDA ' + treg + '</title>' +
            '<style>' +
            '@page{size:A4;margin:15mm 15mm 20mm}' +
            'body{font-family:Arial,sans-serif;font-size:11px;color:#000;margin:0;padding:14mm 14mm 0;}' +
            '@media print{body{padding:0;}}' +
            '.hdr{display:flex;justify-content:space-between;align-items:flex-start;' +
                'padding-bottom:8px;border-bottom:2px solid #000;margin-bottom:10px;}' +
            '.co-name{font-weight:bold;font-size:14px;}' +
            '.co-sub{font-size:9px;color:#444;margin-top:2px;}' +
            '.doc-box{border:2px solid #000;padding:5px 12px;text-align:center;min-width:150px;}' +
            '.doc-box .r{font-size:9px;}' +
            '.doc-box .t{font-weight:bold;font-size:12px;border-top:1px solid #000;margin-top:4px;padding-top:4px;}' +
            '.doc-box .n{font-size:15px;font-weight:bold;margin-top:3px;}' +
            '.info{margin-bottom:10px;}' +
            '.info div{margin-bottom:3px;font-size:10px;}' +
            'table{width:100%;border-collapse:collapse;margin-top:8px;}' +
            'thead th{background:#1e3a5f;color:#fff;padding:5px 8px;font-size:10px;text-align:left;}' +
            'tbody td{padding:4px 8px;border-bottom:1px solid #e5e7eb;font-size:10px;}' +
            'tbody tr:nth-child(even) td{background:#f8fafc;}' +
            '.foot{display:flex;justify-content:space-between;align-items:flex-end;margin-top:14px;}' +
            '.tot-box{border:1px solid #000;padding:6px 16px;text-align:right;}' +
            '.tot-lbl{font-size:9px;}' +
            '.tot-val{font-size:16px;font-weight:bold;}' +
            '@media print{button{display:none;}}' +
            '</style></head><body>' +
            '<div class="hdr">' +
            '  <div style="display:flex;align-items:center;gap:10px;">' +
            '    <img src="/plantaincubacion/frontend/assets/images/logo.png" alt="" style="max-height:50px;max-width:80px;object-fit:contain;">' +
            '    <div>' +
            '      <div class="co-name">GRANJA RINCONADA DEL SUR S.A.</div>' +
            '      <div class="co-sub">LA MAR S/N LA JOYA-AQP<br>TELF.: 492114</div>' +
            '    </div>' +
            '  </div>' +
            '  <div class="doc-box">' +
            '    <div class="r">RUC: 20419158462</div>' +
            '    <div class="t">MOVIMIENTO SALIDA</div>' +
            '    <div class="n">' + alma + ' &ndash; ' + treg + '</div>' +
            '  </div>' +
            '</div>' +
            '<div class="info">' +
            '  <div><strong>SE&Ntilde;OR(ES):</strong> ' + (nombre || '&mdash;') + '</div>' +
            '  <div><strong>RUC:</strong> ' + (ruc || '&mdash;') + '</div>' +
            '  <div><strong>FECHA DE EMISI&Oacute;N:</strong> ' + tfectra + '</div>' +
            '  <div><strong>Almac&eacute;n:</strong> ' + alma + (almaDest ? ' &rarr; ' + almaDest : '') + '</div>' +
            '  <div><strong>Codtra:</strong> ' + codtra + '</div>' +
            '</div>' +
            '<table>' +
            '  <thead><tr>' +
            '    <th style="text-align:center;width:30px;">NRO</th>' +
            '    <th style="width:90px;">C&Oacute;DIGO</th>' +
            '    <th>DESCRIPCI&Oacute;N</th>' +
            '    <th style="width:90px;">LOTE</th>' +
            '    <th style="text-align:center;width:55px;">UNIDAD</th>' +
            '    <th style="text-align:right;width:65px;">CANT.</th>' +
            '  </tr></thead>' +
            '  <tbody>' + filas + '</tbody>' +
            '</table>' +
            '<div class="foot">' +
            '  <div>' +
            '    <img src="' + qrUrl + '" alt="QR" style="display:block;">' +
            '    <div style="font-size:8px;color:#666;margin-top:2px;text-align:center;">REG: ' + treg + '</div>' +
            '  </div>' +
            '  <div>' +
            '    <div class="tot-box">' +
            '      <div class="tot-lbl">TOTAL</div>' +
            '      <div class="tot-val">' + totalStr + '</div>' +
            '    </div>' +
            '    <div style="font-size:9px;color:#666;margin-top:4px;text-align:right;">N&deg; &Iacute;tems: ' + nItemsStr + '</div>' +
            '  </div>' +
            '</div>' +
            '<button onclick="window.print()" style="margin-top:12px;padding:6px 22px;background:#1d4ed8;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:12px;">&#128424; Imprimir</button>' +
            '</body></html>';
    }

    // ─────────────────────────────────────────────────────────────────
    // UTILIDADES
    // ─────────────────────────────────────────────────────────────────
    function _trunc(str, n) {
        if (!str) return '';
        return str.length > n ? str.substring(0, n) + '…' : str;
    }

    function _num(v) {
        const n = parseFloat(v);
        if (isNaN(n)) return '0';
        return n % 1 === 0 ? String(n) : n.toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
    }

    // ─────────────────────────────────────────────────────────────────
    // API PÚBLICA
    // ─────────────────────────────────────────────────────────────────
    return {
        get _initialized() { return _initialized; },
        init,
        cambiarAlmacen,
        buscarProductos,
        filtrarLocal,
        _agregarItem,
        _iniciarEdicion,
        _confirmarEdicion,
        _keyEdicion,
        _quitarIdx,
        quitarSeleccionado,
        quitarTodo,
        cambiarTipo,
        buscarSolicitante,
        nuevoSolicitante,
        _seleccionarCliente,
        nuevo,
        generar,
        vistaPrevia,
        misSalidas,
        cerrarMisSalidas,
        cargarMisSalidas,
        _selMisSalida,
        abrirEditar,
        cerrarEditar,
        guardarEdicion,
        _meEditCant,
        _meQuitarItem,
        imprimirSeleccionado,
        _toggleAyudaTeclado,
    };
})();
