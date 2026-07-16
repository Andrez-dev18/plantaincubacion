<?php
/**
 * Garantiza una única instancia de la clase Database
 * y una única conexión PDO a la base de datos en toda la aplicación.
 *
 * PRODUCCIÓN : copia la carpeta del proyecto junto a conexion_grs/ en el servidor.
 *              Este archivo detecta automáticamente conexion_grs/configuracion.php
 *              y usa las credenciales del servidor JOYA (DB_HOST_JOYA, etc.).
 *
 * DESARROLLO : si conexion_grs/ no existe, usa las credenciales definidas abajo.
 *              Cambia entre $devHost = 'remoto' | 'local' según necesites.
 */

// -----------------------------------------------------------------
// Cargar configuración de producción si la carpeta conexion_grs existe
// Ruta esperada en servidor: <htdocs>/conexion_grs/configuracion.php
// -----------------------------------------------------------------
$_grsConfig = __DIR__ . '/../../../conexion_grs/configuracion.php';
if (file_exists($_grsConfig)) {
    require_once $_grsConfig;
}
unset($_grsConfig);

class Database {
    private static $instance = null;
    private $conn = null;

    // --- Credenciales DESARROLLO (remoto) ---
    private $host     = "200.48.160.2";
    private $db_name  = "ciajoya";
    private $username = "rinconada";
    private $password = "MrCls078e5ou";

    // --- Credenciales DESARROLLO (local) --- descomenta y comenta el bloque de arriba si usas localhost
     /*private $host     = "localhost";
     private $db_name  = "grs_picamana";
     private $username = "root";
     private $password = "";*/

    /**
     * Constructor privado.
     * Si las constantes de producción (conexion_grs) están disponibles,
     * sobreescribe las credenciales con las del servidor JOYA.
     */
    private function __construct() {
        if (defined('DB_HOST_JOYA')) {
            $this->host     = constant('DB_HOST_JOYA');
            $this->username = constant('DB_USER_JOYA');
            $this->password = constant('DB_PASSWORD_JOYA');
            $this->db_name  = constant('DB_NAME_JOYA');
        }
    }

    /**
     * Prevenir clonación del objeto
     */
    private function __clone() {
        // Evita clonar la instancia
    }

    /**
     * Prevenir deserialización del objeto
     */
    public function __wakeup() {
        throw new Exception("No se puede deserializar un singleton");
    }

    /**
     * Obtener la instancia única de Database (Singleton)
     * 
     * @return Database Instancia única de la clase
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Obtener la conexión PDO a la base de datos
     * La conexión se crea solo una vez (lazy loading)
     * 
     * @return PDO Objeto de conexión PDO
     */
    public function getConnection() {
        if ($this->conn === null) {
            try {
                $this->conn = new PDO(
                    "mysql:host={$this->host};dbname={$this->db_name};charset=utf8",
                    $this->username,
                    $this->password
                );
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Deshabilitar modo estricto para compatibilidad con fechas '0000-00-00'
                $this->conn->exec("SET SESSION sql_mode = ''");
            } catch (PDOException $e) {
                throw new Exception("Error de conexión: " . $e->getMessage());
            }
        }
        return $this->conn;
    }
}
