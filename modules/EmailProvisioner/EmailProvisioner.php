<?php

namespace LosInmortales\EmailProvisioner;

/**
 * Coordina el flujo completo para UN expediente:
 * generar usuario -> crear cuenta en cPanel -> enviar credenciales -> actualizar JSON.
 */
final class EmailProvisioner
{
    /** @var CPanelClient */
    private $cpanel;

    /** @var MailSender */
    private $mailer;

    /** @var RequestRepository */
    private $repository;

    /** @var Logger */
    private $logger;

    /** @var UsernameGenerator */
    private $usernames;

    /** @var PasswordGenerator */
    private $passwords;

    /** @var string */
    private $domain;

    /** @var int */
    private $quotaMb;

    /** @var string */
    private $webmailUrl;

    public function __construct(
        CPanelClient $cpanel,
        MailSender $mailer,
        RequestRepository $repository,
        Logger $logger,
        UsernameGenerator $usernames,
        PasswordGenerator $passwords,
        $domain,
        $quotaMb,
        $webmailUrl
    ) {
        $this->cpanel = $cpanel;
        $this->mailer = $mailer;
        $this->repository = $repository;
        $this->logger = $logger;
        $this->usernames = $usernames;
        $this->passwords = $passwords;
        $this->domain = $domain;
        $this->quotaMb = (int)$quotaMb;
        $this->webmailUrl = $webmailUrl;
    }

    /**
     * Procesa un único expediente.
     */
    public function procesar(array $request)
    {
        $guid = isset($request['guid']) ? $request['guid'] : null;
        $emailOriginal = isset($request['email']) ? $request['email'] : null;

        $nombre = isset($request['detalle']['nombre'])
            ? $request['detalle']['nombre']
            : ($emailOriginal ?: 'solicitante');

        if (!$guid || !$emailOriginal) {
            $this->logger->error(
                $guid ?: 'desconocido',
                'Expediente sin guid o sin email original, se omite.'
            );
            return false;
        }

        try {

            $existentes = $this->cpanel->listPopUsernames($this->domain);

            $localPart = $this->usernames->generate(
                $emailOriginal,
                $existentes
            );

            $correoAsignado = $localPart . '@' . $this->domain;

            $password = $this->passwords->generate();

            $this->cpanel->addPop(
                $this->domain,
                $localPart,
                $password,
                $this->quotaMb
            );

            $this->logger->info(
                $guid,
                "Cuenta creada en cPanel: {$correoAsignado}"
            );

            $enviado = $this->mailer->enviarCredenciales(
                $emailOriginal,
                $nombre,
                $correoAsignado,
                $password,
                $this->webmailUrl
            );

            if (!$enviado) {
                throw new \RuntimeException(
                    "La cuenta {$correoAsignado} se creó pero el correo de notificación no pudo enviarse."
                );
            }

            $this->repository->markSuccess($guid, array(
                'usuario' => $localPart,
                'direccion' => $correoAsignado,
                'password_temporal' => $password,
                'creado_en' => date('Y-m-d H:i:s'),
                'correo_enviado' => true,
                'fecha_envio' => date('Y-m-d H:i:s'),
            ));

            $this->logger->info(
                $guid,
                "Notificación enviada a {$emailOriginal}. Expediente actualizado a correo_creado."
            );

            return true;

        } catch (\Throwable $e) {

            $this->logger->error(
                $guid,
                'Fallo en aprovisionamiento: ' . $e->getMessage()
            );

            try {

                $this->repository->markError(
                    $guid,
                    $e->getMessage()
                );

            } catch (\Throwable $inner) {

                $this->logger->error(
                    $guid,
                    'Además falló al escribir el error en el JSON: ' . $inner->getMessage()
                );
            }

            return false;
        }
    }
}