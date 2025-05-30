<?php
class Perceptron {
    private $umbral;
    private $factorAprendizaje;
    private $pesos = [];
    private $compuerta;
    
    public function __construct($umbral, $factorAprendizaje, $pesosIniciales, $compuerta) {
        $this->umbral = $umbral;
        $this->factorAprendizaje = $factorAprendizaje;
        $this->pesos = $pesosIniciales;
        $this->compuerta = $compuerta;
    }
    
    /**
     * Función de activación (escalón)
     * @param float $sumatoria Suma ponderada de entradas
     * @return int 0 o 1 según el umbral
     */
    private function funcionActivacion($sumatoria) {
        return $sumatoria >= $this->umbral ? 1 : 0;
    }
    
    /**
     * Entrena el perceptrón con una entrada
     * @param array $entradas Valores de entrada [x1, x2]
     * @param int $salidaEsperada Resultado esperado (0 o 1)
     * @param int $iteracion Número de iteración actual
     * @param mysqli $conn Conexión a la base de datos
     * @return array Resultados del entrenamiento
     */
    public function entrenar($entradas, $salidaEsperada, $iteracion, $conn) {
        $sumatoria = 0;
        foreach ($entradas as $i => $valor) {
            $sumatoria += $valor * $this->pesos[$i];
        }
        
        $salidaObtenida = $this->funcionActivacion($sumatoria);
        $error = $salidaEsperada - $salidaObtenida;
        
        // Ajustar pesos si hay error
        if ($error != 0) {
            foreach ($this->pesos as $i => $peso) {
                $this->pesos[$i] += $this->factorAprendizaje * $error * $entradas[$i];
            }
        }
        
        // Guardar resultado en la base de datos
        $this->guardarResultado($conn, $iteracion, $entradas, $salidaEsperada, $salidaObtenida, $error);
        
        return [
            'salida_obtenida' => $salidaObtenida,
            'error' => $error,
            'pesos_actualizados' => $this->pesos
        ];
    }
    
    /**
     * Guarda los resultados en la base de datos
     */
    private function guardarResultado($conn, $iteracion, $entradas, $salidaEsperada, $salidaObtenida, $error) {
        $stmt = $conn->prepare("INSERT INTO resultados (configuracion_id, iteracion, entrada1, entrada2, salida_esperada, salida_obtenida, error) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiiid", $_SESSION['configuracion_id'], $iteracion, $entradas[0], $entradas[1], $salidaEsperada, $salidaObtenida, $error);
        $stmt->execute();
    }
    
    // Métodos getter para acceder a las propiedades
    
    public function getUmbral() {
        return $this->umbral;
    }
    
    public function getFactorAprendizaje() {
        return $this->factorAprendizaje;
    }
    
    public function getPesos() {
        return $this->pesos;
    }
    
    public function getCompuerta() {
        return $this->compuerta;
    }
}
?>