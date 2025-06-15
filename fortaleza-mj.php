<?php
/*
Plugin Name: Fortaleza MJ
Description: Blindaje espiritual y técnico para proteger el sitio de Misión Juvenil contra intrusos digitales.
Version: 1.0
Author: Kevin Ortega y Aurora Celestial
*/

// 🛡️ Cabeceras de seguridad
function mj_seguridad_cabeceras() {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: no-referrer-when-downgrade");
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
}
add_action('send_headers', 'mj_seguridad_cabeceras');

// 🔒 Oculta usuarios en la REST API
function mj_ocultar_usuarios_api( $endpoints ) {
    if ( ! is_user_logged_in() ) {
        unset( $endpoints['/wp/v2/users'] );
        unset( $endpoints['/wp/v2/users/(?P<id>[\\d]+)'] );
    }
    return $endpoints;
}
add_filter( 'rest_endpoints', 'mj_ocultar_usuarios_api' );

// 📖 Mensajes bíblicos para intrusos
function mj_mensaje_api_rest($result) {
    if ( ! is_user_logged_in() ) {
        $mensajes = array(
            "🚫 El acceso está sellado. Solo los hijos de la luz pueden entrar. (Efesios 5:8)",
            "⚠️ Esta puerta está cerrada. Solo los redimidos pueden entrar. (Juan 10:9)",
            "🔐 Prohibido el paso a intrusos. Aquí solo se entra con la llave del Reino. (Mateo 16:19)",
            "🙅‍♂️ Tu IP ha tocado un lugar santo. Retrocede y busca la luz. (Éxodo 3:5)",
            "⛔ Este acceso está protegido por la Verdad. Solo los que caminan en ella pueden pasar. (3 Juan 1:4)",
        );
        $mensaje = $mensajes[array_rand($mensajes)];
        return new WP_Error('mj_acceso_restringido', __($mensaje, 'mision-juvenil'), array('status' => 401));
    }
    return $result;
}
add_filter('rest_authentication_errors', 'mj_mensaje_api_rest');

// 🚷 Redirecciona intentos de enumeración de autores
function mj_evitar_enumeracion_autores() {
    if ( is_author() ) {
        wp_redirect( home_url(), 301 );
        exit;
    }
}
add_action( 'template_redirect', 'mj_evitar_enumeracion_autores' );

// 🚫 Desactiva XML-RPC
add_filter('xmlrpc_enabled', '__return_false');

// 🕵️ Oculta la versión de WordPress
remove_action('wp_head', 'wp_generator');

// 🧹 Limpia cabeceras innecesarias
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('template_redirect', 'rest_output_link_header', 11 );
remove_action('wp_head', 'rest_output_link_wp_head', 10);
remove_action('wp_head', 'wp_oembed_add_discovery_links');
remove_action('wp_head', 'wp_oembed_add_host_js');

// ⚙️ Rate limiting básico para REST API
function mj_rate_limit_rest() {
    if ( ! is_user_logged_in() && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false ) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $transient_key = 'mj_rate_limit_' . md5($ip);
        $requests = (int) get_transient($transient_key);

        if ( $requests > 10 ) {
            wp_die('📛 Has hecho demasiadas peticiones. Calma tu espíritu y vuelve en unos minutos. 🙏', 'Demasiadas peticiones', array('response' => 429));
        }

        set_transient($transient_key, $requests + 1, 60);
    }
}
add_action('init', 'mj_rate_limit_rest');
?>
