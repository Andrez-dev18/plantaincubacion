<?php
/**
 * Servicio para el módulo de Guías de Remisión Electrónica
 */
require_once __DIR__ . '/../repositories/GuiaElectronicaRepository.php';
require_once __DIR__ . '/LogsSistemaService.php';

class GuiaElectronicaService
{
    private $repo;
    private $db;
    private $logsService;

    public function __construct($db)
    {
        $this->db = $db;
        $this->repo = new GuiaElectronicaRepository($db);
        $this->logsService = new LogsSistemaService($db);
    }

    public function listarAlmacenes(): array
    {
        return $this->repo->obtenerAlmacenes();
    }

    public function listarTiposTransporte(): array
    {
        return $this->repo->obtenerTiposTransporte();
    }

    public function listarTransportistas(?string $search = null): array
    {
        return $this->repo->obtenerTransportistas($search);
    }

    public function listarConductores(?string $search = null, ?string $rucTransportista = null, bool $mostrarTodos = false): array{
        return $this->repo->obtenerConductores($search, $rucTransportista, $mostrarTodos);
    }

    public function listarCamiones(?string $search = null, ?string $rucTransportista = null): array
    {
        return $this->repo->obtenerCamiones($search, $rucTransportista);
    }

    public function listarClientes(?string $search = null): array
    {
        return $this->repo->obtenerClientes($search);
    }

    public function listarArticulos(?string $search = null, ?string $almacen = null, ?int $anio = null): array
    {
        return $this->repo->obtenerArticulos($search, $almacen, $anio);
    }

    public function listarLotes(string $almacen, string $codigoArticulo, int $anio): array
    {
        return $this->repo->obtenerLotesPorArticulo($almacen, $codigoArticulo, $anio);
    }

    public function listarSeries(string $almacen, string $cliente): array
    {
        return $this->repo->obtenerSeriesConCorrelativo($almacen, $cliente);
    }

    public function listarMotivosTraslado(): array
    {
        return $this->repo->obtenerMotivosTraslado();
    }

    public function obtenerDireccionCliente(string $codigoCliente): ?array
    {
        return $this->repo->obtenerDireccionCliente($codigoCliente);
    }

    public function listarCencos(?string $search = null): array
    {
        return $this->repo->obtenerCencos($search);
    }

    public function listarGalponesPorCencos(string $cencos): array
    {
        return $this->repo->obtenerGalponesPorCencos($cencos);
    }

    public function guardarGuia(array $cabecera, array $detalle, ?string $editTreg = null): string
    {
        $treg = $this->repo->guardarGuia($cabecera, $detalle, $editTreg);

        $accion = !empty($editTreg) ? 'UPDATE' : 'INSERT';
        $serieNum = ($cabecera['serie'] ?? '') . '-' . ($cabecera['numeroGuia'] ?? '');
        $desc = !empty($editTreg) 
            ? "Guía electrónica {$serieNum} (treg: {$treg}) actualizada." 
            : "Guía electrónica {$serieNum} (treg: {$treg}) registrada.";

        $this->logsService->logAction($accion, 'guia', $treg, null, ['cabecera' => $cabecera, 'detalle' => $detalle], $desc);

        return $treg;
    }

    public function actualizarRespuestaNubeFact(string $treg, string $hash, string $url)
    {
        $this->repo->actualizarRespuestaNubeFact($treg, $hash, $url);

        $this->logsService->logAction(
            'UPDATE',
            'guia',
            $treg,
            null,
            ['rsp_nubefact' => $url, 'qr_nubefact' => $hash],
            "Respuesta de NubeFact actualizada para la guía (treg: {$treg}, estado: {$url})."
        );
    }

