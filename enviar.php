<?php
/**
 * Recibe el formulario de contacto y lo reenvía por correo.
 *
 * Vive en la raíz del sitio y lo ejecuta el PHP de Hostinger. El formulario
 * le manda JSON; devuelve JSON. Si algo falla, el sitio ofrece WhatsApp como
 * salida, así que un error aquí no pierde el contacto.
 */

declare(strict_types=1);

// Remitente y destino son la misma casilla: el correo sale de tu buzón y
// entra en tu buzón. Al responder contestas al cliente, no a ti mismo,
// porque el Reply-To apunta a quien rellenó el formulario.
const DESTINO = 'hola@estudiotabularasa.com';
const REMITENTE = 'hola@estudiotabularasa.com'; // tiene que existir en el dominio
const MAX_LARGO = 4000;

header('Content-Type: application/json; charset=utf-8');

function salir(int $codigo, string $mensaje): never
{
    http_response_code($codigo);
    echo json_encode(['ok' => $codigo === 200, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Quita saltos de línea: es lo que se usa para inyectar cabeceras de correo. */
function limpiaLinea(string $valor): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $valor) ?? '');
}

function limpiaTexto(string $valor): string
{
    return trim(mb_substr($valor, 0, MAX_LARGO));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    salir(405, 'método no permitido.');
}

$crudo = file_get_contents('php://input');
$datos = json_decode($crudo ?: '', true);

if (!is_array($datos)) {
    salir(400, 'no se entendió la petición.');
}

// Campo trampa: los formularios los rellenan los robots, las personas no lo ven.
if (!empty($datos['web'])) {
    salir(200, 'gracias.');
}

$nombre = limpiaLinea((string) ($datos['nombre'] ?? ''));
$correo = limpiaLinea((string) ($datos['correo'] ?? ''));
$plazo = limpiaLinea((string) ($datos['plazo'] ?? ''));
$presupuesto = limpiaLinea((string) ($datos['presupuesto'] ?? ''));
$mensaje = limpiaTexto((string) ($datos['mensaje'] ?? ''));

$servicios = [];
if (isset($datos['servicios']) && is_array($datos['servicios'])) {
    foreach (array_slice($datos['servicios'], 0, 12) as $servicio) {
        $servicios[] = limpiaLinea((string) $servicio);
    }
}

if ($nombre === '' || $correo === '' || $mensaje === '') {
    salir(422, 'faltan el nombre, el correo o el mensaje.');
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    salir(422, 'ese correo no parece válido.');
}

$cuerpo = implode("\n", [
    'Nuevo mensaje desde estudiotabularasa.com',
    '',
    'Nombre:       ' . $nombre,
    'Correo:       ' . $correo,
    'Servicios:    ' . ($servicios ? implode(', ', $servicios) : '—'),
    'Plazo:        ' . ($plazo !== '' ? $plazo : '—'),
    'Presupuesto:  ' . ($presupuesto !== '' ? $presupuesto : '—'),
    '',
    'Mensaje:',
    $mensaje,
    '',
    '—',
    'Recibido el ' . date('d/m/Y H:i'),
]);

$cabeceras = implode("\r\n", [
    'From: Web Tabula Rasa <' . REMITENTE . '>',
    'Reply-To: ' . $nombre . ' <' . $correo . '>',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: PHP/' . phpversion(),
]);

$asunto = '=?UTF-8?B?' . base64_encode('Contacto web — ' . $nombre) . '?=';

if (!mail(DESTINO, $asunto, $cuerpo, $cabeceras)) {
    salir(502, 'no pudimos enviarlo.');
}

salir(200, 'gracias.');
