<?php

class NavegacionRepository {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Obtener todos los módulos visibles para un rol
     * (La presencia del registro implica que puede verlo)
     */
    public function obtenerModulosPorRol($idRol) {
        $sql = "SELECT n.id_rol, n.cod_mod,
                       m.nom_mod, m.label_short, m.icono, m.url,
                       m.tipo, m.parent_cod, m.nivel0, m.nivel1, m.nivel2, m.nivel3,
                       m.orden, m.programa
                FROM seg_navegacion_rol n
                INNER JOIN dashboard_modulos m ON n.cod_mod = m.cod_mod
                WHERE n.id_rol = ?
                ORDER BY m.orden";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener módulos visibles para un usuario (considerando todos sus roles)
     * Incluye permisos CRUD del rol
     */
    public function obtenerModulosPorUsuario($idUsuario) {
        $sql = "SELECT DISTINCT n.cod_mod,
                       m.nom_mod, m.label_short, m.icono, m.url,
                       m.tipo, m.parent_cod, m.nivel0, m.nivel1, m.nivel2, m.nivel3,
                       m.orden, m.programa,
                       1 as p_leer,
                       r.p_insertar, r.p_editar, r.p_eliminar, r.p_anular
                FROM seg_usuario_roles ur
                INNER JOIN seg_navegacion_rol n ON ur.id_rol = n.id_rol
                INNER JOIN dashboard_modulos m ON n.cod_mod = m.cod_mod
                INNER JOIN seg_roles r ON ur.id_rol = r.id_rol
                WHERE ur.id_usuario = ?
                AND r.estado = 1
                ORDER BY m.orden";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener módulos agrupados jerárquicamente para un usuario
     */
    public function obtenerMenuJerarquico($idUsuario, $programa = null) {
        $sql = "SELECT DISTINCT 
                       dm.cod_mod,
                       dm.nom_mod, 
                       dm.label_short, 
                       dm.icono, 
                       dm.url,
                       dm.tipo, 
                       dm.parent_cod, 
                       dm.nivel0, 
                       dm.nivel1, 
                       dm.nivel2, 
                       dm.nivel3,
                       dm.orden, 
                       dm.id_programa as programa, 
                       dm.tipo_param,
                       dm.nom_mod as titulo
                FROM adm_usuario_rol ur
                INNER JOIN adm_rol_progr_modulo rpm ON ur.cod_rol = (SELECT cod_rol FROM adm_rol WHERE id = rpm.id_rol)
                INNER JOIN amd_dashboard_modulos dm ON rpm.cod_mod = dm.cod_mod AND rpm.id_programa = dm.id_programa
                WHERE ur.codigo = ?";
        
        if ($programa) {
            $sql .= " AND dm.id_programa = ?";
        }
        
        $sql .= " ORDER BY dm.orden";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idUsuario, PDO::PARAM_STR);
        if ($programa) {
            $stmt->bindParam(2, $programa, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si un rol tiene acceso a un módulo específico
     */
    public function tieneAccesoModulo($idRol, $codMod) {
        $sql = "SELECT COUNT(*) as total 
                FROM seg_navegacion_rol 
                WHERE id_rol = ? AND cod_mod = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        $stmt->bindParam(2, $codMod, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    }

    /**
     * Verificar si un usuario tiene acceso a un módulo (por cualquiera de sus roles)
     */
    public function usuarioTieneAcceso($idUsuario, $codMod) {
        $sql = "SELECT COUNT(*) as total 
                FROM seg_usuario_roles ur
                INNER JOIN seg_navegacion_rol n ON ur.id_rol = n.id_rol
                WHERE ur.id_usuario = ? AND n.cod_mod = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idUsuario, PDO::PARAM_INT);
        $stmt->bindParam(2, $codMod, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    }

    /**
     * Asignar acceso a un módulo para un rol
     */
    public function asignarModulo($idRol, $codMod) {
        // Verificar si ya existe
        if ($this->tieneAccesoModulo($idRol, $codMod)) {
            return false;
        }

        $sql = "INSERT INTO seg_navegacion_rol (id_rol, cod_mod) 
                VALUES (?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        $stmt->bindParam(2, $codMod, PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    /**
     * Remover acceso a un módulo para un rol
     */
    public function removerModulo($idRol, $codMod) {
        $sql = "DELETE FROM seg_navegacion_rol 
                WHERE id_rol = ? AND cod_mod = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        $stmt->bindParam(2, $codMod, PDO::PARAM_STR);
        
        return $stmt->execute();
    }

    /**
     * Remover todos los módulos de un rol
     */
    public function removerTodosLosModulos($idRol) {
        $sql = "DELETE FROM seg_navegacion_rol WHERE id_rol = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Asignar múltiples módulos a un rol (reemplaza asignaciones existentes)
     */
    public function asignarModulosLote($idRol, $modulos) {
        try {
            $this->conn->beginTransaction();
            
            // Remover módulos existentes
            $this->removerTodosLosModulos($idRol);
            
            // Asignar nuevos módulos
            $sql = "INSERT INTO seg_navegacion_rol (id_rol, cod_mod) VALUES (?, ?)";
            $stmt = $this->conn->prepare($sql);
            
            foreach ($modulos as $codMod) {
                $stmt->bindParam(1, $idRol, PDO::PARAM_INT);
                $stmt->bindParam(2, $codMod, PDO::PARAM_STR);
                $stmt->execute();
            }
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            return false;
        }
    }

    /**
     * Obtener módulos de un programa específico disponibles para asignar
     */
    public function obtenerModulosDisponiblesPorPrograma($programa) {
        $sql = "SELECT cod_mod, nom_mod, label_short, icono, tipo, 
                       parent_cod, nivel0, nivel1, nivel2, nivel3, orden
                FROM dashboard_modulos
                WHERE programa = ?
                ORDER BY orden";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $programa, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar cuántos roles tienen acceso a un módulo
     */
    public function contarRolesPorModulo($codMod) {
        $sql = "SELECT COUNT(*) as total 
                FROM seg_navegacion_rol 
                WHERE cod_mod = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(1, $codMod, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'];
    }
}