    public function enviarNubeFact(array $cabecera, array $detalle, array $credenciales, ?string $treg = null): array
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
            $tipoDocRel = (strpos(strtoupper($codigoDamDs), 'DS') !== false || strpos($codigoDamDs, '-18-') !== false) ? '52' : '50';
            $guia_json["documento_relacionado_codigo"] = $tipoDocRel;
        }

        $guia_json["transportista_placa_numero"] = $placa;

        if ($tipo_transporte === "01") {
            $guia_json["transportista_documento_tipo"] = "6";
            $guia_json["transportista_documento_numero"] = $cabecera['codTransportista'];
            $guia_json["transportista_denominacion"] = !empty($cabecera['nombreTransportista']) ? $cabecera['nombreTransportista'] : '-';

            if (!empty($cabecera['codConductor'])) {
                $guia_json["conductor_documento_tipo"] = "1";
                $guia_json["conductor_documento_numero"] = $cabecera['codConductor'];
                $guia_json["conductor_nombre"] = !empty($cabecera['nombreConductor']) ? $cabecera['nombreConductor'] : '-';
                $guia_json["conductor_apellidos"] = "-";

                if (!empty($cabecera['licenciaConductor'])) {
                    $guia_json["conductor_numero_licencia"] = $cabecera['licenciaConductor'];
                }
            }
        } else {
            $guia_json["conductor_documento_tipo"] = "1";
            $guia_json["conductor_documento_numero"] = $cabecera['codConductor'];
            $guia_json["conductor_nombre"] = !empty($cabecera['nombreConductor']) ? $cabecera['nombreConductor'] : '-';
            $guia_json["conductor_apellidos"] = "-";

            if (!empty($cabecera['licenciaConductor'])) {
                $guia_json["conductor_numero_licencia"] = $cabecera['licenciaConductor'];
            }
        }

        foreach ($detalle as $item) {
            $unidadLocal = strtoupper(trim($item['unidad'] ?? 'UND'));
            $unidadSunat = 'NIU';
            
            if ($motivo === '08' || $motivo === '09') {
                if ($unidadLocal === 'UND' || $unidadLocal === 'UNIDAD') {
                    $unidadSunat = 'U';
                } elseif ($unidadLocal === 'KGS' || $unidadLocal === 'KG') {
                    $unidadSunat = 'KG';
                } elseif ($unidadLocal === 'MTR' || $unidadLocal === 'MT') {
                    $unidadSunat = 'M';
                } elseif ($unidadLocal === 'LTS' || $unidadLocal === 'LT') {
                    $unidadSunat = 'L';
                } else {
                    $unidadSunat = 'U';
                }
            } else {
                $unidadSunat = 'NIU';
            }

            $item_data = [
                "unidad_de_medida" => $unidadSunat,
                "codigo" => $item['codigo'],
                "descripcion" => $item['descripcion'],
                "cantidad" => (float)$item['cantidad']
            ];

            if (($motivo === '08' || $motivo === '09') && !empty($codigoDamDs)) {
                $codigoAduanaItem = $codigoDamDs;
                if (strpos($codigoAduanaItem, '/') === false) {
                    $codigoAduanaItem = '1/' . $codigoAduanaItem;
                }
                $item_data["codigo_dam"] = $codigoAduanaItem;
            }

            $guia_json["items"][] = $item_data;
        }

        $json_payload = json_encode($guia_json, JSON_UNESCAPED_UNICODE);

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
            $resultado = ["error" => "Fallo de conexión: " . $error_curl];
        } else {
            $resultado = json_decode($respuesta, true) ?? [];
        }

        $serieNumero = ($cabecera['serie'] ?? '') . '-' . ($cabecera['numeroGuia'] ?? '');
        $regId = $treg ?: $serieNumero;

        $this->logsService->logAction(
            'ENVIO_NUBEFACT',
            'guia',
            $regId,
            null,
            $resultado,
            "Envío de Guía Electrónica {$serieNumero} (treg: {$regId}) a NubeFact realizado."
        );

        return $resultado;
    }

    public function consultarGuia(string $serie, int $numero, string $treg, array $credenciales): array
    {
        $payload_consulta = [
            "operacion" => "consultar_guia",
            "tipo_de_comprobante" => 7,
            "serie" => $serie,
            "numero" => $numero
        ];

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
        $error_curl = curl_error($ch);
        curl_close($ch);

        if ($error_curl) {
            $data_respuesta = ["error" => "Fallo de conexión: " . $error_curl];
        } else {
            $data_respuesta = json_decode($respuesta, true) ?? [];
        }

        if ($data_respuesta) {
            if (isset($data_respuesta['aceptada_por_sunat']) && $data_respuesta['aceptada_por_sunat'] === true) {
                $hash_qr = $data_respuesta['cadena_para_codigo_qr'] ?? null;
                if ($hash_qr) {
                    $this->actualizarRespuestaNubeFact($treg, $hash_qr, 'Verdadero');
                }
            } elseif (!empty($data_respuesta['errors'])) {
                $error_detalle = $data_respuesta['errors'];
                $this->actualizarRespuestaNubeFact($treg, $error_detalle, 'Rechazado');
            } elseif (isset($data_respuesta['aceptada_por_sunat']) && $data_respuesta['aceptada_por_sunat'] === false && !empty($data_respuesta['sunat_description'])) {
                $desc = $data_respuesta['sunat_description'];
                if (stripos($desc, 'rechazad') !== false || stripos($desc, 'excepcion') !== false || stripos($desc, 'error') !== false) {
                    $this->actualizarRespuestaNubeFact($treg, $desc, 'Rechazado');
                }
            }
        }

        $serieNumero = "{$serie}-{$numero}";
        $regId = !empty($treg) ? $treg : $serieNumero;

        $this->logsService->logAction(
            'CONSULTA_NUBEFACT',
            'guia',
            $regId,
            null,
            $data_respuesta,
            "Consulta de estado NubeFact para la Guía Electrónica {$serieNumero} (treg: {$treg})."
        );

        return $data_respuesta;
    }
}

