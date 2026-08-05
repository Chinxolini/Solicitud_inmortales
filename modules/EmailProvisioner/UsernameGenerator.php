<?php

namespace LosInmortales\EmailProvisioner;

/**
 * Deriva un nombre de usuario válido para cPanel a partir del correo original
 * del solicitante, y evita colisiones consultando los correos ya existentes
 * bajo el dominio (Email::list_pops).
 */
final class UsernameGenerator
{
    /**
     * @param string   $originalEmail Correo real del solicitante (ej. mauro@gmail.com)
     * @param string[] $existingUsernames Local-parts ya usados bajo el dominio destino
     */
    public function generate(string $originalEmail, array $existingUsernames): string
    {
        $base = $this->sanitize(strstr($originalEmail, '@', true) ?: $originalEmail);

        if ($base === '') {
            $base = 'expediente';
        }

        $existing = array_map('strtolower', $existingUsernames);

        if (!in_array($base, $existing, true)) {
            return $base;
        }

        $suffix = 2;
        while (in_array($base . $suffix, $existing, true)) {
            $suffix++;
        }

        return $base . $suffix;
    }

    /**
     * Deja solo caracteres válidos para un local-part de cPanel: minúsculas,
     * números, punto y guion. Quita tildes/ñ y cualquier otro símbolo.
     */
    private function sanitize(string $localPart): string
    {
        $localPart = strtolower($localPart);

        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', $localPart);
        if ($transliterated !== false) {
            $localPart = $transliterated;
        }

        $localPart = preg_replace('/[^a-z0-9._-]/', '', $localPart) ?? '';
        $localPart = trim($localPart, '._-');

        return $localPart;
    }
}
