<?php

namespace LosInmortales\EmailProvisioner;

/**
 * Cliente mínimo para la UAPI de cPanel usando autenticación por usuario/contraseña
 * (Basic Auth contra el puerto de cPanel), tal como ya lo tienen comprobado con
 * Email::add_pop y Email::list_pops.
 */
final class CPanelClient
{
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private bool $verifySsl;

    public function __construct(string $host, int $port, string $user, string $pass, bool $verifySsl = true)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
        $this->verifySsl = $verifySsl;
    }

    public static function fromConfig(): self
    {
        return new self(
            Config::get('CPANEL_HOST', ''),
            Config::getInt('CPANEL_PORT', 2083),
            Config::get('CPANEL_USER', ''),
            Config::get('CPANEL_PASS', ''),
            Config::getBool('CPANEL_VERIFY_SSL', true)
        );
    }

    /**
     * Ejecuta una llamada UAPI: /execute/{module}/{function}
     * Usa POST para que los parámetros (incluida la contraseña al crear un correo)
     * no queden expuestos en los logs de acceso del servidor.
     *
     * @throws CPanelException
     */
    public function call(string $module, string $function, array $params = []): array
    {
        $url = sprintf('https://%s:%d/execute/%s/%s', $this->host, $this->port, $module, $function);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_USERPWD        => "{$this->user}:{$this->pass}",
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0,
            CURLOPT_TIMEOUT        => 20,
        ]);

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new CPanelException("Error de conexión con cPanel ({$module}/{$function}): {$curlError}");
        }

        if ($httpCode === 401) {
            throw new CPanelException('Autenticación rechazada por cPanel (usuario/contraseña incorrectos).');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new CPanelException("Respuesta no válida de cPanel ({$module}/{$function}), HTTP {$httpCode}.");
        }

        $status = $decoded['status'] ?? $decoded['result']['status'] ?? null;
        if ((int) $status !== 1) {
            $errors = $decoded['errors'] ?? $decoded['result']['errors'] ?? ['Error desconocido de UAPI'];
            throw new CPanelException(
                "UAPI {$module}/{$function} devolvió error: " . implode('; ', (array) $errors)
            );
        }

        return $decoded;
    }

    public function addPop(string $domain, string $localPart, string $password, int $quotaMb): array
    {
        return $this->call('Email', 'add_pop', [
            'domain'   => $domain,
            'email'    => $localPart,
            'password' => $password,
            'quota'    => $quotaMb,
        ]);
    }

    /**
     * @return string[] Local-parts (sin @dominio) ya existentes bajo el dominio.
     */
    public function listPopUsernames(string $domain): array
    {
        $result = $this->call('Email', 'list_pops', ['domain' => $domain]);
        $data = $result['result']['data'] ?? $result['data'] ?? [];

        $usernames = [];
        foreach ($data as $row) {
            $addr = $row['email'] ?? $row['login'] ?? null;
            if ($addr) {
                $usernames[] = strtolower(strstr($addr, '@', true) ?: $addr);
            }
        }
        return $usernames;
    }
}

final class CPanelException extends \RuntimeException
{
}
