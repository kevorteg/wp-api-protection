<?php

class WaP_Rate_Limit
{

    public function check_rate_limit($result)
    {
        // If already blocked or error, skip.
        if (is_wp_error($result)) {
            return $result;
        }

        // If user is logged in, skip rate limit (unless configured otherwise, but usually auth users are trusted)
        if (is_user_logged_in()) {
            return $result;
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $transient_lock_key = 'wap_lock_' . md5($ip);
        $transient_count_key = 'wap_count_' . md5($ip);

        // 1. Check if already blocked
        if (get_transient($transient_lock_key)) {
            return new WP_Error(
                'wap_rate_limit_exceeded',
                $this->get_biblical_message('blocked'),
                array('status' => 403)
            );
        }

        // 2. Count Attempts
        $attempts = (int) get_transient($transient_count_key);
        $limit = (int) get_option('wap_rate_limit_max', 5);

        // Logic from 'intentos-gracia.php' (Grace Attempt)
        // If attempts == limit - 1, warn them? 
        // Or if attempts >= limit

        if ($attempts >= $limit) {
            // Block for 1 hour
            set_transient($transient_lock_key, true, HOUR_IN_SECONDS);

            // Delete counter so it starts fresh after lock expires
            delete_transient($transient_count_key);

            // Log the block
            if (class_exists('WaP_Logger')) {
                WaP_Logger::log($_SERVER['REMOTE_ADDR'], 'Rate Limit', 'Exceeded ' . $limit . ' attempts');
            }

            // Troll Mode Check
            if (get_option('wap_troll_mode_enabled', false)) {
                if (class_exists('WaP_Protection')) {
                    $protector = new WaP_Protection();
                    $protector->serve_troll_response();
                }
            }

            return new WP_Error(
                'wap_rate_limit_final',
                $this->get_biblical_message('final_block'),
                array('status' => 403)
            );
        }

        // Increment
        $attempts++;
        set_transient($transient_count_key, $attempts, HOUR_IN_SECONDS);

        // If it's a grace attempt (last one)
        if ($attempts === $limit) {
            return new WP_Error(
                'wap_grace_attempt',
                $this->get_biblical_message('grace'),
                array('status' => 401)
            );
        }

        // For early attempts, maybe show a warning?
        // But WP API expects 200 or Error. If we return Error, we stop the request.
        // So we only return error on "Failures".
        // BUT wait, `rest_authentication_errors` runs on EVERY request.
        // We shouldn't count "successes" as failures logic.
        // PROBLEM: `rest_authentication_errors` runs BEFORE authentication is fully verified if we don't return null.
        // Actually, this hook is primarily to *validate* authentication or return errors.
        // If we want to rate limit *failed logins*, check `wp_login_failed`.
        // If we want to rate limit *API requests*, this is the place.
        // BUT valid requests shouldn't consume rate limit credits if they are public endpoints?
        // The user want to protect "Intrusos".
        // If we block here, we block public GET requests too.
        // That seems to be the intent ("Protección API").
        // We will assume "count every request from non-logged-in user".

        return $result;
    }

    private function get_biblical_message($type)
    {
        $messages = get_option('wap_custom_messages', array());

        // Defaults
        $defaults = array(
            'blocked' => '🚫 Has sido sellado fuera por un tiempo. Vuelve cuando tu lámpara tenga aceite. (Mateo 25:13)',
            'final_block' => '🔒 Has agotado la gracia. El acceso se ha cerrado. (Hebreos 10:26-27)',
            'grace' => '🙏 Has llegado al umbral. ¿Eres hijo de la luz o de las sombras? (Juan 12:36)'
        );

        if (isset($messages[$type]) && !empty($messages[$type])) {
            return $messages[$type];
        }

        return $defaults[$type];
    }
}
