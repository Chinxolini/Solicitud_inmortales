<?php

namespace LosInmortales\EmailProvisioner;

/**
 * Carga variables desde un archivo .env a getenv()/$_ENV, sin dependencias externas.
 * No sobrescribe variables ya definidas en el entorno real.
 */
final class Config
{
    private static $loaded = false;

    public static function load($envPath)
    {
        if (self::$loaded) {
            return;
        }

        if (!is_file($envPath)) {
            throw new \RuntimeException(
                "No se encontrио el archivo de configuraciиоn: {$envPath}. " .
                "Copia .env.example a .env y completa tus credenciales."
            );
        }

        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {

            $line = trim($line);

            // Ignorar lикneas vacикas o comentarios
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            // Debe contener "="
            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            // Eliminar comillas
            $value = trim($value, "\"'");

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get($key, $default = null)
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public static function getBool($key, $default = false)
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), array(
            '1',
            'true',
            'yes',
            'on'
        ), true);
    }

    public static function getInt($key, $default = 0)
    {
        $value = self::get($key);

        return $value === null ? $default : (int)$value;
    }
}