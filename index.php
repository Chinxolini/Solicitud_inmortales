<?php
require_once __DIR__ . '/lib/store.php';

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $acepta = isset($_POST['acepta_mandato']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Ingresa un correo electrónico válido.';
    }
    if (!$acepta) {
        $errors[] = 'Debes aceptar el mandato y las condiciones para continuar.';
    }

    if (empty($errors)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
        $entry = li_create_request($email, $ip);

        $link = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://')
            . $_SERVER['HTTP_HOST']
            . '/formulario.php?token=' . urlencode($entry['guid']);

        // Aquí se integraría el envío real del correo (PHPMailer / API transaccional).
        // Por ahora se muestra el enlace en pantalla para pruebas del flujo.
        $success = $link;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Los Inmortales — Centraliza tu historial médico</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          ink:      '#14262B',
          bg:       '#F6F8F7',
          surface:  '#FFFFFF',
          primary:  '#123D3A',
          primary2: '#1F6E63',
          accent:   '#B8703F',
          line:     '#DCE6E3',
        },
        fontFamily: {
          serif: ['"Source Serif 4"', 'serif'],
          sans:  ['Inter', 'sans-serif'],
        },
      },
    },
  }
</script>
<style>
  body { font-family: 'Inter', sans-serif; }
  h1, h2, .font-display { font-family: 'Source Serif 4', serif; }
  @media (prefers-reduced-motion: reduce) {
    * { animation: none !important; transition: none !important; }
  }
