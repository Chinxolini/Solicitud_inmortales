<?php
/**
 * Configuración central de "Los Inmortales".
 * Ajusta estos valores según el entorno real (producción / staging).
 */

// Dominio privado usado para los correos de seguimiento del caso (paso posterior, fuera de este MVP)
define('LI_MAIL_DOMAIN', 'seguimiento.losinmortales.cl');

// Días que se conserva la información del caso desde que se completa el formulario detallado.
// Vencido este plazo, cleanup.php elimina el registro y los documentos adjuntos.
define('LI_RETENTION_DAYS', 90);

// Rutas de almacenamiento (fuera del webroot público en producción real)
define('LI_DATA_DIR', __DIR__ . '/data');
define('LI_REQUESTS_FILE', LI_DATA_DIR . '/requests.json');
define('LI_CEDULAS_DIR', LI_DATA_DIR . '/cedulas');

// Longitud máxima de archivo de cédula (5 MB) y tipos permitidos
define('LI_CEDULA_MAX_BYTES', 5 * 1024 * 1024);
define('LI_CEDULA_ALLOWED_MIME', ['image/jpeg', 'image/png', 'application/pdf']);

// Zona horaria para timestamps consistentes
date_default_timezone_set('America/Santiago');
