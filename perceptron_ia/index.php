<?php
// Cargar primero las dependencias
require_once 'Perceptron.php';
require_once 'db_config.php';

// Iniciar sesión después de cargar las clases
session_start();

// Conexión a la base de datos
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Procesar configuración del perceptrón
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['configurar'])) {
    $umbral = floatval($_POST['umbral']);
    $factorAprendizaje = floatval($_POST['factor_aprendizaje']);
    $compuerta = $_POST['compuerta'];
    $tipo_pesos = $_POST['tipo_pesos'];
    
    // Generar pesos según selección
    $pesos = [];
    if ($tipo_pesos === 'aleatorio') {
        $pesos = [rand(0, 100) / 100, rand(0, 100) / 100];
    } else {
        $pesos = [floatval($_POST['peso1']), floatval($_POST['peso2'])];
    }
    
    // Guardar configuración en la base de datos
    $stmt = $conn->prepare("INSERT INTO configuraciones (umbral, factor_aprendizaje, compuerta) VALUES (?, ?, ?)");
    $stmt->bind_param("dds", $umbral, $factorAprendizaje, $compuerta);
    $stmt->execute();
    $configuracion_id = $conn->insert_id;
    
    // Guardar pesos
    foreach ($pesos as $i => $peso) {
        $stmt = $conn->prepare("INSERT INTO pesos (configuracion_id, entrada_num, valor) VALUES (?, ?, ?)");
        $entrada_num = $i + 1;
        $stmt->bind_param("iid", $configuracion_id, $entrada_num, $peso);
        $stmt->execute();
    }
    
    // Crear perceptrón y guardar en sesión
    $_SESSION['perceptron'] = new Perceptron($umbral, $factorAprendizaje, $pesos, $compuerta);
    $_SESSION['configuracion_id'] = $configuracion_id;
    $_SESSION['iteracion'] = 0;
    $_SESSION['resultados'] = []; // Inicializar array de resultados
}

// Procesar entrenamiento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['entrenar'])) {
    if (!isset($_SESSION['perceptron'])) {
        die("Primero configure el perceptrón");
    }
    
    $perceptron = $_SESSION['perceptron'];
    $iteracion = ++$_SESSION['iteracion'];
    $entradas = [intval($_POST['entrada1']), intval($_POST['entrada2'])];
    $salidaEsperada = 0;
    
    // Determinar salida esperada según compuerta
    if ($perceptron->getCompuerta() === 'AND') {
        $salidaEsperada = ($entradas[0] && $entradas[1]) ? 1 : 0;
    } else { // OR
        $salidaEsperada = ($entradas[0] || $entradas[1]) ? 1 : 0;
    }
    
    // Entrenar perceptrón
    $resultado = $perceptron->entrenar($entradas, $salidaEsperada, $iteracion, $conn);
    
    // Guardar resultados en sesión para mostrar
    $_SESSION['resultados'][] = [
        'iteracion' => $iteracion,
        'entradas' => $entradas,
        'salida_esperada' => $salidaEsperada,
        'salida_obtenida' => $resultado['salida_obtenida'],
        'error' => $resultado['error'],
        'pesos' => $resultado['pesos_actualizados']
    ];
    
    // Actualizar perceptrón en sesión
    $_SESSION['perceptron'] = $perceptron;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Perceptrón Simple - Proyecto IA</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; }
        .success { background-color: #d4edda; }
        .error { background-color: #f8d7da; }
        label { display: inline-block; width: 150px; }
        input, select { padding: 5px; }
        button { padding: 8px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #45a049; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Perceptrón Simple</h1>
        
        <!-- Formulario de configuración -->
        <h2>Configuración del Perceptrón</h2>
        <form method="post">
            <div class="form-group">
                <label for="compuerta">Compuerta lógica:</label>
                <select name="compuerta" id="compuerta" required>
                    <option value="AND">AND</option>
                    <option value="OR">OR</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="umbral">Umbral (θ):</label>
                <input type="number" step="0.01" name="umbral" id="umbral" value="0.5" required>
            </div>
            
            <div class="form-group">
                <label for="factor_aprendizaje">Factor de aprendizaje (η):</label>
                <input type="number" step="0.01" min="0" max="1" name="factor_aprendizaje" id="factor_aprendizaje" value="0.1" required>
            </div>
            
            <div class="form-group">
                <label for="tipo_pesos">Tipo de pesos iniciales:</label>
                <select name="tipo_pesos" id="tipo_pesos" onchange="togglePesosManual()">
                    <option value="aleatorio">Aleatorios</option>
                    <option value="manual">Manuales</option>
                </select>
            </div>
            
            <div class="form-group" id="pesos_manual" style="display: none;">
                <label for="peso1">Peso 1 (w₁):</label>
                <input type="number" step="0.01" name="peso1" id="peso1" value="0.5">
                
                <label for="peso2">Peso 2 (w₂):</label>
                <input type="number" step="0.01" name="peso2" id="peso2" value="0.5">
            </div>
            
            <button type="submit" name="configurar">Configurar Perceptrón</button>
        </form>
        
        <?php if (isset($_SESSION['perceptron'])): ?>
        <!-- Formulario de entrenamiento -->
        <h2>Entrenamiento del Perceptrón</h2>
        <form method="post">
            <div class="form-group">
                <label for="entrada1">Entrada 1 (x₁):</label>
                <select name="entrada1" id="entrada1" required>
                    <option value="0">0</option>
                    <option value="1">1</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="entrada2">Entrada 2 (x₂):</label>
                <select name="entrada2" id="entrada2" required>
                    <option value="0">0</option>
                    <option value="1">1</option>
                </select>
            </div>
            
            <button type="submit" name="entrenar">Entrenar</button>
        </form>
        
        <!-- Resultados del entrenamiento -->
        <h2>Resultados</h2>
        <?php if (!empty($_SESSION['resultados'])): ?>
        <table>
            <tr>
                <th>Iteración</th>
                <th>Entradas (x₁, x₂)</th>
                <th>Salida Esperada</th>
                <th>Salida Obtenida</th>
                <th>Error</th>
                <th>Pesos (w₁, w₂)</th>
            </tr>
            <?php foreach ($_SESSION['resultados'] as $resultado): ?>
            <tr class="<?= $resultado['error'] == 0 ? 'success' : 'error' ?>">
                <td><?= $resultado['iteracion'] ?></td>
                <td><?= implode(', ', $resultado['entradas']) ?></td>
                <td><?= $resultado['salida_esperada'] ?></td>
                <td><?= $resultado['salida_obtenida'] ?></td>
                <td><?= $resultado['error'] ?></td>
                <td><?= implode(', ', $resultado['pesos']) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <h3>Parámetros aprendidos</h3>
        <p>Umbral final: <?= $_SESSION['perceptron']->getUmbral() ?></p>
        <p>Pesos finales: <?= implode(', ', $_SESSION['perceptron']->getPesos()) ?></p>
        <p>Factor de aprendizaje: <?= $_SESSION['perceptron']->getFactorAprendizaje() ?></p>
        <p>Compuerta lógica: <?= $_SESSION['perceptron']->getCompuerta() ?></p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        function togglePesosManual() {
            const tipoPesos = document.getElementById('tipo_pesos').value;
            const pesosManual = document.getElementById('pesos_manual');
            pesosManual.style.display = tipoPesos === 'manual' ? 'block' : 'none';
        }
    </script>
</body>
</html>