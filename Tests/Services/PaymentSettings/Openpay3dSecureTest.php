<?php
namespace OpenpayCards\Services\PaymentSettings {

    // Simulamos la función get_option para interceptar la configuración de SSL
    if (!function_exists('OpenpayCards\Services\PaymentSettings\get_option')) {
        function get_option($option_name) {
            return $GLOBALS['mock_wp_options'][$option_name] ?? 'yes';
        }
    }

    // Simulamos la función site_url para controlar el dominio base generado
    if (!function_exists('OpenpayCards\Services\PaymentSettings\site_url')) {
        function site_url($path = '', $scheme = null) {
            $base_url = ($scheme === 'http') ? 'http://mysite.mock' : 'https://mysite.mock';
            return $base_url . $path;
        }
    }
}

// =========================================================================
// 2. CLASE DE PRUEBAS UNITARIAS
// =========================================================================

namespace OpenpayCards\Tests {

    use PHPUnit\Framework\TestCase;
    use OpenpayCards\Services\PaymentSettings\Openpay3dSecure;

    /**
     * @covers \OpenpayCards\Services\PaymentSettings\Openpay3dSecure
     */
    class Openpay3dSecureTest extends \WP_UnitTestCase
    {
        public function setUp(): void
        {
            parent::setUp();

            // Blindaje obligatorio para inicialización de pasarelas en entornos CLI
            if (!isset($_SERVER['REMOTE_ADDR'])) {
                $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
            }

            $GLOBALS['mock_wp_options'] = [];
        }

        public function tearDown(): void
        {
            parent::tearDown();
            unset($GLOBALS['mock_wp_options']);
        }

        // =====================================================================
        // CASO 1: SSL Forzado Activo ('yes' u otra opción por defecto) -> HTTPS
        // =====================================================================
        public function test_redirect_url_3d_uses_https_by_default()
        {
            // Seteamos la opción de WooCommerce para forzar SSL de forma afirmativa
            $GLOBALS['mock_wp_options']['woocommerce_force_ssl_checkout'] = 'yes';

            $url = Openpay3dSecure::redirect_url_3d();

            // Verificamos que construya la URL de retorno con el webhook de Openpay seguro
            $this->assertEquals('https://mysite.mock/?wc-api=openpay_confirm', $url);
        }

        // =====================================================================
        // CASO 2: SSL Forzado Inactivo ('no') -> HTTP
        // =====================================================================
        public function test_redirect_url_3d_uses_http_when_ssl_forced_is_no()
        {
            // Cambiamos la configuración a 'no' para forzar la bifurcación condicional
            $GLOBALS['mock_wp_options']['woocommerce_force_ssl_checkout'] = 'no';

            $url = Openpay3dSecure::redirect_url_3d();

            // Verificamos que use el esquema no seguro sin romper los parámetros del webhook
            $this->assertEquals('http://mysite.mock/?wc-api=openpay_confirm', $url);
        }

        // =====================================================================
        // CASO 3: Validación del Constructor de la Clase
        // =====================================================================
        public function test_constructor_can_be_instantiated()
        {
            $instance = new Openpay3dSecure();
            $this->assertInstanceOf(Openpay3dSecure::class, $instance);
        }
    }
}