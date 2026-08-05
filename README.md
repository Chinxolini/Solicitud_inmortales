# Los Inmortales — MVP de captura de solicitudes

## Estructura
```
index.php          Landing + paso 1 (correo + aceptación general)
formulario.php      Paso 2 (datos, cédula, firma electrónica del mandato)
cleanup.php          Script cron: elimina casos vencidos (90 días)
config.php           Configuración central
lib/store.php        Capa de almacenamiento JSON (requests.json) + textos legales
data/                requests.json + cédulas subidas (NO debe ser público ni indexable)
```

## Decisiones legales del diseño (por qué se ve así)

**El problema del borrador original:** pedir el correo y marcar un checkbox no constituye
un mandato válido para que un tercero solicite la ficha clínica de otra persona. Los
propios centros de salud (ej. políticas públicas de registros clínicos consultadas) piden,
como mínimo, un **"poder simple" + copia de cédula del titular**, o poder notarial /
firma electrónica avanzada para trámites más formales.

**Qué implementa este MVP:**
- El titular deja evidencia real de su mandato: nombre, RUN, cédula de identidad
  adjunta, y una firma electrónica simple (Ley N° 19.799) con IP y timestamp.
- El texto del mandato cita expresamente los artículos 12 y 13 de la Ley N° 20.584.
- Eliminación automática (no solo declarada) de todos los datos y la cédula a los
  90 días de cerrado el caso, vía `cleanup.php` ejecutado por cron.

**Qué falta antes de producción real:**
- Revisión por un abogado para confirmar si "poder simple" basta para *todos* los
  centros que se quiera contactar (algunos, especialmente públicos, pueden exigir
  poder notarial o firma electrónica avanzada certificada).
- Cifrado en reposo de `requests.json` y de las cédulas (contienen datos sensibles
  bajo la Ley 21.719, vigente desde el 1 de diciembre de 2026).
- Envío real de correos (hoy el enlace del paso 2 se muestra en pantalla para pruebas).
- Mover `data/` fuera del webroot público y bloquear su acceso directo vía servidor web.
- Registro de auditoría de accesos al panel de representantes de salud.

## Cómo probar localmente
```bash
php -S localhost:8000
# abrir http://localhost:8000/index.php
```

---

## Módulo: aprovisionamiento automático de correo (`modules/EmailProvisioner`)

Cuando un expediente llega a `status = "en_gestion"`, este módulo crea automáticamente
su correo `usuario@chiledao.cl` en cPanel (vía UAPI, usuario/contraseña), envía las
credenciales al correo real del solicitante, y deja el expediente en `correo_creado`
(o `error_correo` con el detalle, si algo falla).

### Estructura
```
autoload.php                          Autoloader sin Composer (mapea el namespace)
run_provisioning.php                  Punto de entrada CLI (para cron)
.env.example                          Plantilla de variables — copiar a .env
modules/EmailProvisioner/
    Config.php                        Carga .env sin dependencias
    CPanelClient.php                  Llamadas UAPI (Email::add_pop, Email::list_pops)
    UsernameGenerator.php             mauro -> mauro2 -> mauro3... sin colisiones
    PasswordGenerator.php             Contraseñas seguras (12+ car., mayús/minús/núm/símbolo)
    MailSender.php                    Envío del correo de credenciales (mail() nativo)
    RequestRepository.php             Lee/actualiza data/requests.json (con flock)
    EmailProvisioner.php              Orquesta el flujo completo de un expediente
logs/email-provision.log              Log de cada ejecución (nunca contiene contraseñas)
```

### Instalación
1. Copia `.env.example` a `.env` y completa:
   - `CPANEL_HOST`: normalmente el hostname del servidor (ej. `server123.tuhosting.cl`),
     **no siempre** el dominio del sitio — confírmalo con tu proveedor de hosting.
   - `CPANEL_USER` / `CPANEL_PASS`: las mismas credenciales con las que ya te autenticas
     hoy contra `/execute/Email/add_pop`.
   - `CPANEL_DOMAIN=chiledao.cl`.
   - `WEBMAIL_URL`: revisa cuál te funciona en tu hosting — `https://webmail.chiledao.cl`
     solo sirve si ese subdominio/proxy ya está apuntando al cpsrvd de cPanel (típico con
     AutoSSL + "Webmail" activado en WHM). Si no, usa el puerto nativo:
     `https://chiledao.cl:2096`. Ambos formatos funcionan, prueba cuál responde.
2. Asegúrate de que `.env` **no** quede accesible por web (fuera del `document root`,
   o bloqueado por regla del servidor — igual que `data/`).
3. No se requiere Composer: todo el módulo es PHP puro con autoloader propio.

### Ejecutar
```bash
php run_provisioning.php
```
Recorre todos los expedientes `en_gestion` sin bloque `correo` todavía, y los procesa
uno por uno (un fallo en uno no detiene a los demás).

### Automatizar (cron)
```
*/5 * * * * /usr/bin/php /ruta/a/los-inmortales/run_provisioning.php >> /ruta/a/los-inmortales/logs/cron.log 2>&1
```

### Cómo probar
1. Crea o edita manualmente un registro de prueba en `data/requests.json` con
   `"status": "en_gestion"` y un `email` real al que tengas acceso.
2. Corre `php run_provisioning.php`.
3. Revisa:
   - `logs/email-provision.log` — debe mostrar `Cuenta creada` y `Notificación enviada`.
   - En cPanel → Cuentas de correo, que exista `usuario@chiledao.cl`.
   - Tu bandeja de entrada, el correo con las credenciales.
   - `data/requests.json` — el registro debe tener `status: "correo_creado"` y el
     bloque `correo` con `direccion`, `password_temporal`, etc.
4. Corre `php run_provisioning.php` **de nuevo** sin tocar nada: no debe crear una
   segunda cuenta ni reenviar el correo (por eso el filtro exige `status en_gestion`
   sin bloque `correo` — al pasar a `correo_creado` queda fuera del lote).

### Nota de seguridad importante
`password_temporal` queda en texto plano dentro de `requests.json` para que el equipo
pueda hacer soporte si el solicitante no recibe el correo. Antes de producción real,
considera: (a) cifrar ese campo, o (b) purgarlo automáticamente pasadas 24-48h desde
`fecha_envio` con un script adicional similar a `cleanup.php`. El log nunca contiene
la contraseña, solo el resultado de la operación.
