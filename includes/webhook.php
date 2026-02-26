<?php
defined('ABSPATH') || exit;

/**
 * Registro da rota REST para webhook Sila Pay
 */
add_action('rest_api_init', function () {
    register_rest_route('silapay/v1', '/webhook', [
        'methods'  => 'POST',
        'callback' => 'silapay_webhook_handler',
        'permission_callback' => '__return_true',
    ]);
    
    register_rest_route('silapay/v1', '/test-connection', [
        'methods'  => 'POST',
        'callback' => 'silapay_test_connection',
        'permission_callback' => '__return_true',
    ]);
        
});

/*
    Handler do test connection
*/

function silapay_test_connection($request) {
    return new WP_REST_Response([
        'success' => true,
        'message' => 'Conexão estabelecida com sucesso!',
        'timestamp' => current_time('mysql')
    ], 200);
}

/**
 * Handler do Webhook Silapay
 */
function silapay_webhook_handler($request) {

    $body = $request->get_body();
    $data = json_decode($body, true);

    if (!$data) {
        return new WP_REST_Response(['error' => 'Payload inválido'], 400);
    }

    //  Validação de assinatura (OBRIGATÓRIO EM PRODUÇÃO)
    $options = get_option('woocommerce_silapay_checkout_settings');
    $webhook_secret = $options['webhook_secret'] ?? '';

    $incoming_signature = $_SERVER['HTTP_X_SILAPAY_SIGNATURE'] ?? '';

    if (!empty($webhook_secret)) {
        $expected_signature = hash_hmac('sha256', $body, $webhook_secret);

        if (!hash_equals($expected_signature, $incoming_signature)) {
            return new WP_REST_Response(['error' => 'Assinatura inválida'], 403);
        }
    }

    $order_id = $data['orderId'] ?? null;
    $status   = $data['status'] ?? null;
    $transaction_id = $data['transactionId'] ?? null;

    if (!$order_id || !$status) {
        return new WP_REST_Response(['error' => 'Dados incompletos'], 400);
    }

    $order = wc_get_order($order_id);

    if (!$order) {
        return new WP_REST_Response(['error' => 'Pedido não encontrado'], 404);
    }

    // Evita processar duas vezes
    if ($order->get_meta('_silapay_webhook_processed') === 'yes') {
        return new WP_REST_Response(['message' => 'Webhook já processado'], 200);
    }

    switch ($status) {

        case 'paid':

            if (!in_array($order->get_status(), ['processing', 'completed'])) {

                $order->payment_complete($transaction_id);
                $order->add_order_note('Pagamento confirmado via Webhook Sila Pay.');

                $order->update_meta_data('_silapay_transaction_id', $transaction_id);
                $order->update_meta_data('_silapay_webhook_processed', 'yes');
                $order->save();
            }

            break;

        case 'failed':

            $order->update_status('failed', 'Pagamento recusado via Webhook Silapay.');
            break;

        case 'canceled':

            $order->update_status('cancelled', 'Pagamento cancelado via Webhook Silapay.');
            break;
    }

    return new WP_REST_Response(['success' => true], 200);
}