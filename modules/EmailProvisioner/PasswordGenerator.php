<?php

namespace LosInmortales\EmailProvisioner;

/**
 * Genera contraseñas que cumplen las políticas habituales de cPanel:
 * longitud mínima, mayúsculas, minúsculas, números y símbolos, sin
 * caracteres ambiguos (0/O, 1/l/I) para evitar errores de transcripción.
 */
final class PasswordGenerator
{
    private const UPPER   = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    private const LOWER   = 'abcdefghijkmnpqrstuvwxyz';
    private const DIGITS  = '23456789';
    private const SYMBOLS = '!@#%^&*-_=+';

    public function generate(int $length = 16): string
    {
        $length = max(12, $length);
        $pools = [self::UPPER, self::LOWER, self::DIGITS, self::SYMBOLS];

        $password = [];
        // Garantiza al menos un carácter de cada tipo
        foreach ($pools as $pool) {
            $password[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        $all = implode('', $pools);
        while (count($password) < $length) {
            $password[] = $all[random_int(0, strlen($all) - 1)];
        }

        shuffle($password);
        return implode('', $password);
    }
}
