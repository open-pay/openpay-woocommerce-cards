<?php
/**
 * PHPUnit bootstrap file para Openpay WooCommerce Cards
 */

// Obtener la ruta del entorno de pruebas desde variable de entorno o usar default
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/opt/wp-test-environment/wordpress-tests-lib';
}

// Verificar que el directorio existe
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Error: No se encuentra el entorno de pruebas en: {$_tests_dir}\n";
    echo "Verifica que WP_TESTS_DIR esté configurado correctamente.\n";
    exit(1);
}

// Carga las funciones de prueba de WordPress
require_once $_tests_dir . '/includes/functions.php';

/**
 * Cargar WooCommerce antes que el plugin
 */
function _manually_load_woocommerce() {
    $wc_core_dir = getenv('WP_CORE_DIR') ?: '/opt/wp-test-environment/wordpress';
    $woocommerce_path = $wc_core_dir . '/wp-content/plugins/woocommerce/woocommerce.php';

    if (file_exists($woocommerce_path)) {
        require $woocommerce_path;
    } else {
        echo "Advertencia: No se encontró WooCommerce en: {$woocommerce_path}\n";
    }
}
tests_add_filter('muplugins_loaded', '_manually_load_woocommerce');

/**
 * Instalar WooCommerce después de que WordPress esté configurado
 */
function _install_woocommerce() {
    // Definir constantes de WooCommerce para modo de prueba (solo si no existen)
    if (!defined('WC_TAX_ROUNDING_MODE')) {
        define('WC_TAX_ROUNDING_MODE', 'auto');
    }
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        define('WP_UNINSTALL_PLUGIN', false);
    }

    // Actualizar la versión de WooCommerce en la BD para forzar instalación de tablas
    update_option('woocommerce_version', null);

    // Instalar WooCommerce
    if (class_exists('WC_Install')) {
        WC_Install::install();

        // Crear las tablas de WooCommerce
        WC_Install::create_tables();

        // Verificar instalación (modo silencioso)
        global $wpdb;
        $tables = [
            "{$wpdb->prefix}woocommerce_attribute_taxonomies",
            "{$wpdb->prefix}wc_webhooks"
        ];

        $all_tables_created = true;
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'");
            if (!$table_exists) {
                $all_tables_created = false;
                echo "⚠️  Advertencia: No se pudo crear la tabla {$table}\n";
            }
        }

        if ($all_tables_created) {
            echo "✅ Tablas de WooCommerce creadas correctamente\n";
        }
    }
}
tests_add_filter('setup_theme', '_install_woocommerce');

/**
 * Cargar el plugin de Openpay
 */
function _manually_load_plugin() {
    // Buscar el archivo principal del plugin
    $plugin_dir = dirname(dirname(__FILE__));
    $possible_files = [
        $plugin_dir . '/openpay_cards.php',
        $plugin_dir . '/class-wc-openpay-gateway.php',
        $plugin_dir . '/openpay-woocommerce-cards.php'
    ];

    foreach ($possible_files as $file) {
        if (file_exists($file)) {
            require $file;
            echo "✅ Plugin cargado desde: " . basename($file) . "\n";
            return;
        }
    }

    echo "Error: No se encontró el archivo principal del plugin\n";
    echo "Archivos buscados:\n";
    foreach ($possible_files as $file) {
        echo "  - {$file}\n";
    }
    exit(1);
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Inicia el entorno de pruebas de WordPress
require $_tests_dir . '/includes/bootstrap.php';

// Mensaje de confirmación
echo "\n";
echo "========================================\n";
echo "  Entorno de Pruebas Inicializado\n";
echo "========================================\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "WooCommerce Version: " . (defined('WC_VERSION') ? WC_VERSION : 'No disponible') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "========================================\n\n";