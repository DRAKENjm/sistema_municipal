<?php
/**
 * ============================================================
 * Configuración de conexión a la base de datos - EJEMPLO
 * SIGDOC-ML - Sistema de Gestión Documental y ML
 * ============================================================
 * 
 * INSTRUCCIONES:
 * 1. Copia este archivo y renómbralo a: database.php
 * 2. Ajusta los valores según tu configuración local
 * 3. NO subas database.php a GitHub (está en .gitignore)
 * 
 * ============================================================
 */

// ── CONFIGURACIÓN DE CONEXIÓN ──
define('DB_HOST',   '127.0.0.1');        // Host del servidor MySQL
define('DB_USER',   'root');             // Usuario de MySQL
define('DB_PASS',   '');                 // Contraseña (vacía en XAMPP por defecto)
define('DB_NAME',   'municipalidad_sigd_ml'); // Nombre de la base de datos
define('DB_CHARSET','utf8mb4');          // Codificación (soporta tildes y ñ)

/**
 * Función para obtener conexión PDO a la base de datos
 * @return PDO Objeto de conexión PDO
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En producción, registrar el error en un log
            error_log("Error de conexión DB: " . $e->getMessage());
            die(json_encode([
                'error' => 'Error de conexión a la base de datos.',
                'mensaje' => 'Por favor verifica la configuración en config/database.php'
            ]));
        }
    }
    
    return $pdo;
}
