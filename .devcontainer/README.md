# 🌌 AuroxLink DevContainer

Este devcontainer proporciona un entorno de desarrollo completo para **AuroxLink** con todas las dependencias necesarias preconfiguradas.

## 🚀 Características

### Incluye:
- **Debian Bookworm** (base del sistema)
- **PHP 8.2** con extensiones necesarias
- **Apache2** configurado y listo para usar
- **Git** para control de versiones
- **Network Manager** y utilidades de red
- **ALSA utilities** para simulación de audio
- **Configuraciones mock de SVXLink** para desarrollo
- **Permisos sudo** preconfigurados para www-data

### Extensiones de VS Code:
- PHP Intelephense (autocompletado inteligente)
- PHP Debug (depuración con Xdebug)
- PHP Tools (análisis de código)
- Docker (gestión de contenedores)
- Prettier (formateo de código)
- YAML (edición de configuraciones)

## 📦 Requisitos

1. **Docker Desktop** instalado y ejecutándose
2. **Visual Studio Code** con la extensión **Remote - Containers**

## 🎯 Cómo usar

### Opción 1: Desde VS Code
1. Abre la carpeta del proyecto en VS Code
2. Cuando aparezca la notificación, haz clic en "Reopen in Container"
3. Espera a que se construya el contenedor (primera vez toma unos minutos)
4. ¡Listo! El entorno está configurado

### Opción 2: Desde la paleta de comandos
1. Presiona `F1` o `Ctrl+Shift+P`
2. Escribe: "Remote-Containers: Reopen in Container"
3. Selecciona la opción y espera

## 🌐 Acceso a la aplicación

Una vez iniciado el contenedor:

- **URL de la aplicación**: `http://localhost`
- **Contraseña por defecto**: `password`
- **Puerto Apache**: `80` (mapeado automáticamente)

## 📝 Estructura del entorno

```
/workspaces/auroxlink/          # Tu código del proyecto
/var/www/html/                  # Symlink a tu proyecto
/etc/svxlink/                   # Configuraciones mock de SVXLink
  ├── svxlink.conf              # Config principal (mock)
  └── svxlink.d/
      └── ModuleEchoLink.conf   # Config EchoLink (mock)
/var/log/svxlink                # Log simulado de SVXLink
```

## 🔧 Comandos útiles

### Dentro del contenedor:

```bash
# Reiniciar Apache
sudo service apache2 restart

# Ver logs de Apache
sudo tail -f /var/log/apache2/error.log

# Ver logs de acceso
sudo tail -f /var/log/apache2/access.log

# Editar configuración SVXLink mock
sudo nano /etc/svxlink/svxlink.conf

# Verificar permisos
ls -la /etc/svxlink/

# Probar PHP
php -v

# Ver estado de Apache
sudo service apache2 status
```

## 🐛 Depuración

### PHP Debugging:
El entorno está preconfigurado para depuración con Xdebug (si lo necesitas en el futuro).

### Logs:
- **Apache error log**: `/var/log/apache2/error.log`
- **Apache access log**: `/var/log/apache2/access.log`
- **SVXLink log (mock)**: `/var/log/svxlink`

## 🔐 Seguridad

**⚠️ IMPORTANTE**: Este entorno es SOLO para desarrollo local.

- Las contraseñas están configuradas por defecto
- Los permisos sudo son permisivos
- No usar en producción
- El hash MD5 por defecto es `5f4dcc3b5aa765d61d8327deb882cf99` = "password"

## 🛠️ Personalización

### Cambiar la contraseña:
Edita `/workspaces/auroxlink/includes/environment.php`:
```php
// Genera un nuevo hash MD5 de tu contraseña
$clave_acceso = "tu_hash_md5_aqui";
```

Para generar un hash MD5:
```bash
echo -n "tucontraseña" | md5sum
```

### Agregar extensiones PHP:
Edita `.devcontainer/setup.sh` y agrega paquetes en la sección de instalación de PHP.

### Agregar extensiones de VS Code:
Edita `.devcontainer/devcontainer.json` en la sección `extensions`.

## 🔄 Actualizar el entorno

Si modificas los archivos del devcontainer:

1. Presiona `F1`
2. Escribe: "Remote-Containers: Rebuild Container"
3. Espera a que se reconstruya

## 📚 Notas de desarrollo

### Diferencias con producción:
1. **No hay SVXLink real** - Se usan archivos mock
2. **No hay hardware de radio** - Simulación de audio
3. **Permisos más permisivos** - Para facilitar desarrollo
4. **Sin systemd completo** - Apache se inicia manualmente

### Limitaciones:
- No se pueden probar funciones que requieren hardware real
- Comandos de `systemctl svxlink` no funcionarán (SVXLink no instalado)
- No hay acceso a tarjetas de sonido reales
- Funciones de red WiFi son limitadas

### Lo que SÍ funciona:
- ✅ Toda la interfaz web
- ✅ Edición de configuraciones
- ✅ Sistema de autenticación
- ✅ Gráficos y estadísticas (con datos mock)
- ✅ Sistema de personalización
- ✅ Lógica PHP y backend
- ✅ Validación de formularios
- ✅ Sistema de integridad

## 🆘 Solución de problemas

### Apache no inicia:
```bash
sudo service apache2 start
sudo service apache2 status
```

### Permisos incorrectos:
```bash
sudo chown -R www-data:www-data /workspaces/auroxlink
sudo chmod -R 775 /workspaces/auroxlink
```

### Página en blanco:
Revisa los logs:
```bash
sudo tail -f /var/log/apache2/error.log
```

### Puerto 80 ocupado:
Cambia el puerto en `devcontainer.json`:
```json
"forwardPorts": [8080, 3306],
```

## 📞 Soporte

Para problemas con el proyecto AuroxLink original:
- GitHub: https://github.com/telecov/auroxlink
- Autor: CA2RDP - TelecoViajero

## 📄 Licencia

El devcontainer sigue la misma licencia que el proyecto AuroxLink.

---

🌌 **Happy coding!** - Desarrollado para la comunidad de radioaficionados
