<?php
/**
 * cleanup.php
 *
 * Ejecutar periódicamente vía cron (ej. una vez al día):
 *   0 4 * * * /usr/bin/php /ruta/a/los-inmortales/cleanup.php >> /var/log/li-cleanup.log 2>&1
 *
 * Elimina de forma irreversible los casos cuyo plazo de retención (LI_RETENTION_DAYS)
 * ya venció, junto con su archivo de cédula adjunto. Solo debe ejecutarse por CLI.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por línea de comandos.');
}

require_once __DIR__ . '/lib/store.php';

$now = time();
$requests = li_read_all();
$restantes = [];
$eliminados = 0;

foreach ($requests as $req) {
    $expiraEn = $req['expires_at'] ?? null;

    if ($expiraEn !== null && strtotime($expiraEn) <= $now) {
        // Elimina el archivo de cédula asociado, si existe.
        $cedula = $req['mandato']['cedula_path'] ?? null;
        if ($cedula) {
            $path = LI_CEDULAS_DIR . '/' . $cedula;
            if (is_file($path)) {
                unlink($path);
            }
        }
        $eliminados++;
        continue; // no se conserva el registro
    }

    $restantes[] = $req;
}

li_write_all($restantes);

echo "[" . date('Y-m-d H:i:s') . "] Limpieza completada. Casos eliminados: {$eliminados}. Casos activos: " . count($restantes) . PHP_EOL;
