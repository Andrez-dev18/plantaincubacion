<?php
require_once __DIR__ . '/../services/GuiaElectronicaService.php';
require_once __DIR__ . '/../services/ConfigApiService.php';

class GuiaElectronicaController
{
    private $service;
    private $configApiService;

    public function __construct($db)
    {
        $this->service = new GuiaElectronicaService($db);
        $this->configApiService = new ConfigApiService($db);
    }

    /**
     * Retorna todas las zonas/almacenes en formato JSON
     */
    public function getZonas()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $zonas = $this->service->listarAlmacenes();

            echo json_encode([
                "success" => true,
                "data" => $zonas
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener las zonas: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna todos los tipos de transporte en formato JSON
     */
    public function getTiposTransporte()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $tipos = $this->service->listarTiposTransporte();

            echo json_encode([
                "success" => true,
                "data" => $tipos
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los tipos de transporte: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna el listado de transportistas filtrado o completo en formato JSON
     */
    public function getTransportistas()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $search = $_GET['q'] ?? null;
            $transportistas = $this->service->listarTransportistas($search);

            echo json_encode([
                "success" => true,
                "data" => $transportistas
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los transportistas: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    public function getConductores()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $search = $_GET['q'] ?? null;
            $rucTransportista = $_GET['rucTransportista'] ?? null;
            $mostrarTodos = isset($_GET['mostrarTodos']) && ($_GET['mostrarTodos'] === 'true' || $_GET['mostrarTodos'] == 1);
            $conductores = $this->service->listarConductores($search, $rucTransportista, $mostrarTodos);

            echo json_encode([
                "success" => true,
                "data" => $conductores
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los conductores: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            exit;
        }
    }

    /**
     * Retorna el listado de camiones filtrado o completo en formato JSON
     */
    public function getCamiones()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $search = $_GET['q'] ?? null;
            $rucTransportista = $_GET['rucTransportista'] ?? null;
            $camiones = $this->service->listarCamiones($search, $rucTransportista);

            echo json_encode([
                "success" => true,
                "data" => $camiones
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los camiones: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna el listado de clientes filtrado o completo en formato JSON
     */
    public function getClientes()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $search = $_GET['q'] ?? null;
            $clientes = $this->service->listarClientes($search);

            echo json_encode([
                "success" => true,
                "data" => $clientes
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los clientes: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna el listado de artículos (mitm) filtrado o completo en formato JSON
     */
    public function getArticulos()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $search = $_GET['q'] ?? null;
            $almacen = $_GET['almacen'] ?? null;
            $anio = isset($_GET['anio']) ? intval($_GET['anio']) : null;
            $articulos = $this->service->listarArticulos($search, $almacen, $anio);

            echo json_encode([
                "success" => true,
                "data" => $articulos
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los artículos: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna el listado de lotes para un almacén, artículo y año en formato JSON
     */
    public function getLotes()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $almacen = $_GET['almacen'] ?? '';
            $articulo = $_GET['articulo'] ?? '';
            $anio = intval($_GET['anio'] ?? date('Y'));

            $lotes = $this->service->listarLotes($almacen, $articulo, $anio);

            echo json_encode([
                "success" => true,
                "data" => $lotes
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los lotes: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna las series y descripciones filtradas por almacén y cliente en formato JSON
     */
    public function getSeries()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $almacen = $_GET['almacen'] ?? '';
            $cliente = $_GET['cliente'] ?? '';

            $series = $this->service->listarSeries($almacen, $cliente);

            echo json_encode([
                "success" => true,
                "data" => $series
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener las series: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna los motivos de traslado en formato JSON
     */
    public function getMotivosTraslado()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $motivos = $this->service->listarMotivosTraslado();

            echo json_encode([
                "success" => true,
                "data" => $motivos
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener los motivos de traslado: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna la dirección y ubigeo de un cliente en formato JSON
     */
    public function getDireccionCliente()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $codigo = $_GET['codigo'] ?? '';
            $direccion = $this->service->obtenerDireccionCliente($codigo);

            echo json_encode([
                "success" => true,
                "data" => $direccion
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener la dirección del cliente: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna los centros de costo (cencos) filtrados o completos en formato JSON
     */
    public function getCencos()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $search = $_GET['q'] ?? null;
            $cencos = $this->service->listarCencos($search);

            echo json_encode([
                "success" => true,
                "data" => $cencos
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener cencos: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Retorna los galpones asociados a un cencos en formato JSON
     */
    public function getGalpones()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $cencos = $_GET['cencos'] ?? '';
            $galpones = $this->service->listarGalponesPorCencos($cencos);

            echo json_encode([
                "success" => true,
                "data" => $galpones
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al obtener galpones: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Guarda la cabecera y el detalle de la Guía Electrónica
     */
    public function guardarGuia()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                throw new Exception("Payload de entrada vacío o inválido.");
            }

            $cabecera = $input['cabecera'] ?? null;
            $detalle = $input['detalle'] ?? null;
            $accion = $input['accion'] ?? 'guardar';

            // --- NUEVO: Capturamos el ID de edición si existe ---
            $editTreg = $input['edit_treg'] ?? null;

            if (!$cabecera || !$detalle || !is_array($detalle)) {
                throw new Exception("La cabecera o el detalle están incompletos.");
            }

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $cabecera['tuser'] = $_SESSION['usuario'] ?? $_SESSION['username'] ?? 'SYS';

            // --- NUEVO: Pasamos el tercer parámetro ($editTreg) al servicio ---
            $treg = $this->service->guardarGuia($cabecera, $detalle, $editTreg);

            // 2. Lógica de envío a NubeFact
            $respuesta_nubefact = null;

            if (!empty($treg)) {
                $respuesta_servicio = $this->configApiService->obtenerCredencialesNubeFact();

                if ($respuesta_servicio['success'] && !empty($respuesta_servicio['data']['ruta']) && !empty($respuesta_servicio['data']['token'])) {
                    $credenciales = $respuesta_servicio['data'];

                    // 1. Envías la petición a NubeFact
                    $respuesta_nubefact = $this->enviarNubeFact($cabecera, $detalle, $credenciales);

                    // GUARDAR LA RESPUESTA EN LA BD
                    if ($respuesta_nubefact && !isset($respuesta_nubefact['error'])) {
                        $hash_qr = $respuesta_nubefact['cadena_para_codigo_qr'] ?? 'Pendiente';

                        // Emulamos el sistema antiguo
                        $estado_legacy = (isset($respuesta_nubefact['aceptada_por_sunat']) && $respuesta_nubefact['aceptada_por_sunat'] === true) ? 'Verdadero' : 'Procesando en SUNAT';

                        // Si NubeFact arrojó un error de validación, guardamos el error para que el frontend lo lea
                        if (isset($respuesta_nubefact['errors'])) {
                            $estado_legacy = 'Rechazado';
                            $hash_qr = $respuesta_nubefact['errors'];
                        }

                        // Actualizamos la base de datos usando el treg
                        $this->service->actualizarRespuestaNubeFact($treg, $hash_qr, $estado_legacy);
                    }
                } else {
                    $respuesta_nubefact = ["error" => "No se encontraron credenciales de NubeFact en la BD."];
                }
            }

            echo json_encode([
                "success" => !empty($treg),
                "treg" => $treg,
                "nubefact" => $respuesta_nubefact,
                "accion_solicitada" => $accion,
                "message" => "Proceso completado."
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error en el servidor: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }

    /**
     * Función privada para construir el JSON y hacer la petición cURL
     */
    private function enviarNubeFact($cabecera, $detalle, $credenciales)
    {
        // Limpieza de datos (Quitar guiones de la placa como exige el manual)
        $placa = str_replace('-', '', $cabecera['placaP'] ?? '');
        $tipo_transporte = (strpos($cabecera['tipoTransporte'], '01') !== false) ? "01" : "02";

        // Estructura exigida por el manual de NubeFact
        $guia_json = [
            "operacion" => "generar_guia",
            "tipo_de_comprobante" => 7,
            "serie" => $cabecera['serie'],
            "numero" => (int)$cabecera['numeroGuia'],
            "cliente_tipo_de_documento" => 6, // 6 = RUC
            "cliente_numero_de_documento" => $cabecera['clienteRuc'],
            "cliente_denominacion" => $cabecera['clienteRazonSocial'] ?? 'CLIENTE',
            "cliente_direccion" => $cabecera['puntoLlegada'] ?? '-',
            "fecha_de_emision" => date('d-m-Y', strtotime($cabecera['fechaEmision'])),
            "observaciones" => $cabecera['observaciones'] ?? '',
            "motivo_de_traslado" => explode(' |', $cabecera['motivoTraslado'])[0] ?? "04", // Obtener solo el código
            "peso_bruto_total" => (float)$cabecera['totalPeso'],
            "peso_bruto_unidad_de_medida" => "KGM",
            "numero_de_bultos" => (int)($cabecera['numeroBultos'] ?? $cabecera['totalCantidad']),
            "tipo_de_transporte" => $tipo_transporte,
            "fecha_de_inicio_de_traslado" => date('d-m-Y', strtotime($cabecera['fechaTraslado'])),
            "punto_de_partida_ubigeo" => $cabecera['ubigeoPartida'] ?? '',
            "punto_de_partida_direccion" => $cabecera['puntoPartida'] ?? '-',
            "punto_de_llegada_ubigeo" => $cabecera['ubigeoLlegada'] ?? '',
            "punto_de_llegada_direccion" => $cabecera['puntoLlegada'] ?? '-',
            "enviar_automaticamente_al_cliente" => "false",
            "formato_de_pdf" => "A4",
            "items" => []
        ];

        $motivo = explode(' |', $cabecera['motivoTraslado'])[0] ?? "04";
        $codigoDamDs = trim($cabecera['codigoDamDs'] ?? '');
        
        if (($motivo === '08' || $motivo === '09') && !empty($codigoDamDs)) {
            // 1. REGLA CABECERA: NubeFact no usa arreglos para documentos aduaneros.
            // Exige que el código (50 o 52) vaya suelto en la raíz del JSON.
            $tipoDocRel = (strpos(strtoupper($codigoDamDs), 'DS') !== false || strpos($codigoDamDs, '-18-') !== false) ? '52' : '50';
            
            $guia_json["documento_relacionado_codigo"] = $tipoDocRel;
        }

        // La placa del vehículo siempre va, sin importar el tipo de transporte
        $guia_json["transportista_placa_numero"] = $placa;

        // Validaciones condicionales de NubeFact según el tipo de transporte
        if ($tipo_transporte === "01") {
            // TRANSPORTE PÚBLICO
            $guia_json["transportista_documento_tipo"] = "6";
            $guia_json["transportista_documento_numero"] = $cabecera['codTransportista'];
            $guia_json["transportista_denominacion"] = !empty($cabecera['nombreTransportista']) ? $cabecera['nombreTransportista'] : '-';

            if (!empty($cabecera['codConductor'])) {
                $guia_json["conductor_documento_tipo"] = "1"; // 1 = DNI
                $guia_json["conductor_documento_numero"] = $cabecera['codConductor'];
                $guia_json["conductor_nombre"] = !empty($cabecera['nombreConductor']) ? $cabecera['nombreConductor'] : '-';
                $guia_json["conductor_apellidos"] = "-";

                if (!empty($cabecera['licenciaConductor'])) {
                    $guia_json["conductor_numero_licencia"] = $cabecera['licenciaConductor'];
                }
            }
        } else {
            // TRANSPORTE PRIVADO
            $guia_json["conductor_documento_tipo"] = "1";
            $guia_json["conductor_documento_numero"] = $cabecera['codConductor'];
            $guia_json["conductor_nombre"] = !empty($cabecera['nombreConductor']) ? $cabecera['nombreConductor'] : '-';
            $guia_json["conductor_apellidos"] = "-";

            if (!empty($cabecera['licenciaConductor'])) {
                $guia_json["conductor_numero_licencia"] = $cabecera['licenciaConductor'];
            }
        }

        // Armar el detalle de productos
        foreach ($detalle as $item) {
            // 1. Traductor de Unidades (Catálogo 65 SUNAT para Aduanas vs Catálogo 03 para Nacional)
            $unidadLocal = strtoupper(trim($item['unidad'] ?? 'UND'));
            $unidadSunat = 'NIU'; // Por defecto para nacionales (Catálogo 03)
            
            if ($motivo === '08' || $motivo === '09') {
                // Reglas estrictas para Importación/Exportación (CATÁLOGO 65 DE ADUANAS)
                if ($unidadLocal === 'UND' || $unidadLocal === 'UNIDAD') {
                    $unidadSunat = 'U'; // Unidad en Catálogo 65
                } elseif ($unidadLocal === 'KGS' || $unidadLocal === 'KG') {
                    $unidadSunat = 'KG'; // Kilogramos en Catálogo 65
                } elseif ($unidadLocal === 'MTR' || $unidadLocal === 'MT') {
                    $unidadSunat = 'M'; // Metros en Catálogo 65
                } elseif ($unidadLocal === 'LTS' || $unidadLocal === 'LT') {
                    $unidadSunat = 'L'; // Litros en Catálogo 65
                } else {
                    $unidadSunat = 'U'; // Fallback seguro para Aduanas
                }
            } else {
                // Para traslados nacionales normales (CATÁLOGO 03)
                // Forzamos 'NIU' porque NubeFact lo pide por defecto para productos
                $unidadSunat = 'NIU';
            }

            $item_data = [
                "unidad_de_medida" => $unidadSunat,
                "codigo" => $item['codigo'],
                "descripcion" => $item['descripcion'],
                "cantidad" => (float)$item['cantidad']
            ];

            // 2. REGLA DETALLE: 'codigo_dam' debe ir dentro de cada ítem
            if (($motivo === '08' || $motivo === '09') && !empty($codigoDamDs)) {
                $codigoAduanaItem = $codigoDamDs;
                
                // Si el usuario no digitó el slash de la serie aduanera (ej: "1/"), se lo agregamos automáticamente
                if (strpos($codigoAduanaItem, '/') === false) {
                    $codigoAduanaItem = '1/' . $codigoAduanaItem;
                }
                
                $item_data["codigo_dam"] = $codigoAduanaItem;
            }

            $guia_json["items"][] = $item_data;
        }

        $json_payload = json_encode($guia_json, JSON_UNESCAPED_UNICODE);

        // Envío HTTP cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $credenciales['ruta']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $credenciales['token'],
            'Content-Type: application/json'
        ]);

        $respuesta = curl_exec($ch);
        $error_curl = curl_error($ch);
        curl_close($ch);

        if ($error_curl) {
            return ["error" => "Fallo de conexión: " . $error_curl];
        }

        return json_decode($respuesta, true);
    }

    public function consultarGuia()
    {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $serie = $_GET['serie'] ?? '';
            $numero = (int)($_GET['numero'] ?? 0);
            $treg = $_GET['treg'] ?? '';

            if (empty($serie) || $numero === 0) {
                throw new Exception("Serie y número de guía son requeridos para la consulta.");
            }

            // 1. Obtener credenciales de la BD
            $respuesta_servicio = $this->configApiService->obtenerCredencialesNubeFact();
            if (!$respuesta_servicio['success'] || empty($respuesta_servicio['data']['ruta'])) {
                throw new Exception("No se encontraron credenciales de NubeFact.");
            }
            $credenciales = $respuesta_servicio['data'];

            // 2. Estructura JSON para consultar (Según página 10 del PDF)
            $payload_consulta = [
                "operacion" => "consultar_guia",
                "tipo_de_comprobante" => 7,
                "serie" => $serie,
                "numero" => $numero
            ];

            // 3. Petición cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $credenciales['ruta']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload_consulta));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $credenciales['token'],
                'Content-Type: application/json'
            ]);

            $respuesta = curl_exec($ch);
            curl_close($ch);

            $data_respuesta = json_decode($respuesta, true);

            if ($data_respuesta) {
                // 1. CASO DE ÉXITO: Aceptada por SUNAT
                if (isset($data_respuesta['aceptada_por_sunat']) && $data_respuesta['aceptada_por_sunat'] === true) {
                    $hash_qr = $data_respuesta['cadena_para_codigo_qr'] ?? null;

                    if ($hash_qr) {
                        // Guardamos "Verdadero" y el enlace/hash oficial
                        $this->service->actualizarRespuestaNubeFact($treg, $hash_qr, 'Verdadero');
                    }
                }
                // 2. CASO DE ERROR DE VALIDACIÓN: NubeFact detecta un problema (ej. RUC inválido)
                elseif (!empty($data_respuesta['errors'])) {
                    $error_detalle = $data_respuesta['errors'];
                    // Guardamos "Rechazado" y el texto del error en el campo QR
                    $this->service->actualizarRespuestaNubeFact($treg, $error_detalle, 'Rechazado');
                }
                // 3. CASO DE RECHAZO SUNAT: NubeFact lo procesó, pero SUNAT lo rebotó
                elseif (isset($data_respuesta['aceptada_por_sunat']) && $data_respuesta['aceptada_por_sunat'] === false && !empty($data_respuesta['sunat_description'])) {
                    $desc = $data_respuesta['sunat_description'];

                    // Verificamos si la descripción de SUNAT contiene palabras clave de fallo
                    if (stripos($desc, 'rechazad') !== false || stripos($desc, 'excepcion') !== false || stripos($desc, 'error') !== false) {
                        $this->service->actualizarRespuestaNubeFact($treg, $desc, 'Rechazado');
                    }
                }
            }

            echo $respuesta; // Devolvemos la respuesta directa de NubeFact al Frontend

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al consultar: " . $e->getMessage()
            ]);
        }
        exit;
    }
}
