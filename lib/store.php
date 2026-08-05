<?php
require_once __DIR__ . '/../config.php';

/**
 * Genera un identificador único v4 para cada solicitud.
 */
function li_generate_guid(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Asegura que exista el directorio de datos y el archivo requests.json.
 */
function li_ensure_storage(): void
{
    if (!is_dir(LI_DATA_DIR)) {
        mkdir(LI_DATA_DIR, 0770, true);
    }
    if (!is_dir(LI_CEDULAS_DIR)) {
        mkdir(LI_CEDULAS_DIR, 0770, true);
    }
    if (!file_exists(LI_REQUESTS_FILE)) {
        file_put_contents(LI_REQUESTS_FILE, json_encode([], JSON_PRETTY_PRINT));
    }
}

/**
 * Lee todas las solicitudes con bloqueo compartido.
 */
function li_read_all(): array
{
    li_ensure_storage();
    $fp = fopen(LI_REQUESTS_FILE, 'r');
    if (!$fp) {
        return [];
    }
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
}

/**
 * Reescribe el archivo completo de solicitudes con bloqueo exclusivo.
 */
function li_write_all(array $requests): bool
{
    li_ensure_storage();
    $fp = fopen(LI_REQUESTS_FILE, 'c');
    if (!$fp) {
        return false;
    }
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(array_values($requests), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

/**
 * Agrega una nueva solicitud (paso 1: correo + aceptación de términos).
 */
function li_create_request(string $email, string $ip): array
{
    $requests = li_read_all();

    $entry = [
        'guid'      => li_generate_guid(),
        'email'     => $email,
        'timestamp' => date('Y-m-d H:i:s'),
        'status'    => 'pendiente_datos',
        'ip'        => $ip,
        // se completa recién cuando el caso termina o expira
        'expires_at' => null,
    ];

    $requests[] = $entry;
    li_write_all($requests);

    return $entry;
}

/**
 * Busca una solicitud por GUID. Devuelve null si no existe.
 */
function li_find_by_guid(string $guid): ?array
{
    foreach (li_read_all() as $req) {
        if (($req['guid'] ?? null) === $guid) {
            return $req;
        }
    }
    return null;
}

/**
 * Completa una solicitud con los datos médicos detallados y la evidencia de mandato,
 * y fija la fecha de expiración/eliminación automática.
 */
function li_complete_request(string $guid, array $detalle, array $mandato): bool
{
    $requests = li_read_all();
    $found = false;

    foreach ($requests as &$req) {
        if (($req['guid'] ?? null) === $guid) {
            $req['status']     = 'en_gestion';
            $req['detalle']    = $detalle;
            $req['mandato']    = $mandato;
            $req['expires_at'] = date('Y-m-d H:i:s', strtotime('+' . LI_RETENTION_DAYS . ' days'));
            $found = true;
            break;
        }
    }
    unset($req);

    if ($found) {
        li_write_all($requests);
    }
    return $found;
}

/**
 * Texto formal del mandato simple que el titular firma electrónicamente en el paso 2.
 * Se apoya en el Art. 12-13 de la Ley 20.584 (derecho del titular / representación autorizada)
 * y en la Ley 19.799 sobre documentos y firma electrónica (firma electrónica simple).
 */
function li_texto_mandato(string $nombre, string $run, array $centros, string $periodo): string
{
    $listaCentros = implode(', ', $centros);
    return "Mediante este acto, yo, {$nombre}, RUN {$run}, en mi calidad de titular de la ficha clínica, "
        . "otorgo mandato simple a \"Los Inmortales\" para que, en mi representación y por un plazo máximo "
        . "de 3 meses desde esta fecha, solicite ante los siguientes centros de salud: {$listaCentros}, "
        . "copia de mi ficha clínica y antecedentes médicos correspondientes al período: {$periodo}, "
        . "conforme a los artículos 12 y 13 de la Ley N° 20.584 sobre Derechos y Deberes de los Pacientes. "
        . "Declaro haber sido informado(a) de que: (1) se me asignará un correo de dominio privado exclusivo "
        . "para el seguimiento de este caso; (2) mis datos serán procesados en un panel seguro accesible solo "
        . "por representantes de salud habilitados; y (3) mis antecedentes serán eliminados de forma "
        . "automática e irreversible transcurridos " . LI_RETENTION_DAYS . " días desde el término del mandato.";
}