</style>
</head>
<body class="bg-bg text-ink antialiased">

  <!-- Barra superior -->
  <header class="border-b border-line">
    <div class="max-w-5xl mx-auto px-6 py-5 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" class="text-primary">
          <path d="M12 2L4 6v6c0 5.2 3.4 9.4 8 10 4.6-.6 8-4.8 8-10V6l-8-4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
          <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span class="font-display text-lg tracking-tight">Los Inmortales</span>
      </div>
      <span class="text-xs text-ink/50 hidden sm:block">Gestión autorizada de historial clínico · Chile</span>
    </div>
  </header>

  <!-- Hero -->
  <section class="max-w-5xl mx-auto px-6 pt-16 pb-12 grid md:grid-cols-5 gap-12 items-start">
    <div class="md:col-span-3">
      <p class="text-accent text-sm font-medium tracking-wide uppercase mb-3">Ley N° 20.584 · Derechos del paciente</p>
      <h1 class="font-display text-4xl sm:text-5xl leading-[1.1] text-primary mb-6">
        Tu historial médico está disperso.<br class="hidden sm:block"> Nosotros lo reunimos por ti.
      </h1>
      <p class="text-ink/70 text-lg leading-relaxed max-w-xl mb-8">
        Con tu autorización expresa, solicitamos formalmente tus fichas clínicas y exámenes
        a los centros de salud que indiques, y centralizamos todo en un panel seguro —
        sin que tengas que hacer una sola llamada.
      </p>

      <!-- Formulario paso 1 -->
      <div class="bg-surface border border-line rounded-2xl p-6 sm:p-8 shadow-sm max-w-lg">
        <?php if ($success): ?>
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-primary2 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <div>
              <p class="font-medium text-ink mb-1">Solicitud registrada</p>
              <p class="text-sm text-ink/70 mb-4">Te enviaremos un correo con el siguiente enlace para completar tus datos. Válido para esta prueba:</p>
              <a href="<?= htmlspecialchars($success) ?>" class="inline-block text-sm bg-primary text-white rounded-lg px-4 py-2 hover:bg-primary2 transition">
                Continuar al formulario detallado →
              </a>
            </div>
          </div>
        <?php else: ?>
          <?php if (!empty($errors)): ?>
            <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
              <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="POST" class="space-y-5">
            <div>
              <label for="email" class="block text-sm font-medium text-ink mb-1.5">Correo electrónico</label>
              <input type="email" name="email" id="email" required placeholder="tu@correo.cl"
                class="w-full rounded-lg border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary2 focus:border-transparent">
            </div>

            <label class="flex items-start gap-3 text-sm text-ink/70 cursor-pointer">
              <input type="checkbox" name="acepta_mandato" required
                class="mt-0.5 rounded border-line text-primary focus:ring-primary2">
              <span>
                Autorizo a <strong>Los Inmortales</strong> a gestionar en mi nombre, por un plazo máximo
                de <strong>3 meses</strong>, la solicitud de mis fichas clínicas ante los centros de salud
                que yo indique (Ley N° 20.584); se me asignará un <strong>correo de seguimiento de dominio
                privado</strong>, y mis datos se procesarán en un <strong>panel seguro</strong> verificado por
                representantes de salud. Este mandato se formaliza con firma electrónica y copia de cédula
                en el siguiente paso.
                <a href="#terminos" class="text-primary2 underline underline-offset-2">Leer condiciones completas</a>.
              </span>
            </label>

            <button type="submit"
              class="w-full bg-primary text-white rounded-lg py-3 text-sm font-medium hover:bg-primary2 transition">
              Iniciar mi solicitud
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Línea de custodia (elemento distintivo) -->
    <div class="md:col-span-2 md:pl-6">
      <div class="relative pl-8">
        <div class="absolute left-[7px] top-1 bottom-1 w-px bg-line"></div>
        <?php
          $pasos = [
            ['n' => '01', 't' => 'Solicitud', 'd' => 'Dejas tu correo y aceptas el mandato simple.'],
            ['n' => '02', 't' => 'Verificación', 'd' => 'Confirmas identidad con cédula y firma electrónica.'],
            ['n' => '03', 't' => 'Gestión, 90 días', 'd' => 'Solicitamos y centralizamos tus fichas clínicas.'],
            ['n' => '04', 't' => 'Eliminación automática', 'd' => 'Tus datos se borran al cerrar el caso, sin pasos manuales.'],
          ];
        ?>
        <?php foreach ($pasos as $i => $p): ?>
          <div class="relative mb-8 last:mb-0">
            <span class="absolute -left-8 top-0 w-4 h-4 rounded-full bg-primary2 border-4 border-bg"></span>
            <p class="text-xs text-accent font-medium tracking-wide"><?= $p['n'] ?></p>
            <p class="font-display text-lg text-ink"><?= $p['t'] ?></p>
            <p class="text-sm text-ink/60 leading-snug"><?= $p['d'] ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- Términos completos -->
  <section id="terminos" class="max-w-3xl mx-auto px-6 py-16 border-t border-line">
    <h2 class="font-display text-2xl text-primary mb-6">Mandato y condiciones legales</h2>
    <div class="space-y-4 text-sm text-ink/70 leading-relaxed">
      <p>
        Este servicio opera al amparo de los artículos 12 y 13 de la <strong>Ley N° 20.584</strong> sobre
        Derechos y Deberes que tienen las personas en relación con acciones vinculadas a su atención en
        salud, que reconocen al titular de la ficha clínica el derecho a solicitarla directamente o a
        través de un representante debidamente autorizado.
      </p>
      <p>
        Al continuar al segundo paso, el titular otorga un <strong>mandato simple</strong> —respaldado con
        copia de su cédula de identidad y firma electrónica simple conforme a la Ley N° 19.799— para que
        Los Inmortales actúe en su representación ante los centros de salud que indique expresamente,
        por un plazo máximo de 3 meses.
      </p>
      <p>
        Durante ese período se habilita un correo de dominio privado exclusivo para el seguimiento del
        caso, y la información se procesa en un panel de acceso restringido a representantes de salud
        habilitados. Conforme a los principios de minimización y limitación del plazo de conservación,
        toda la información del caso —incluida la copia de cédula— se elimina de forma automática e
        irreversible <strong>90 días</strong> después de finalizada la gestión.
      </p>
      <p>
        El titular puede solicitar la eliminación anticipada de sus datos o revocar el mandato en
        cualquier momento, escribiendo desde el correo de seguimiento asignado.
      </p>
    </div>
  </section>

  <footer class="border-t border-line">
    <div class="max-w-5xl mx-auto px-6 py-8 text-xs text-ink/40">
      Los Inmortales — Servicio de gestión documental de salud. No somos un centro asistencial ni
      reemplazamos la atención médica.
    </div>
  </footer>

</body>
</html>
