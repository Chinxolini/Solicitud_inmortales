<?php

/**
 * run_provisioning.php
 *
 * Script de depuración con trazas completas.
 */

// if (php_sapi_name() !== 'cli') {
//     http_response_code(403);
//     exit('Este script solo puede ejecutarse por línea de comandos.');
// }

$runLogPath = __DIR__ . '/logs/run.log';

if (!is_dir(dirname($runLogPath))) {
    @mkdir(dirname($runLogPath), 0770, true);
}

function li_run_log(string $mensaje): void
{
    global $runLogPath;

    file_put_contents(
        $runLogPath,
        '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

/**
 * Imprime en consola y además escribe en run.log
 */
function debug(string $mensaje): void
{
    $texto = "[" . date('H:i:s') . "] " . $mensaje;

    echo $texto . PHP_EOL;

    if (function_exists('ob_flush')) {
        @ob_flush();
    }

    flush();

    li_run_log($mensaje);
}

set_exception_handler(function (\Throwable $e) {

    debug("========== EXCEPCIÓN NO CAPTURADA ==========");
    debug($e->getMessage());
    debug($e->getFile() . ":" . $e->getLine());
    debug($e->getTraceAsString());
});

register_shutdown_function(function () {

    $error = error_get_last();

    if ($error !== null) {

        debug("========== SHUTDOWN ==========");

        debug("Tipo: " . $error['type']);
        debug("Mensaje: " . $error['message']);
        debug("Archivo: " . $error['file']);
        debug("Línea: " . $error['line']);
    }
});

use LosInmortales\EmailProvisioner\Config;
use LosInmortales\EmailProvisioner\CPanelClient;
use LosInmortales\EmailProvisioner\MailSender;
use LosInmortales\EmailProvisioner\RequestRepository;
use LosInmortales\EmailProvisioner\Logger;
use LosInmortales\EmailProvisioner\UsernameGenerator;
use LosInmortales\EmailProvisioner\PasswordGenerator;
use LosInmortales\EmailProvisioner\EmailProvisioner;

debug("==============================================");
debug("INICIO RUN_PROVISIONING");
debug("==============================================");

try {

    debug("Paso 1: Cargando autoload...");
    require_once __DIR__ . '/autoload.php';
    debug("✔ Autoload OK");

    debug("Paso 2: Cargando config.php...");
    require_once __DIR__ . '/config.php';
    debug("✔ config.php OK");

    debug("Paso 3: Cargando .env...");
    Config::load(__DIR__ . '/.env');
    debug("✔ .env OK");

    debug("----------------------------------------------");
    debug("CONFIGURACIÓN");

    debug("CPANEL_HOST: " . Config::get('CPANEL_HOST'));
    debug("CPANEL_USER: " . Config::get('CPANEL_USER'));
    debug("CPANEL_DOMAIN: " . Config::get('CPANEL_DOMAIN'));
    debug("WEBMAIL_URL: " . Config::get('WEBMAIL_URL'));
    debug("CPANEL_QUOTA_MB: " . Config::get('CPANEL_QUOTA_MB'));

    debug("CPANEL_TOKEN cargado: " . (Config::get('CPANEL_TOKEN') ? 'SI' : 'NO'));
    debug("SMTP_HOST cargado: " . (Config::get('SMTP_HOST') ? 'SI' : 'NO'));
    debug("SMTP_USER cargado: " . (Config::get('SMTP_USER') ? 'SI' : 'NO'));
    debug("SMTP_PASSWORD cargado: " . (Config::get('SMTP_PASSWORD') ? 'SI' : 'NO'));

    debug("----------------------------------------------");

    debug("Paso 4: Creando Logger...");
    $logger = new Logger(__DIR__ . '/logs/email-provision.log');
    debug("✔ Logger OK");

    debug("Paso 5: Creando Repository...");
    $repository = new RequestRepository(LI_REQUESTS_FILE);
    debug("✔ Repository OK");

    debug("Paso 6: Creando CPanelClient...");
    $cpanel = CPanelClient::fromConfig();
    debug("✔ CPanelClient OK");

    debug("Paso 7: Creando MailSender...");
    $mailer = MailSender::fromConfig();
    debug("✔ MailSender OK");

    debug("Paso 8: Construyendo EmailProvisioner...");

    $provisioner = new EmailProvisioner(
        $cpanel,
        $mailer,
        $repository,
        $logger,
        new UsernameGenerator(),
        new PasswordGenerator(),
        Config::get('CPANEL_DOMAIN', 'chiledao.cl'),
        Config::getInt('CPANEL_QUOTA_MB', 250),
        Config::get('WEBMAIL_URL', 'https://webmail.chiledao.cl')
    );

    debug("✔ EmailProvisioner OK");

    debug("----------------------------------------------");

    debug("Paso 9: Buscando expedientes pendientes...");

    $pendientes = $repository->pendingEnGestion();

    debug("Cantidad encontrada: " . count($pendientes));

    if (empty($pendientes)) {

        debug("No existen expedientes pendientes.");
        debug("FIN");

        exit(0);
    }

    $ok = 0;
    $fallidos = 0;

    foreach ($pendientes as $i => $request) {

        debug("");
        debug("==============================================");
        debug("SOLICITUD #" . ($i + 1));
        debug("==============================================");

        if (is_array($request)) {

            foreach ($request as $k => $v) {

                if (is_scalar($v) || $v === null) {
                    debug("$k = " . var_export($v, true));
                }
            }
        }

        try {

            debug("Ejecutando procesar()...");

            $resultado = $provisioner->procesar($request);

            debug("procesar() retornó: " . var_export($resultado, true));

            if ($resultado) {
                $ok++;
            } else {
                $fallidos++;
            }

        } catch (\Throwable $e) {

            $fallidos++;

            debug("******** ERROR EN PROCESAR ********");
            debug("Mensaje: " . $e->getMessage());
            debug("Archivo: " . $e->getFile());
            debug("Línea: " . $e->getLine());
            debug("Trace:");
            debug($e->getTraceAsString());
        }

        debug("Fin solicitud #" . ($i + 1));
    }

    debug("");
    debug("==============================================");
    debug("RESUMEN");
    debug("==============================================");

    debug("Pendientes: " . count($pendientes));
    debug("Exitosos : " . $ok);
    debug("Fallidos : " . $fallidos);

    debug("FIN CORRECTO");

} catch (\Throwable $e) {

    debug("");
    debug("##############################################");
    debug("ERROR GENERAL");
    debug("##############################################");

    debug("Mensaje:");
    debug($e->getMessage());

    debug("Archivo:");
    debug($e->getFile());

    debug("Línea:");
    debug((string)$e->getLine());

    debug("Trace:");
    debug($e->getTraceAsString());

    exit(1);
}