<?php

class PermisoRepository {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Obtener todos los permisos de un rol
     */
    public function obtenerPorRol($idRol) {
        $sql = "SELECT p.id_permiso, p.id_rol, p.cod_mod, 
                       COALESCE(p.p_leer, 1) as p_leer,
                       p.p_insertar, p.p_editar, p.p_eliminar, p.p_anular,
                       m.nom_mod, m.label_short, m.icono
                FROM seg_permisos_rol p
                INNER JOIN dashboard_modulos m ON p.cod_mod = m.cod_mod
                WHERE p.id_rol = ?
                ORDER BY m.orden";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener permisos de un módulo específico para un rol
     */
    public function obtenerPermiso($idRol, $codMod) {
        $sql = "SELECT COALESCE(p_leer, 1) as p_leer, 
                       p_insertar, p_editar, p_eliminar, p_anular
                FROM seg_permisos_rol
                WHERE id_rol = ? AND cod_mod = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        $stmt->bindParam(2, $codMod, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Asignar o actualizar permiso
     */
    public function asignarPermiso($idRol, $codMod, $insertar, $editar, $eliminar, $anular, $leer = 1) {
        // Verificar si ya existe el permiso
        $sqlCheck = "SELECT id_permiso FROM seg_permisos_rol 
                     WHERE id_rol = ? AND cod_mod = ?";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->bindParam(1, $idRol, PDO::PARAM_INT);
        $stmtCheck->bindParam(2, $codMod, PDO::PARAM_STR);
        $stmtCheck->execute();
        
        if ($stmtCheck->fetch()) {
            // Actualizar
            $sql = "UPDATE seg_permisos_rol 
                    SET p_leer = ?, p_insertar = ?, p_editar = ?, p_eliminar = ?, p_anular = ?
                    WHERE id_rol = ? AND cod_mod = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $leer, PDO::PARAM_INT);
            $stmt->bindParam(2, $insertar, PDO::PARAM_INT);
            $stmt->bindParam(3, $editar, PDO::PARAM_INT);
            $stmt->bindParam(4, $eliminar, PDO::PARAM_INT);
            $stmt->bindParam(5, $anular, PDO::PARAM_INT);
            $stmt->bindParam(6, $idRol, PDO::PARAM_INT);
            $stmt->bindParam(7, $codMod, PDO::PARAM_STR);
        } else {
            // Insertar con p_leer por defecto en 1
            $sql = "INSERT INTO seg_permisos_rol (id_rol, cod_mod, p_leer, p_insertar, p_editar, p_eliminar, p_anular) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
            $stmt->bindParam(2, $codMod, PDO::PARAM_STR);
            $stmt->bindParam(3, $leer, PDO::PARAM_INT);
            $stmt->bindParam(4, $insertar, PDO::PARAM_INT);
            $stmt->bindParam(5, $editar, PDO::PARAM_INT);
            $stmt->bindParam(6, $eliminar, PDO::PARAM_INT);
            $stmt->bindParam(7, $anular, PDO::PARAM_INT);
        }
        
        return $stmt->execute();
    }

    /**
     * Eliminar todos los permisos de un rol
     */
    public function eliminarPorRol($idRol) {
        $sql = "DELETE FROM seg_permisos_rol WHERE id_rol = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Eliminar permiso específico
     */
    public function eliminarPermiso($idRol, $codMod) {
        $sql = "DELETE FROM seg_permisos_rol WHERE id_rol = ? AND cod_mod = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        $stmt->bindParam(2, $codMod, PDO::PARAM_STR);
        return $stmt->execute();
    }

    /**
     * Obtener módulos disponibles para asignar a un rol
     */
    public function obtenerModulosDisponibles($idRol) {
        $sql = "SELECT m.cod_mod, m.nom_mod, m.label_short, m.icono, m.tipo,
                       COALESCE(p.p_leer, 0) as tiene_leer,
                       COALESCE(p.p_insertar, 0) as tiene_insertar,
                       COALESCE(p.p_editar, 0) as tiene_editar,
                       COALESCE(p.p_eliminar, 0) as tiene_eliminar,
                       COALESCE(p.p_anular, 0) as tiene_anular
                FROM dashboard_modulos m
                LEFT JOIN seg_permisos_rol p ON m.cod_mod = p.cod_mod AND p.id_rol = ?
                ORDER BY m.orden";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
