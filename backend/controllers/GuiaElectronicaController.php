<?php
require_once __DIR__ . '/../services/GuiaElectronicaService.php';

class GuiaElectronicaController
{
    private $service;

    public function __construct($db)
    {
        $this->service = new GuiaElectronicaService($db);
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

            if (!$cabecera || !$detalle || !is_array($detalle)) {
                throw new Exception("La cabecera o el detalle están incompletos.");
            }

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $cabecera['tuser'] = $_SESSION['usuario'] ?? $_SESSION['username'] ?? 'SYS';

            $treg = $this->service->guardarGuia($cabecera, $detalle);

            echo json_encode([
                "success" => !empty($treg),
                "treg" => $treg,
                "message" => "Guía guardada correctamente."
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Error al guardar la guía: " . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        exit;
    }
}
