<?php

namespace LosInmortales\EmailProvisioner;

/**
 * Repositorio sobre el mismo data/requests.json que ya usa el sistema de captura
 * (lib/store.php). No se modifica ese archivo; esta clase solo lee/escribe el
 * mismo JSON con bloqueo de archivo, igual que el resto del sistema.
 */
final class RequestRepository
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Expedientes listos para aprovisionar: status en_gestion y que todavía
     * no tienen bloque "correo" (evita reprocesar si el script se corre dos veces).
     */
    public function pendingEnGestion(): array
    {
        $all = $this->readAll();
        return array_values(array_filter($all, function (array $req) {
            return ($req['status'] ?? null) === 'en_gestion' && !isset($req['correo']);
        }));
    }

    public function markSuccess(string $guid, array $correo): void
    {
        $this->updateByGuid($guid, function (array $req) use ($correo) {
            $req['status'] = 'correo_creado';
            $req['correo'] = $correo;
            return $req;
        });
    }

    public function markError(string $guid, string $errorMessage): void
    {
        $this->updateByGuid($guid, function (array $req) use ($errorMessage) {
            $req['status'] = 'error_correo';
            $req['correo_error'] = [
                'mensaje' => $errorMessage,
                'fecha'   => date('Y-m-d H:i:s'),
            ];
            return $req;
        });
    }

    private function updateByGuid(string $guid, callable $mutator): void
    {
        $fp = fopen($this->filePath, 'c+');
        if (!$fp) {
            throw new \RuntimeException("No se pudo abrir {$this->filePath}");
        }

        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $all = json_decode($raw ?: '[]', true);
        if (!is_array($all)) {
            $all = [];
        }

        $found = false;
        foreach ($all as &$req) {
            if (($req['guid'] ?? null) === $guid) {
                $req = $mutator($req);
                $found = true;
                break;
            }
        }
        unset($req);

        if ($found) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode(array_values($all), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($fp);
        }

        flock($fp, LOCK_UN);
        fclose($fp);

        if (!$found) {
            throw new \RuntimeException("No se encontró el expediente {$guid} al intentar actualizarlo.");
        }
    }

    private function readAll(): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }
        $fp = fopen($this->filePath, 'r');
        flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($raw ?: '[]', true);
        return is_array($data) ? $data : [];
    }
}
