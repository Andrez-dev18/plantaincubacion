<?php
/**
 * GalponService
 * 
 * Servicio con lógica de negocio para gestión de galpones
 * Implementa CRUD inteligente con operación UPSERT para detalles
 */

require_once __DIR__ . '/../repositories/GalponRepository.php';

class GalponService {
    
    private $repo;

    public function __construct($galponRepository) {
        $this->repo = $galponRepository;
    }

    /**
     * Listar galpones con filtros opcionales
     * 
     * @param array $filtros ['id_granja', 'fecha_desde', 'fecha_hasta']
     * @return array
     */
    public function listar($filtros = []) {
        try {
            $idGranja = $filtros['id_granja'] ?? null;
            $fechaDesde = $filtros['fecha_desde'] ?? null;
            $fechaHasta = $filtros['fecha_hasta'] ?? null;
            
            return $this->repo->findAll($idGranja, $fechaDesde, $fechaHasta);
        } catch (Exception $e) {
            error_log("Error en GalponService::listar: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener un galpón por ID
     * 
     * @param int $id
     * @param int $idGranja
     * @return array
     */
    public function obtenerPorId($id, $idGranja) {
        $galpon = $this->repo->findById($id, $idGranja);
        
        if (!$galpon) {
            return [
                'success' => false,
                'message' => 'Galpón no encontrado'
            ];
        }
        
        return [
            'success' => true,
            'data' => $galpon
        ];
    }

    /**
     * Crear un nuevo galpón
     * ADAPTADO: No crea registros en regcencosgalpones (solo lectura)
     * Solo verifica que existe y guarda características en pi_dim_detalles
     * 
     * @param array $data Datos del galpón y sus características
     * @return array
     */
    public function crear($data) {
        try {
            // Normalizar nombres de campos (aceptar ambos formatos)
            $granja = $data['granja'] ?? $data['id_granja'] ?? null;
            $galpon = $data['galpon'] ?? $data['numero_galpon'] ?? null;
            $nombre = $data['nombre'] ?? $data['nombre_galpon'] ?? "GALPÓN #{$galpon}";
            
            // Validar datos requeridos
            if (empty($granja) || $galpon === null) {
                return [
                    'success' => false,
                    'message' => 'Faltan datos requeridos: granja y galpon'
                ];
            }
            
            // Verificar que el galpón existe en regcencosgalpones (no se puede crear)
            try {
                $idGalponRetornado = $this->repo->create([
                    'granja' => $granja,
                    'galpon' => $galpon,
                    'nombre' => $nombre,
                    'usuario_crea' => $data['usuario_crea'] ?? 'sistema'
                ]);
                
                // create() retorna el tcodint si existe, o lanza excepción si no existe
                $idGalpon = $idGalponRetornado;
                
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
            
            // Insertar características si vienen
            if (isset($data['caracteristicas']) && is_array($data['caracteristicas'])) {
                foreach ($data['caracteristicas'] as $caracteristica) {
                    // El frontend envía: [{id_caracteristica: 1, valor: 'abc'}, ...]
                    $idCarac = $caracteristica['id_caracteristica'] ?? null;
                    $valor = $caracteristica['valor'] ?? null;
                    
                    if ($idCarac && $valor !== null && $valor !== '') {
                        $this->repo->insertDetalle([
                            'id_granja' => $granja,
                            'id_galpon' => $idGalpon,
                            'id_caracteristica' => $idCarac,
                            'dato' => $valor,
                            'usuario_crea' => $data['usuario_crea'] ?? 'sistema'
                        ]);
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => 'Características del galpón guardadas exitosamente',
                'data' => ['id' => "{$granja}-{$idGalpon}"]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al crear galpón: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Actualizar un galpón (CRUD INTELIGENTE)
     * 
     * Para cada característica:
     * - Si existe en pi_dim_detalles -> UPDATE
     * - Si NO existe -> INSERT
     * Respeta el índice UNIQUE (id_granja, id_galpon, id_caracteristica)
     * 
     * @param string $id ID compuesto del galpón "granja-galpon" (ej: "623-1")
     * @param string $idGranja ID de la granja (tcencos)
     * @param array $data Datos a actualizar
     * @return array
     */
    public function actualizar($id, $idGranja, $data) {
        try {
            // Verificar que el galpón existe
            $galponExistente = $this->repo->findById($id, $idGranja);
            if (!$galponExistente) {
                return [
                    'success' => false,
                    'message' => 'Galpón no encontrado'
                ];
            }
            
            // Extraer el número de galpón del ID compuesto
            $parts = explode('-', $id);
            $numeroGalpon = count($parts) === 2 ? $parts[1] : $id;
            
            // Actualizar datos básicos del maestro si vienen (no hace nada en regcencosgalpones)
            if (isset($data['nombre_galpon'])) {
                $this->repo->updateMaestro($id, $idGranja, [
                    'nombre' => $data['nombre_galpon'],
                    'usuario_modifica' => $data['usuario_modifica'] ?? 'sistema'
                ]);
            }
            
            // LÓGICA INTELIGENTE: UPSERT para características
            if (isset($data['caracteristicas']) && is_array($data['caracteristicas'])) {
                foreach ($data['caracteristicas'] as $idCaracteristica => $valor) {
                    // Usar el número de galpón (tcodint) para pi_dim_detalles
                    $existe = $this->repo->existeDetalle($idGranja, $numeroGalpon, $idCaracteristica);
                    
                    $detalleData = [
                        'id_granja' => $idGranja,
                        'id_galpon' => $numeroGalpon,
                        'id_caracteristica' => $idCaracteristica,
                        'dato' => $valor,
                        'usuario_crea' => $data['usuario_modifica'] ?? 'sistema',
                        'usuario_modifica' => $data['usuario_modifica'] ?? 'sistema'
                    ];
                    
                    if ($existe) {
                        // UPDATE: El atributo ya existe
                        $this->repo->updateDetalle($detalleData);
                    } else {
                        // INSERT: Es un nuevo atributo
                        $this->repo->insertDetalle($detalleData);
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => 'Galpón actualizado exitosamente'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al actualizar galpón: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Eliminar un galpón
     * 
     * @param int $id
     * @param int $idGranja
     * @return array
     */
    public function eliminar($id, $idGranja) {
        try {
            $resultado = $this->repo->delete($id, $idGranja);
            
            if ($resultado) {
                return [
                    'success' => true,
                    'message' => 'Galpón eliminado exitosamente'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'No se pudo eliminar el galpón'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al eliminar galpón: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener catálogo de características disponibles
     * 
     * @return array
     */
    public function obtenerCaracteristicas() {
        return [
            'success' => true,
            'data' => $this->repo->getCaracteristicas()
        ];
    }

    /**
     * Obtener lista de granjas disponibles desde tabla legacy regcencosgalpones
     * 
     * @return array
     */
    public function obtenerGranjas() {
        try {
            return $this->repo->getGranjas();
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener granjas: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener galpones de una granja específica
     * 
     * @param string $tcencos ID de la granja
     * @return array
     */
    public function obtenerGalponesPorGranja($tcencos) {
        try {
            return $this->repo->getGalponesByGranja($tcencos);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener galpones: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Guardar características de un galpón existente (UPSERT)
     * 
     * @param array $data Datos con granja, galpon y características
     * @return array
     */
    public function guardarCaracteristicas($data) {
        try {
            $granja = $data['granja'] ?? null;
            $numeroGalpon = $data['galpon'] ?? null;
            
            // Validar datos requeridos
            if (empty($granja) || $numeroGalpon === null) {
                return [
                    'success' => false,
                    'message' => 'Faltan datos requeridos: granja y galpon'
                ];
            }
            
            // Buscar el galpón en regcencosgalpones por granja + numero
            $galponMaestro = $this->repo->findByGranjaGalpon($granja, $numeroGalpon);
            
            // Si no existe en regcencosgalpones, error (no podemos crear galpones en tabla legacy)
            if (!$galponMaestro) {
                return [
                    'success' => false,
                    'message' => "El galpón {$numeroGalpon} no existe en la granja {$granja}"
                ];
            }
            
            // Usar el número de galpón directamente (tcodint) para pi_dim_detalles
            // No usar el ID compuesto "granja-galpon"
            $idGalponParaDetalles = $numeroGalpon;
            
            $procesadas = 0;
            
            // Procesar características si vienen
            if (isset($data['caracteristicas']) && is_array($data['caracteristicas'])) {
                foreach ($data['caracteristicas'] as $caracteristica) {
                    $idCarac = $caracteristica['id_caracteristica'] ?? null;
                    $valor = $caracteristica['valor'] ?? null;
                    
                    // Solo procesar si tiene ID de característica y valor no vacío
                    if ($idCarac && $valor !== null && $valor !== '') {
                        // Verificar si ya existe en pi_dim_detalles
                        if ($this->repo->existeDetalle($granja, $idGalponParaDetalles, $idCarac)) {
                            // UPDATE
                            $this->repo->updateDetalle([
                                'id_granja' => $granja,
                                'id_galpon' => $idGalponParaDetalles,
                                'id_caracteristica' => $idCarac,
                                'dato' => $valor,
                                'usuario_modifica' => $data['usuario_modifica'] ?? 'sistema'
                            ]);
                        } else {
                            // INSERT
                            $this->repo->insertDetalle([
                                'id_granja' => $granja,
                                'id_galpon' => $idGalponParaDetalles,
                                'id_caracteristica' => $idCarac,
                                'dato' => $valor,
                                'usuario_crea' => $data['usuario_crea'] ?? 'sistema'
                            ]);
                        }
                        $procesadas++;
                    }
                }
            }
            
            return [
                'success' => true,
                'message' => "Características guardadas exitosamente ({$procesadas} procesadas)",
                'procesadas' => $procesadas
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al guardar características: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Exportar galpones a PDF
     * 
     * @param array $filtros Filtros para aplicar
     * @return void (descarga PDF)
     */
    public function exportarPDF($filtros = []) {
        require_once __DIR__ . '/../libraries/PDFExporter.php';
        
        try {
            // Obtener datos filtrados
            $idGranja = $filtros['id_granja'] ?? null;
            $fechaDesde = $filtros['fecha_desde'] ?? null;
            $fechaHasta = $filtros['fecha_hasta'] ?? null;
            
            $galpones = $this->repo->findAll($idGranja, $fechaDesde, $fechaHasta);
            
            // Obtener características
            $caracteristicas = $this->repo->getCaracteristicas();
            
            // Crear PDF
            $pdf = new PDFExporter('L', 'mm', 'A4'); // Landscape para más columnas
            
            // Generar reporte
            $filtrosTexto = [];
            if ($idGranja) {
                $filtrosTexto['granja'] = $idGranja;
            }
            if ($fechaDesde) {
                $filtrosTexto['fechaDesde'] = date('d/m/Y', strtotime($fechaDesde));
            }
            if ($fechaHasta) {
                $filtrosTexto['fechaHasta'] = date('d/m/Y', strtotime($fechaHasta));
            }
            
            $pdf->generarReporteGalpones($galpones, $caracteristicas, $filtrosTexto);
            
            // Descargar PDF
            $filename = 'Reporte_Galpones_' . date('Y-m-d_His') . '.pdf';
            $pdf->descargar($filename);
            
        } catch (Exception $e) {
            // En caso de error, retornar JSON
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error al generar PDF: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Exportar galpones a Excel
     * 
     * @param array $filtros Filtros opcionales
     * @return void (descarga archivo Excel)
     */
    public function exportarExcel($filtros = []) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        try {
            // Obtener datos filtrados
            $idGranja = $filtros['id_granja'] ?? null;
            $fechaDesde = $filtros['fecha_desde'] ?? null;
            $fechaHasta = $filtros['fecha_hasta'] ?? null;
            
            $galpones = $this->repo->findAll($idGranja, $fechaDesde, $fechaHasta);
            $caracteristicas = $this->repo->getCaracteristicas();
            
            // Crear nuevo documento Excel
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Galpones');
            
            // Encabezados principales
            $columna = 1;
            $headers = ['#', 'Granja', 'Galpón', 'Nombre'];
            
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($columna, 1, $header);
                $columna++;
            }
            
            // Agregar características como columnas
            foreach ($caracteristicas as $carac) {
                $nombreCarac = is_array($carac) ? $carac['nombre'] : $carac->nombre;
                $sheet->setCellValueByColumnAndRow($columna, 1, $nombreCarac);
                $columna++;
            }
            
            // Estilo del encabezado
            $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columna - 1);
            $headerRange = 'A1:' . $lastColumn . '1';
            
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 11
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);
            
            // Datos
            $fila = 2;
            foreach ($galpones as $index => $galpon) {
                $columna = 1;
                
                // Columnas fijas - soportar tanto array como objeto
                $sheet->setCellValueByColumnAndRow($columna++, $fila, $index + 1);
                $sheet->setCellValueByColumnAndRow($columna++, $fila, is_array($galpon) ? $galpon['granja'] : $galpon->granja);
                $sheet->setCellValueByColumnAndRow($columna++, $fila, is_array($galpon) ? $galpon['galpon'] : $galpon->galpon);
                $sheet->setCellValueByColumnAndRow($columna++, $fila, is_array($galpon) ? ($galpon['nombre_granja'] ?? '-') : ($galpon->nombre_granja ?? '-'));
                
                // Características dinámicas
                foreach ($caracteristicas as $carac) {
                    $idCarac = is_array($carac) ? $carac['id'] : $carac->id;
                    $valor = '-';
                    
                    if (is_array($galpon)) {
                        $valor = $galpon[$idCarac] ?? $galpon[strval($idCarac)] ?? '-';
                    } else {
                        $valor = $galpon->{$idCarac} ?? $galpon->{strval($idCarac)} ?? '-';
                    }
                    
                    $sheet->setCellValueByColumnAndRow($columna++, $fila, $valor);
                }
                
                $fila++;
            }
            
            // Estilo de datos
            $lastRow = $fila - 1;
            $dataRange = 'A2:' . $lastColumn . $lastRow;
            
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC']
                    ]
                ],
                'alignment' => [
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
                ]
            ]);
            
            // Auto-ajustar ancho de columnas
            for ($col = 1; $col < $columna; $col++) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
            }
            
            // Altura de filas
            $sheet->getRowDimension(1)->setRowHeight(25);
            
            // Crear writer y descargar
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            // Limpiar todos los buffers de salida
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Headers para descarga
            $filename = 'Reporte_Galpones_' . date('Y-m-d_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            $writer->save('php://output');
            exit;
            
        } catch (Exception $e) {
            error_log("Error al exportar Excel: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Error al generar Excel: ' . $e->getMessage()
            ]);
        }
    }
}
