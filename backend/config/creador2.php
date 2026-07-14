<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/conexion_grs/configuracion.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST_JOYA . ";dbname=" . DB_NAME_JOYA, DB_USER_JOYA, DB_PASSWORD_JOYA);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");

    // =============================================
    // MATRIZ DE CONSULTAS PARA VALIDACIÓN
    // =============================================
    $consultas = [
        "1. Validar la Programación y el Pedido" => "
            SELECT * FROM amd_programas_pic
        ",
        "2. Validar los Envíos Físicos (Tabla Docu)" => "
            SELECT 
                a.tfecfac AS Fecha_Venta, 
                c.codigo AS CodCliente, 
                c.nombre AS Cliente, 
                a.placa AS Placa_Camion, 
                CONCAT(a.tdate, ' ', a.ttime) AS Hora_Registro_Envio
            FROM docu AS a
            INNER JOIN ccte AS c ON a.tprocli = c.codigo
            WHERE a.tfecfac = '2026-07-05' 
            AND a.tline = '601' AND a.tlib = 'RV' AND a.tcodtra = 'S700'
            AND c.nombre LIKE '%GUEVARA YANQUI YASSIR FREDDY%'
            ORDER BY Hora_Registro_Envio ASC
        ",
        "3. La Prueba del Pago Faltante (Tabla Caja - spca)" => "
            SELECT 
                a.tfectra AS Fecha_Caja,
                c.codigo AS CodCliente, 
                c.nombre AS Cliente, 
                a.tipo AS Tipo_Documento, 
                a.tcodtra AS Codigo_Transaccion, 
                CONCAT(a.tdate, ' ', a.ttime) AS Hora_Real_Caja,
                a.timporte AS Importe_Pagado
            FROM spca AS a
            INNER JOIN ccte AS c ON a.tprocli = c.codigo
            WHERE a.tfectra = '2026-07-05' 
            AND c.nombre LIKE '%GUEVARA YANQUI YASSIR FREDDY%'
        "
    ];

    echo "<h2 style='font-family:Arial; color: #333;'>Análisis Cliente: GUEVARA YANQUI YASSIR FREDDY (05/07/2026)</h2>";

    // Recorremos y ejecutamos cada consulta
    foreach ($consultas as $titulo => $sql) {
        echo "<h3 style='font-family:Arial; color: #2c3e50; margin-top: 30px; border-bottom: 2px solid #ccc; padding-bottom: 5px;'>$titulo</h3>";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($resultados)) {
            echo "<p style='font-family:Arial; color:red; font-weight:bold; font-size: 16px;'>Sin resultados (Cero registros en base de datos para este cruce).</p>";
        } else {
            $columnas = array_keys($resultados[0]);
            echo "<table border='1' cellpadding='8' style='border-collapse:collapse;font-family:Arial;font-size:14px; width:100%;'>";
            echo "<tr style='background:#f4f4f4; color: #333; text-align: center;'>";
            foreach ($columnas as $col) {
                echo "<th>$col</th>";
            }
            echo "</tr>";
            foreach ($resultados as $fila) {
                echo "<tr>";
                foreach ($columnas as $col) {
                    // Verificamos si es null o vacío
                    $valor = (isset($fila[$col]) && trim($fila[$col]) !== '') ? htmlspecialchars($fila[$col]) : '<span style="color:#aaa;">(NULO)</span>';
                    echo "<td style='text-align:center;'>" . $valor . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
            echo "<p style='font-family:Arial; color:#666;'>Total filas: " . count($resultados) . "</p>";
        }
    }

} catch (PDOException $e) {
    echo "<p style='color:red;font-family:Arial;'>Error: " . $e->getMessage() . "</p>";
}
?>