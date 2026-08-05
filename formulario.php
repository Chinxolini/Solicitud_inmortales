<?php
require_once __DIR__ . '/lib/store.php';

$token = $_GET['token'] ?? '';
$request = $token ? li_find_by_guid($token) : null;
$errors = [];
$done = false;

if (!$request) {
    http_response_code(404);
}

if ($request && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $run      = trim($_POST['run'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $centros  = array_filter(array_map('trim', explode(',', $_POST['centros'] ?? '')));
    $periodo  = trim($_POST['periodo'] ?? '');
    $firma    = trim($_POST['firma'] ?? '');
    $declara  = isset($_POST['declara_veraz']);

    if ($nombre === '') $errors[] = 'Ingresa tu nombre completo.';
    if (!preg_match('/^\d{7,8}-[\dkK]$/', $run)) $errors[] = 'Ingresa un RUN válido (ej. 12345678-9).';
    if ($telefono === '') $errors[] = 'Ingresa un teléfono de contacto.';
    if (empty($centros)) $errors[] = 'Indica al menos un centro de salud.';
    if ($periodo === '') $errors[] = 'Indica el período a solicitar.';
    if (strcasecmp($firma, $nombre) !== 0) $errors[] = 'La firma electrónica debe coincidir exactamente con tu nombre completo.';
    if (!$declara) $errors[] = 'Debes declarar que la información es veraz y otorgar el mandato.';

    // Cédula de identidad (respaldo del mandato simple)
    $cedulaPath = null;
    if (empty($errors)) {
        if (!isset($_FILES['cedula']) || $_FILES['cedula']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Adjunta una foto o escaneo de tu cédula de identidad.';
        } else {
            $file = $_FILES['cedula'];
            $mime = mime_content_type($file['tmp_name']);
            if ($file['size'] > LI_CEDULA_MAX_BYTES) {
                $errors[] = 'El archivo de la cédula supera el tamaño máximo permitido (5 MB).';
            } elseif (!in_array($mime, LI_CEDULA_ALLOWED_MIME, true)) {
                $errors[] = 'Formato de cédula no permitido. Usa JPG, PNG o PDF.';
            } else {
                li_ensure_storage();
                $ext = $mime === 'application/pdf' ? 'pdf' : ($mime === 'image/png' ? 'png' : 'jpg');
                $cedulaPath = LI_CEDULAS_DIR . '/' . $request['guid'] . '.' . $ext;
                if (!move_uploaded_file($file['tmp_name'], $cedulaPath)) {
                    $errors[] = 'No pudimos guardar el archivo de la cédula, intenta nuevamente.';
                    $cedulaPath = null;
                }
            }
        }
    }

    if (empty($errors)) {
        $detalle = [
            'nombre'   => $nombre,
            'run'      => $run,
            'telefono' => $telefono,
            'centros'  => array_values($centros),
            'periodo'  => $periodo,
        ];

        $mandato = [
            'texto'        => li_texto_mandato($nombre, $run, $centros, $periodo),
            'firma'        => $firma,
            'firmado_en'   => date('Y-m-d H:i:s'),
            'ip'           => $_SERVER['REMOTE_ADDR'] ?? 'desconocida',
            'cedula_path'  => $cedulaPath ? basename($cedulaPath) : null,
        ];

        li_complete_request($request['guid'], $detalle, $mandato);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Los Inmortales — Completa tu solicitud</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:opsz,wght@8..60,400;8..60,600;8..60,700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: { extend: { colors: {
      ink:'#14262B', bg:'#F6F8F7', surface:'#FFFFFF',
      primary:'#123D3A', primary2:'#1F6E63', accent:'#B8703F', line:'#DCE6E3',
    } } }
  }
</script>
<style>
  body { font-family: 'Inter', sans-serif; }
  h1, h2, .font-display { font-family: 'Source Serif 4', serif; }
</style>
</head>
<body class="bg-bg text-ink antialiased min-h-screen">

<header class="border-b border-line">
  <div class="max-w-3xl mx-auto px-6 py-5">
    <span class="font-display text-lg tracking-tight text-primary">Los Inmortales</span>
  </div>
</header>

<main class="max-w-3xl mx-auto px-6 py-12">

  <?php if (!$request): ?>

    <div class="bg-surface border border-line rounded-2xl p-8 text-center">
      <h1 class="font-display text-2xl text-primary mb-2">Enlace no válido</h1>
      <p class="text-ink/60 text-sm">Este enlace expiró o no corresponde a una solicitud activa. Vuelve a la
        <a href="/index.php" class="text-primary2 underline">página de inicio</a> para generar uno nuevo.</p>
    </div>

  <?php elseif ($done): ?>

    <div class="bg-surface border border-line rounded-2xl p-8 text-center">
      <svg class="w-10 h-10 text-primary2 mx-auto mb-4" fill="none" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      <h1 class="font-display text-2xl text-primary mb-2">Tu caso quedó en manos de Los Inmortales</h1>
      <p class="text-ink/60 text-sm max-w-md mx-auto">
        Comenzaremos las gestiones ante los centros de salud que indicaste. Te contactaremos a través de
        tu correo de seguimiento con las novedades durante los próximos 3 meses. Toda tu información,
        incluida la cédula, se eliminará automáticamente 90 días después de cerrado el caso.
      </p>
    </div>

  <?php else: ?>

    <h1 class="font-display text-3xl text-primary mb-1">Completa tu solicitud</h1>
    <p class="text-ink/60 text-sm mb-8">Caso asociado a <?= htmlspecialchars($request['email']) ?></p>

    <?php if (!empty($errors)): ?>
      <div class="mb-6 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <ul class="list-disc list-inside space-y-1">
          <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-surface border border-line rounded-2xl p-6 sm:p-8 space-y-6">

      <div class="grid sm:grid-cols-2 gap-5">
        <div>
          <label class="block text-sm font-medium mb-1.5">Nombre completo</label>
          <input type="text" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
            class="w-full rounded-lg border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary2">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1.5">RUN</label>
          <input type="text" name="run" placeholder="12345678-9" required value="<?= htmlspecialchars($_POST['run'] ?? '') ?>"
            class="w-full rounded-lg border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary2">
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1.5">Teléfono de contacto</label>
        <input type="tel" name="telefono" required value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>"
          class="w-full rounded-lg border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary2">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1.5">Centros de salud a consultar</label>
        <input type="text" name="centros" placeholder="Hospital Barros Luco, Cesfam San Joaquín" required
          value="<?= htmlspecialchars($_POST['centros'] ?? '') ?>"
          class="w-full rounded-lg border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary2">
        <p class="text-xs text-ink/50 mt-1">Sepáralos con coma si son varios.</p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1.5">Período a solicitar</label>
        <input type="text" name="periodo" placeholder="Últimos 3 años" required value="<?= htmlspecialchars($_POST['periodo'] ?? '') ?>"
          class="w-full rounded-lg border border-line px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-primary2">
      </div>

      <div class="border-t border-line pt-6">
        <label class="block text-sm font-medium mb-1.5">Cédula de identidad (foto o escaneo, frontal)</label>
        <input type="file" name="cedula" accept=".jpg,.jpeg,.png,.pdf" required
          class="w-full text-sm rounded-lg border border-line px-4 py-2.5 file:mr-3 file:rounded-md file:border-0 file:bg-primary/10 file:text-primary file:px-3 file:py-1.5 file:text-sm">
        <p class="text-xs text-ink/50 mt-1">
          Respalda el mandato simple ante los centros de salud, junto con tu firma electrónica más abajo.
        </p>
      </div>

      <div class="border-t border-line pt-6 space-y-4">
        <p class="text-sm text-ink/70 leading-relaxed">
          Al firmar, otorgas mandato simple a Los Inmortales para solicitar tu ficha clínica en los centros
          indicados, por 3 meses, conforme a los artículos 12 y 13 de la Ley N° 20.584. Puedes leer el
          <a href="/index.php#terminos" class="text-primary2 underline">texto completo del mandato</a>.
        </p>

        <div>
          <label class="block text-sm font-medium mb-1.5">Firma electrónica (escribe tu nombre completo)</label>
          <input type="text" name="firma" required value="<?= htmlspecialchars($_POST['firma'] ?? '') ?>"
            class="w-full rounded-lg border border-line px-4 py-2.5 text-sm font-display italic focus:outline-none focus:ring-2 focus:ring-primary2"
            placeholder="Tu nombre completo, tal como aparece en tu cédula">
        </div>

        <label class="flex items-start gap-3 text-sm text-ink/70 cursor-pointer">
          <input type="checkbox" name="declara_veraz" required class="mt-0.5 rounded border-line text-primary focus:ring-primary2">
          <span>Declaro que la información proporcionada es veraz y otorgo el mandato descrito arriba.</span>
        </label>
      </div>

      <button type="submit" class="w-full bg-primary text-white rounded-lg py-3 text-sm font-medium hover:bg-primary2 transition">
        Firmar y enviar mi solicitud
      </button>
    </form>

  <?php endif; ?>

</main>
</body>
</html>
