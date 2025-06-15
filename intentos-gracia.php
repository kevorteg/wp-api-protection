<?php
/**
 * Control de intentos con mensaje bíblico y bloqueo por IP
 * Versión: 1.0
 * Autor: Kevin y Aurora Celestial
 */

function mj_limitar_intentos_rest($result) {
    if (is_user_logged_in()) return $result;

    $ip = $_SERVER['REMOTE_ADDR'];
    $clave_intentos = 'mj_intentos_' . md5($ip);
    $clave_bloqueado = 'mj_bloqueado_' . md5($ip);

    // Verifica si la IP está bloqueada
    if (get_transient($clave_bloqueado)) {
        return new WP_Error('mj_bloqueado', __('🚫 Has sido sellado fuera por un tiempo. Vuelve cuando tu lámpara tenga aceite. (Mateo 25:13)', 'mision-juvenil'), array('status' => 403));
    }

    $intentos = (int) get_transient($clave_intentos);

    // Si ya falló 3 veces
    if ($intentos >= 3) {
        // Intento de gracia
        if ($intentos == 3) {
            $mensaje = "🙏 Has llegado al umbral. ¿Eres hijo de la luz o de las sombras? (Juan 12:36)";
            set_transient($clave_intentos, 4, HOUR_IN_SECONDS);
            return new WP_Error('mj_ultimo_intento', __($mensaje, 'mision-juvenil'), array('status' => 401));
        }

        // Bloquea IP por 1 hora
        set_transient($clave_bloqueado, true, HOUR_IN_SECONDS);
        return new WP_Error('mj_bloqueado', __('🔒 Has agotado la gracia. El acceso se ha cerrado. (Hebreos 10:26-27)', 'mision-juvenil'), array('status' => 403));
    }

    // Incrementa el contador
    set_transient($clave_intentos, $intentos + 1, HOUR_IN_SECONDS);

    // Mensajes aleatorios para los primeros intentos
    $mensajes = array(
        "⚠️ Esta puerta está custodiada. Solo los santos pasarán. (Salmos 24:3-4)",
        "⛔ Este umbral es santo. No entres sin propósito. (Éxodo 3:5)",
        "🔐 Aún no tienes acceso. Busca la verdad primero. (Juan 14:6)"
    );

    $mensaje = $mensajes[array_rand($mensajes)];
    return new WP_Error('mj_acceso_restringido', __($mensaje, 'mision-juvenil'), array('status' => 401));
}

add_filter('rest_authentication_errors', 'mj_limitar_intentos_rest');

// 🛑 Sistema de intentos fallidos
require_once plugin_dir_path(__FILE__) . 'intentos-fallidos.php';
