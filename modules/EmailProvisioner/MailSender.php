<?php

namespace LosInmortales\EmailProvisioner;

/**
 * Envía el correo de notificación con las credenciales del correo de expediente.
 * Usa mail() nativo (funciona de forma estándar en hosting cPanel con el dominio
 * ya configurado). Si más adelante se necesita mayor entregabilidad/trazabilidad,
 * esta clase es el único punto a reemplazar por un envío vía SMTP/PHPMailer.
 */
final class MailSender
{
    private string $fromAddress;
    private string $fromName;

    public function __construct(string $fromAddress, string $fromName)
    {
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
    }

    public static function fromConfig(): self
    {
        return new self(
            Config::get('MAIL_FROM', 'soporte@chiledao.cl'),
            Config::get('MAIL_FROM_NAME', 'Los Inmortales')
        );
    }

    public function enviarCredenciales(
        string $destinatario,
        string $nombreSolicitante,
        string $correoAsignado,
        string $passwordTemporal,
        string $webmailUrl
    ): bool {
        $asunto = 'Se ha creado su correo privado para el seguimiento del expediente';

        $cuerpo = $this->plantilla($nombreSolicitante, $correoAsignado, $passwordTemporal, $webmailUrl);

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromAddress . '>',
            'Reply-To: ' . $this->fromAddress,
            'X-Mailer: LosInmortales-EmailProvisioner',
        ];

        return mail($destinatario, $asunto, $cuerpo, implode("\r\n", $headers));
    }

    private function plantilla(string $nombre, string $correo, string $password, string $webmailUrl): string
    {
        return <<<TEXT
Hola {$nombre},

Se ha creado tu correo privado para el seguimiento de tu expediente en Los Inmortales.

Correo asignado:   {$correo}
Contraseña temporal: {$password}
Acceso a Webmail:  {$webmailUrl}

IMPORTANTE:
- Debes cambiar esta contraseña de inmediato la primera vez que inicies sesión.
- Este correo se utilizará exclusivamente para comunicaciones relacionadas con tu
  expediente durante el período de gestión (hasta 3 meses).
- No compartas estas credenciales con terceros.

Si no reconoces esta solicitud, contáctanos respondiendo a este mismo correo.

— Equipo Los Inmortales
TEXT;
    }
}
