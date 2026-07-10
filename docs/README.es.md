# Plugin Hotkeys para GLPI

Guarda formularios de tickets y tareas de tickets en GLPI mediante atajos de teclado. Este plugin proporciona operaciones de "Guardado Inteligente" y de "Forzar Guardado", completamente integradas con la arquitectura y los formularios principales de GLPI 11.

## Características

- **Guardado Inteligente (`Ctrl/Cmd + S`):** Guarda de forma inteligente el formulario activo. Si el formulario de tarea está activo, guarda la tarea. De lo contrario, guarda el ticket.
- **Forzar Guardado de Ticket (`Ctrl/Cmd + Alt + S`):** Apunta directamente al formulario del ticket para guardar los cambios, ignorando cualquier cuadro de diálogo de tarea abierto.
- **Nativo de la Plataforma:** Maneja automáticamente la tecla `Cmd` de Mac frente a la tecla `Ctrl` de Windows/Linux.
- **Soporte Dinámico para TIMELINE y AJAX:** Sigue siendo completamente funcional tras transiciones AJAX, adiciones inline, ventanas emergentes y actualizaciones del timeline.
- **Pantalla de Configuración de Administración:** Configura interruptores, graba combinaciones con un oyente de teclado en vivo y activa/desactiva los comentarios visuales.
- **Protección de Envío Seguro:** Evita envíos duplicados y respeta las validaciones nativas de HTML5.

## Versiones Soportadas

- **GLPI:** `11.0.0` o superior
- **PHP:** `8.2` o superior
- **Navegadores:** Chrome/Chromium, Firefox, Edge, Safari

---

## Instalación

### Producción (Desde el Archivo de Lanzamiento)
1. Descarga el último lanzamiento `glpi-hotkeys-plugin-1.0.0.zip` desde la pestaña de Lanzamientos en GitHub.
2. Extrae el archivo. El directorio extraído **debe** llamarse `hotkeys`.
3. Sube la carpeta `hotkeys` al directorio de tu instalación de GLPI en:
   ```text
   GLPI_ROOT/plugins/hotkeys
   ```
4. Inicia sesión en GLPI como administrador y navega a **Administración > Plugins**.
5. Instala y activa **Hotkeys**.

### Instalación de Desarrollo
1. Clona el repositorio en tu directorio de plugins como `hotkeys`:
   ```bash
   git clone https://github.com/JuanCarlosAcostaPeraba/glpi-hotkeys-plugin.git GLPI_ROOT/plugins/hotkeys
   ```
2. Navega al directorio del plugin e instala las dependencias de Node.js:
   ```bash
   cd GLPI_ROOT/plugins/hotkeys
   npm install
   ```
3. Ejecuta el constructor de assets para compilar los archivos de distribución:
   ```bash
   npm run build
   ```

---

## Configuración

Navega a **Administración > Plugins** y haz clic en el icono de configuración de **Hotkeys** para ajustar:
1. **Habilitar/Deshabilitar Guardado Inteligente** y editar sus teclas de atajo.
2. **Habilitar/Deshabilitar Forzar Guardado de Ticket** y editar sus teclas de atajo.
3. **Habilitar/Deshabilitar Comentarios Visuales** (alertas toast).
4. **Restaurar Valores Predeterminados** en cualquier momento.

La interfaz de grabación de atajos captura la siguiente combinación de teclas presionada y la valida para evitar colisiones con el navegador.

---

## Detalles Técnicos y Modelo de Seguridad

- **Sin Omitir Permisos:** El plugin utiliza el método estándar del navegador `form.requestSubmit()` en los botones reales de guardar/actualizar. Los tokens de seguridad estándar de GLPI (CSRF) y los permisos siguen siendo estrictamente aplicados por el backend.
- **Protección de Datos:** No se registran ni se transmiten contenidos de tickets, textos de tareas ni la escritura ordinaria del usuario. El plugin no se conecta a servidores externos.
- **Limpieza al Desinstalar:** La desinstalación normal elimina completamente las configuraciones de la tabla de la base de datos `glpi_configs` y no toca los tickets ni el historial del core de GLPI.

---

## Pruebas

Ejecuta las pruebas unitarias del frontend (Vitest + JSDOM):
```bash
npm test
```

Ejecuta las pruebas unitarias del backend en PHP:
```bash
php tests/php/run.php
```

---

## Licencia

Distribuido bajo la Licencia Pública General de GNU v3 o posterior. Consulta `LICENSE` para más detalles.
