<?php

namespace LosInmortales\EmailProvisioner;

/**
 * Logger de archivo plano. Por diseño, ningún método de esta clase debe recibir
 * contraseñas o secretos — solo GUID, usuario (sin password) y resultados.
 */
final class Logger
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
    }

    public function info(string $guid, string $message): void
    {
        $this->write('INFO', $guid, $message);
    }

    public function error(string $guid, string $message): void
    {
        $this->write('ERROR', $guid, $message);
    }

    private function write(string $level, string $guid, string $message): void
    {
        $line = sprintf(
            '[%s] %s guid=%s %s%s',
            date('Y-m-d H:i:s'),
            $level,
            $guid,
            $message,
            PHP_EOL
        );
        file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX);
    }
}
