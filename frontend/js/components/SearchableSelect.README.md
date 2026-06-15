# SearchableSelect Component

Componente JavaScript para convertir elementos `<select>` nativos en selectores con búsqueda integrada y navegación mejorada.

## ✨ Características

- 🔍 **Campo de búsqueda integrado** al desplegar el selector
- ⚡ **Filtrado en tiempo real** escribiendo
- ⏭️ **Auto-avance al siguiente campo** al seleccionar una opción o presionar Enter
- ⌨️ **Navegación completa con teclado**:
  - `↑` / `↓` - Navegar entre opciones
  - `Enter` - Seleccionar opción actual
  - `Esc` - Cerrar dropdown
  - `Espacio` / `Enter` - Abrir dropdown desde el campo
- 🎨 **Estilos personalizados** con scrollbar bonito
- 🔄 **Actualización dinámica** de opciones
- 📱 **Responsive** y mobile-friendly

## 🚀 Uso Básico

### 1. Incluir archivos necesarios

```html
<!-- CSS -->
<link rel="stylesheet" href="../css/components/searchable-select.css">

<!-- JavaScript -->
<script src="../js/components/SearchableSelect.js"></script>
```

### 2. HTML normal

```html
<select id="mi-selector" class="input-field">
    <option value="">-- Seleccionar --</option>
    <option value="1">Opción 1</option>
    <option value="2">Opción 2</option>
    <option value="3">Opción 3</option>
</select>
```

### 3. Inicializar

```javascript
// Inicializar todos los selectores automáticamente
initSearchableSelects();

// O inicializar un selector específico
const select = document.getElementById('mi-selector');
new SearchableSelect(select);
```

## ⚙️ Opciones de configuración

```javascript
new SearchableSelect(selectElement, {
    placeholder: 'Buscar...',           // Texto del campo de búsqueda
    noResults: 'Sin resultados',        // Mensaje cuando no hay resultados
    autoFocus: true,                    // Auto-enfocar campo de búsqueda al abrir
    moveToNextOnSelect: true            // Pasar al siguiente campo al seleccionar
});
```

## 📦 API

### Métodos de instancia

```javascript
const instance = new SearchableSelect(selectElement);

instance.open();           // Abrir dropdown
instance.close();          // Cerrar dropdown
instance.toggle();         // Toggle open/close
instance.loadOptions();    // Recargar opciones desde el <select> original
instance.destroy();        // Destruir instancia y restaurar select original
```

### Actualizar opciones dinámicamente

```javascript
// Después de modificar el <select> original, actualizar el SearchableSelect
const select = document.getElementById('mi-selector');
select.innerHTML = '<option value="">Nuevas opciones...</option>';

// Actualizar la instancia
if (select.searchableSelectInstance) {
    select.searchableSelectInstance.loadOptions();
}
```

## 🎯 Ejemplo completo

```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="../css/components/searchable-select.css">
</head>
<body>
    <form>
        <label>Provincia</label>
        <select id="provincia" class="input-field">
            <option value="">-- Provincia --</option>
            <option value="CZ">CUZCO</option>
            <option value="AQ">AREQUIPA</option>
            <option value="PN">PUNO</option>
        </select>

        <label>Distrito</label>
        <select id="distrito" class="input-field">
            <option value="">-- Distrito --</option>
        </select>
    </form>

    <script src="../js/components/SearchableSelect.js"></script>
    <script>
        // Inicializar automáticamente todos los selects
        document.addEventListener('DOMContentLoaded', () => {
            initSearchableSelects();

            // Ejemplo de select dependiente
            document.getElementById('provincia').addEventListener('change', (e) => {
                const distritoSel = document.getElementById('distrito');
                distritoSel.innerHTML = '<option value="">-- Distrito --</option>' +
                    '<option value="D1">Distrito 1</option>';

                // Actualizar SearchableSelect
                if (distritoSel.searchableSelectInstance) {
                    distritoSel.searchableSelectInstance.loadOptions();
                }
            });
        });
    </script>
</body>
</html>
```

## 🚫 Excluir selectores

Para evitar que un `<select>` específico se convierta en SearchableSelect, agregar el atributo `data-no-search`:

```html
<select data-no-search>
    <option>Este select permanecerá normal</option>
</select>
```

## 🎨 Personalización de estilos

Puedes personalizar los estilos modificando el archivo `searchable-select.css` o sobrescribiendo las siguientes clases CSS:

- `.searchable-select` - Contenedor principal
- `.searchable-select-display` - Campo de visualización
- `.searchable-select-dropdown` - Dropdown
- `.searchable-select-search` - Campo de búsqueda
- `.searchable-select-options` - Lista de opciones
- `.searchable-select-option-item` - Item individual
- `.highlighted` - Item resaltado con teclado
- `.selected` - Item actualmente seleccionado

## 📝 Notas

- El componente preserva el `<select>` original oculto, por lo que los formularios siguen funcionando normalmente
- Los eventos `change` del select original se disparan correctamente
- Compatible con validación de formularios HTML5
- El componente observa cambios en el DOM del select original y se actualiza automáticamente

## 🔧 Compatibilidad

- Navegadores modernos (Chrome, Firefox, Safari, Edge)
- Requiere JavaScript ES6+
- No requiere librerías externas (vanilla JavaScript)

## 📄 Licencia

Uso interno - PlantaIncubacion 2026
