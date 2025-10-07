<?php
/**
 * Plugin Name: Notificador de Ofertas
 * Description: Envía notificaciones a Firebase cuando se publica un producto en la categoría "ofertas".
 * Version: 2.0
 * Author: @federkone github
 */

require_once __DIR__ . '/lib/vendor/autoload.php';

// Configuración
define('NO_CATEGORIA_OFERTAS', 'Ofertas');
define('NO_SERVICE_ACCOUNT_PATH', __DIR__ . '/service-account.json');

// Agregar meta box para pruebas
add_action('add_meta_boxes', 'add_test_button_meta_box');
function add_test_button_meta_box() {
    add_meta_box(
        'test_notification',
        'Notificaciónes Android',
        'test_notification_callback',
        'product',
        'side',
        'high'
    );
}

function test_notification_callback($post) {
    $checked = get_post_meta($post->ID, '_enviar_notificacion_al_publicar', true);
    if ($checked === '0') $checked = '1'; 
    ?>
    <p>
        <label>
            <input type="checkbox" name="enviar_notificacion_al_publicar" value="1" <?php checked($checked, '1'); ?>>
            Enviar notificación al publicar Oferta
        </label>
    </p>

    <?php

    /*     <form method="post">
        <?php wp_nonce_field('prueba_notificacion_producto', 'test_nonce'); ?>
        <input type="hidden" name="producto_id" value="<?php echo esc_attr($post->ID); ?>">
        <button name="enviar_prueba_producto" class="button button-primary">
            Enviar Prueba
        </button>
    </form>*/
}
add_action('save_post_product', 'guardar_valor_checkbox_notificacion');
function guardar_valor_checkbox_notificacion($post_id) {
    // Evita autoguardados
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Solo guardar si es un producto
    if (get_post_type($post_id) !== 'product') return;

    if (isset($_POST['enviar_notificacion_al_publicar'])) {
        update_post_meta($post_id, '_enviar_notificacion_al_publicar', '1');
    } else {
        update_post_meta($post_id, '_enviar_notificacion_al_publicar', '0');
    }
}
// Procesar el test cuando se envía el formulario
add_action('admin_init', 'process_test_notification');
function process_test_notification() {
    if (!isset($_POST['enviar_prueba_producto']) || !wp_verify_nonce($_POST['test_nonce'], 'prueba_notificacion_producto')) {
        return;
    }

    // Resultados de la prueba
    $debug_info = [];
    $debug_info[] = "Iniciando prueba de notificación FCM...";
    
    // Verificar archivo de credenciales
    if (!file_exists(NO_SERVICE_ACCOUNT_PATH)) {
        $debug_info[] = "❌ Error: Archivo de credenciales no encontrado en: " . NO_SERVICE_ACCOUNT_PATH;
        set_transient('fcm_test_debug', $debug_info, 60);
        return;
    }
    $debug_info[] = "✔ Archivo de credenciales encontrado";

    try {
        $debug_info[] = "Inicializando cliente Google...";
        $client = new Google\Client();
        $client->setAuthConfig(NO_SERVICE_ACCOUNT_PATH);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        
        $debug_info[] = "Obteniendo token de acceso...";
        $token_data = $client->fetchAccessTokenWithAssertion();
        
        if (!isset($token_data['access_token'])) {
            $debug_info[] = "❌ Error al obtener token de acceso";
            set_transient('fcm_test_debug', $debug_info, 60);
            return;
        }
        
        $accessToken = $token_data['access_token'];
        $debug_info[] = "✔ Token de acceso obtenido";
        
    } catch (Exception $e) {
        $debug_info[] = "❌ Excepción: " . $e->getMessage();
        set_transient('fcm_test_debug', $debug_info, 60);
        return;
    }

    // Preparar datos de la notificación
    $data = [
        'message' => [
            'topic' => strtolower(NO_CATEGORIA_OFERTAS),
            'notification' => [
                'title' => 'Prueba de notificación',
                'body' => 'Este es un mensaje de prueba enviado desde WordPress'
            ]
        ]
    ];

    $debug_info[] = "Enviando a topic: " . strtolower(NO_CATEGORIA_OFERTAS);
    $debug_info[] = "Datos enviados: " . json_encode($data, JSON_PRETTY_PRINT);

    // Enviar la solicitud
    $response = wp_remote_post('https://fcm.googleapis.com/v1/projects/lafamiliaminimercado-149c5/messages:send', [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json; charset=UTF-8',
        ],
        'body' => json_encode($data),
        'timeout' => 30,
    ]);

    // Procesar respuesta
    if (is_wp_error($response)) {
        $debug_info[] = "❌ Error en la solicitud: " . $response->get_error_message();
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        $debug_info[] = "Código de estado: " . $status_code;
        $debug_info[] = "Respuesta completa: " . $body;
        
        if ($status_code === 200) {
            $debug_info[] = "✔ Notificación enviada con éxito";
        } else {
            $debug_info[] = "❌ Error en la respuesta de FCM";
        }
    }

    set_transient('fcm_test_debug', $debug_info, 60);
}

// Mostrar resultados en admin notices
add_action('admin_notices', 'show_test_result');
function show_test_result() {
    $debug_info = get_transient('fcm_test_debug');
    if (!$debug_info || !is_array($debug_info)) return;
    
    delete_transient('fcm_test_debug');
    ?>
    <div class="notice notice-info">
        <h3>Resultados de Prueba FCM</h3>
        <ul style="margin-bottom:0;">
            <?php foreach ($debug_info as $line) : ?>
                <li><?php echo wp_kses_post($line); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}


add_action('woocommerce_process_product_meta', 'notificar_producto_oferta', 10, 2);

function notificar_producto_oferta($post_id, $post) {
    // Verificar si debe enviarse notificación
    $debe_enviar = get_post_meta($post_id, '_enviar_notificacion_al_publicar', true);
    if ($debe_enviar == '0') return;
    if (!has_term('ofertas', 'product_cat', $post_id)) return;

    $product = wc_get_product($post_id);
    if (!$product) return;

    $titulo = $product->get_name();
    $precio = $product->get_price();

    if (!$precio){ $precio = '';
    }else{$precio= "a solo $precio$";}

    // Cargar credenciales
    if (!file_exists(NO_SERVICE_ACCOUNT_PATH)) return;

    try {
        $client = new Google\Client();
        $client->setAuthConfig(NO_SERVICE_ACCOUNT_PATH);
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $token_data = $client->fetchAccessTokenWithAssertion();

        if (!isset($token_data['access_token'])) return;

        $accessToken = $token_data['access_token'];
    } catch (Exception $e) {
        error_log('Error al obtener token FCM: ' . $e->getMessage());
        return;
    }

    // Preparar datos de la notificación
    $data = [
        'message' => [
            'topic' => strtolower(NO_CATEGORIA_OFERTAS),
            'notification' => [
                'title' => "¡Oferta imperdible!",
                'body' => "$titulo $precio"
            ],
            'data' => [
                'product_id' => (string) $post_id,
                'price' => (string) $precio,
                'source' => 'woocommerce'
            ]
        ]
    ];

    // Enviar la solicitud a FCM
    $response = wp_remote_post('https://fcm.googleapis.com/v1/projects/lafamiliaminimercado-149c5/messages:send', [
        'headers' => [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json; charset=UTF-8',
        ],
        'body' => json_encode($data),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        error_log('Error al enviar notificación FCM: ' . $response->get_error_message());
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        error_log("Notificación enviada (Código: $status_code): $body");
    }
}