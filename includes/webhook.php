<?php
defined('ABSPATH') || exit;

class WC_Silapay_Checkout extends WC_Payment_Gateway {

    // private $api_url = 'https://api.silapay.pro/v1/'; 
    public $id = "silapay_checkout";
    public $api_url = 'http://127.0.0.1:5000/v1/';
    public $access_token;
    public $secret_key;
    public $store_settings = array();

    // PARAMETROS DO PAINEL SILA PAY
    public $payment_methods = array();
    public $woocommerce_store_name = '';
    public $redirect_url = '';
    public $tax_assume = 'seller';
    public $store_id = '';
    public $seller_id = '';
    
    
    public function __construct() {
        $this->id                 = 'silapay_checkout';
        $this->method_title       = 'Silapay Checkout';
        $this->method_description = 'Aceite pagamentos via Cartão, PIX e Boleto';
        $this->has_fields         = true;
        $this->supports           = array('products');
        
        // Carrega configurações
        $this->init_form_fields();
        $this->init_settings();
        
        // Define variáveis
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->enabled      = $this->get_option('enabled');
        $this->access_token = $this->get_option('access_token');
        $this->secret_key   = $this->get_option('secret_key');

        // Ações
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        
        $this->register_webhook_automatically();
    }


    
    // adicionando os estilos
    public function enqueue_styles() {
    if (is_checkout() || is_checkout_pay_page()) {
        wp_enqueue_style(
            'silapay-checkout-styles',
            plugin_dir_url(__FILE__) . '../assets/css/silapay-checkout.css', // Ajuste o caminho
            array(),
            '1.0.0'
        );
    }
}
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Ativar/Desativar',
                'type'    => 'checkbox',
                'label'   => 'Ativar Sila Pay Checkout',
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => 'Título',
                'type'        => 'text',
                'description' => 'Título que o cliente vê durante o checkout.',
                'default'     => 'Silapay (Cartão, PIX, Boleto)',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => 'Descrição',
                'type'        => 'textarea',
                'description' => 'Descrição que o cliente vê durante o checkout.',
                'default'     => 'Pague com cartão, PIX ou boleto bancário.',
                'desc_tip'    => true,
            ),
            'access_token' => array(
                'title'       => 'Access Token',
                'type'        => 'text',
                'description' => 'Seu Access Token fornecido pela Sila Pay.',
            ),
            'secret_key' => array(
                'title'       => 'Secret Key',
                'type'        => 'password',
                'description' => 'Sua Secret Key fornecida pela Sila Pay.',
            ),    
            'webhookSecret' => [
                'title' => 'Webhook Secret',
                'type'  => 'password',
                'custom_attributes' => [
                    'readonly' => 'readonly'
                ],
            ]        
        );
    }
    
    public function payment_fields() {

    ?>
        <div>
            <img src="<?php echo plugin_dir_url(__FILE__) . '../assets/images/logo-small.png';?>" alt="Sila Pay Logo" style="border-radius:12px; margin-left:0px;" width="46" height="46">
        </div>
    <?php
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }        
        ?>

        

        <div class="silapay-methods">            
            <?php if(in_array("creditCard", $this->payment_methods)): ?>
            <p>
                <input type="radio" name="silapay_method" value="card" id="silapay-card" checked>
                <label for="silapay-card"><strong>💳 Cartão de Crédito</strong></label>
            </p>
            
            <div id="silapay-card-fields" style="margin-left: 20px; margin-bottom: 15px;">
                <p>
                    <label>Número do cartão *<br>
                    <input type="text" name="card_number" placeholder="4444 4444 4444 4444" maxlength="16">
                    </label>
                </p>
                
                <p>
                    <div style="display: flex; justify-content: space-between;">
                        <div style="width: 48%;">
                            <label>Mês (MM) *<br>
                            <input type="number" max="12" min="01" name="card_expiration_month" placeholder="Ex: 05" style="width: 100%;">
                            </label>
                        </div>
                        <div style="width: 48%;">
                            <label>Ano (AA) *<br>
                            <input type="number" min="<?php echo date("y");?>" max="<?php echo date("y") + 20;?>" name="card_expiration_year" placeholder="Ex: <?php echo date("y") + 5;?>" style="width: 100%; padding: 8px;">
                            </label>
                        </div>
                    </div>
                </p>

                <p>
                    <label>CVC *<br>
                    <input type="text" name="card_cvc" placeholder="123" style="width: 60px; padding: 8px;">
                    </label>
                </p>
                
                <p>
                    <label>Nome no cartão *<br>
                    <input type="text" name="card_holder" placeholder="João da Silva" style="width: 100%; padding: 8px;">
                    </label>
                </p>
                
                <p>
                    <label>CPF do titular * (sem pontos e traços)<br>
                    <input type="text" name="card_cpf" placeholder="Ex: 123.456.789-00" maxlength="11" style="width: 100%; padding: 8px;">
                    </label>
                </p>
                
                <p>
                    <label>Parcelas<br>
                    <select name="installments" style="padding: 8px;">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?>x</option>
                        <?php endfor; ?>
                    </select>
                    </label>
                </p>
            </div>
            <?php endif ;?>

            <?php  if(in_array("pix", $this->payment_methods)): ?>
            <p>
                <input type="radio" name="silapay_method" value="pix" id="silapay-pix">
                <label for="silapay-pix"><strong>📱 PIX</strong></label>
            </p>
            
            <div id="silapay-pix-info" style="margin-left: 20px; display: none;">
                <p><em>Após confirmar, será gerado um QR Code para pagamento.</em></p>
            </div>
            <?php endif; ?>
            
            <?php /* if(in_array("billet", $this->payment_methods)): ?>
            <p>
                <input type="radio" name="silapay_method" value="boleto" id="silapay-boleto">
                <label for="silapay-boleto"><strong>📄 Boleto Bancário</strong></label>
            </p>
            
            
            <div id="silapay-boleto-info" style="margin-left: 20px; display: none;">
                <p><em>O boleto será gerado após a confirmação do pedido.</em></p>
                <p>
                    <label>CPF/CNPJ para o boleto *<br>
                    <input type="text" name="boleto_document" placeholder="Digite seu CPF ou CNPJ" style="width: 100%; padding: 8px;">
                    </label>
                </p>
            </div>
            <?php endif; */?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('input[name="silapay_method"]').change(function() {
                $('#silapay-card-fields').toggle($(this).val() === 'card');
                $('#silapay-pix-info').toggle($(this).val() === 'pix');
                $('#silapay-boleto-info').toggle($(this).val() === 'boleto');
            });
        });
        </script>
        <?php
    }
    
    public function validate_fields() {
        $method = isset($_POST['silapay_method']) ? sanitize_text_field($_POST['silapay_method']) : '';
        
        if (empty($method)) {
            wc_add_notice('Selecione uma forma de pagamento.', 'error');
            return false;
        }
        
        if ($method === 'card') {
            $card_number = isset($_POST['card_number']) ? sanitize_text_field($_POST['card_number']) : '';
            $expiration_month = isset($_POST['card_expiration_month']) ? sanitize_text_field($_POST['card_expiration_month']) : '';
            $expiration_year = isset($_POST['card_expiration_year']) ? sanitize_text_field($_POST['card_expiration_year']) : '';
           
            $card_cvc = isset($_POST['card_cvc']) ? sanitize_text_field($_POST['card_cvc']) : '';
            $card_holder = isset($_POST['card_holder']) ? sanitize_text_field($_POST['card_holder']) : '';
            $card_cpf = isset($_POST['card_cpf']) ? sanitize_text_field($_POST['card_cpf']) : '';
            
            if (strlen(str_replace(' ', '', $card_number)) < 13) {
                wc_add_notice('Número do cartão inválido.', 'error');
                return false;
            } 


            if ($expiration_month < 1 || $expiration_month > 12) {
                wc_add_notice('Mês de validade inválido. Deve ser entre 01 e 12.', 'error');
                return false;
            }

            if ($expiration_year < date('y')) {
                wc_add_notice('Ano de validade inválido. O cartão já está expirado.', 'error');
                return false;
            }
            
            if (!preg_match('/^\d{3,4}$/', $card_cvc)) {
                wc_add_notice('CVC inválido.', 'error');
                return false;
            }
            
            if (empty($card_holder)) {
                wc_add_notice('Nome no cartão é obrigatório.', 'error');
                return false;
            }
            
            if (empty($card_cpf)) {
                wc_add_notice('CPF do titular é obrigatório.', 'error');
                return false;
            }
        }
        
        if ($method === 'boleto') {
            $document = isset($_POST['boleto_document']) ? sanitize_text_field($_POST['boleto_document']) : '';
            if (empty($document)) {
                wc_add_notice('CPF/CNPJ é obrigatório para boleto.', 'error');
                return false;
            }
        }
        
        return true;
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // Salva método escolhido
        $method = isset($_POST['silapay_method']) ? sanitize_text_field($_POST['silapay_method']) : 'card';
        $order->update_meta_data('_silapay_method', $method);
        
        // Salva dados específicos
        if ($method === 'card') {
            $card_data = array(
                'number' => isset($_POST['card_number']) ? sanitize_text_field($_POST['card_number']) : '',
                'card_expiration_month' => isset($_POST['card_expiration_month']) ? sanitize_text_field($_POST['card_expiration_month']) : '',
                'card_expiration_year' => isset($_POST['card_expiration_year']) ? sanitize_text_field($_POST['card_expiration_year']) : '',
                'cvc' => isset($_POST['card_cvc']) ? sanitize_text_field($_POST['card_cvc']) : '',
                'holder' => isset($_POST['card_holder']) ? sanitize_text_field($_POST['card_holder']) : '',
                'cpf' => isset($_POST['card_cpf']) ? sanitize_text_field($_POST['card_cpf']) : '',
                'installments' => isset($_POST['installments']) ? intval($_POST['installments']) : 1,
            );
            $order->update_meta_data('_silapay_card_data', $card_data);
        } elseif ($method === 'boleto') {
            $boleto_data = array(
                'document' => isset($_POST['boleto_document']) ? sanitize_text_field($_POST['boleto_document']) : '',
            );
            $order->update_meta_data('_silapay_boleto_data', $boleto_data);
        }
        
        $order->save();
        
        // Marca como pendente
        $order->update_status('pending', 'Aguardando pagamento via Silapay');
        
        // Redireciona para página de processamento
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true)
        );
    }
    
    public function receipt_page($order_id) {
        $order = wc_get_order($order_id);
        $method = $order->get_meta('_silapay_method', true);
        
        echo '<div class="silapay-processing">';
        echo '<h3>Processando pagamento...</h3>';
        
        // Processa pagamento baseado no método
        switch ($method) {
            case 'card':
                $this->process_card_payment($order);
                break;
            case 'pix':
                $this->process_pix_payment($order);
                break;
            case 'boleto':
                $this->process_boleto_payment($order);
                break;
            default:
                echo '<p class="error">Método de pagamento inválido.</p>';
        }
        
        echo '</div>';
    }

    public function process_admin_options() { 
        $saved = parent::process_admin_options();
        
        // Recarrega as configurações após salvar
        $this->init_settings();
        $this->access_token = $this->get_option('access_token');
        $this->secret_key = $this->get_option('secret_key');
        
       
        if (!empty($this->access_token) && !empty($this->secret_key)) {
            return false;
        }

        
            
        return $saved;
    }

    private function register_webhook_automatically() {
        
        // Pega o webhookSecret salvo nas configurações
        $webhookSecret = $this->get_option('webhookSecret');
        

        $settings = get_option('woocommerce_silapay_checkout_settings', []);

        // Gera secret se não existir ainda no banco
        if (empty($settings['webhookSecret'])) {
            $settings['webhookSecret'] = wp_generate_password(32, false);
            update_option('woocommerce_silapay_checkout_settings', $settings);

            $webhookSecret = $settings['webhookSecret'];
        }

        $postbackUrl = home_url('/wp-json/silapay/v1/webhook');

        wp_remote_post($this->api_url.'integrations/woocommerce/secret/register', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'api-key' => $this->access_token,
                'secret-key'  => $this->secret_key,
            ],
            'body' => json_encode([
                'postbackUrl'    => $postbackUrl,
                'webhookSecret' => $webhookSecret
            ])
        ]);
    }

    public function fetch_store_settings() {      
        
       

        if (empty($this->access_token) || empty($this->secret_key)) {
            return false;
        }
        
        $endpoint = 'integrations/woocommerce/store/key/' . $this->access_token;
        
        $response = $this->api_request_get($endpoint);

        if ($response && !isset($response['error'])) {
            
            $this->update_store_settings($response);

            return true;
        } 
        
        return false;
    }
    
    private function update_store_settings($settings) {
        $this->store_settings = $settings;
        
        // Mapeia os campos do JSON para as propriedades da classe
        if (isset($settings['paymentMethods'])) {
            $this->payment_methods = $settings['paymentMethods'];
        }
        
        if (isset($settings['woocommerceStoreName'])) {
            $this->woocommerce_store_name = $settings['woocommerceStoreName'];
        }
        
        if (isset($settings['redirectUrl'])) {
            $this->redirect_url = $settings['redirectUrl'];
        }
        
        if (isset($settings['taxAssume'])) {
            $this->tax_assume = $settings['taxAssume'];
        }
        
        if (isset($settings['id'])) {
            $this->store_id = $settings['id'];
        }

        if(isset($settings['sellerId'])){
            $this->seller_id = $settings['sellerId'];
        }
        
        // Log para debug
        $this->log('Configurações da loja atualizadas: ' . print_r($this->get_store_settings_summary(), true));
    }

    // RETORNA O RESUMO DA CONFIGURAÇÃO DA LOJA
    public function get_store_settings_summary() {
        return array(
            'payment_methods' => $this->payment_methods,
            'store_name' => $this->woocommerce_store_name,
            'tax_assume' => $this->tax_assume,
            'store_id' => $this->store_id,
        );
    }

    private function process_card_payment($order) {
        echo '<p>Processando pagamento com cartão...</p>';
        
        // Monta dados para API
        $card_data = $order->get_meta('_silapay_card_data', true);
        
        $data = array(
            'value' => floatval($order->get_total()),
            'dueDate' => date('Y-m-d', strtotime('+7 days')),
            'description' => 'Pedido #' . $order->get_id(),
            'installmentCount' => $card_data['installments'] ?? 1,
            'totalValue' => floatval($order->get_total()),
            'installmentValue' => floatval($order->get_total()) / ($card_data['installments'] ?? 1),
            'paymentMethod' => 'creditCard',
            'postalService' => false,
            'callback' => array(
                'successUrl' => $this->redirect_url ?? $this->get_return_url($order),
                'autoRedirect' => true
            ),
            'cardOwner' => array(
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'document' => $card_data['cpf'] ?? '',
            ),          
            'customer' => array(
            'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'document' => $card_data['cpf'] ?? '',
            'street' => $order->get_billing_address_1(),
            'complement' => $order->get_billing_address_2(),
            'postalCode' => $order->get_billing_postcode(),
            'neighborhood' => $order->get_meta('_billing_neighborhood', true),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'country' => $order->get_billing_country(),
            ),
            'card' => array(
            'cardHolderName' => $card_data['holder'] ?? '',
            'cardHolderDocument' => $card_data['cpf'] ?? '',
            'cardNumber' => str_replace(' ', '', $card_data['number'] ?? ''),
            'expirationMonth' => $card_data['card_expiration_month'],
            'expirationYear' => $card_data['card_expiration_year'],
            'cvv' => $card_data['cvc'] ?? '',
            ),
            'products' => array_values(array_map(function($item) {
            return array(
                'name' => $item->get_name(),
                'price' => floatval($item->get_total() / $item->get_quantity()),
                'total' => floatval($item->get_total()),
                'quantity' => intval($item->get_quantity()),
            );
            }, $order->get_items())),
        );
        
        // Faz chamada à API
        $response = $this->api_request('transactions', $data);
        
        if ($response && isset($response['transaction'])) {
            if ($response['transaction']["transactionId"] != NULL) {
                $this->complete_payment($order, $response);
            } else {
                echo '<p class="error">Erro ao processar cartão: ' . ($response['message'] ?? 'Erro desconhecido') . '</p>';
            }
        } else {
            echo '<p class="error">Falha na comunicação com a Silapay. Erro: '.$response["message"].'</p>';
        }
    }
    
    private function process_pix_payment($order) {
        echo '<p>Gerando PIX...</p> ';
        
        $data = array(
            'woocommerceStoreName' => preg_replace('#^https?://#', '', home_url()),
            'sellerId' => $this->access_token,
            'orderId' => (string) $order->get_id(),
            'customerId' => (string) $order->get_customer_id(),
            'cartId' => (string) $order->get_cart_hash(),
            'method' => 'pix',
            'feeChoice' => 'Seller',
            'items' => array_values(array_map(function($item) {
                return [
                    'id' => (string) $item->get_product_id(),
                    'name' => $item->get_name(),
                    'price' => (float) $item->get_total(),
                    'quantity' => (int) $item->get_quantity(),
                ];
            }, $order->get_items())),
            'paymentInfo' => array(
            'amount' => $order->get_total(),
            'currency' => 'BRL',
            ),
            'personalInfo' => array(
            'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone(),
            'document' => $order->get_meta('_billing_cpf', true),
            ),
            'redirectUrl' => $this->get_return_url($order),
        );
        
        $response = $this->api_request('integrations/woocommerce/pay/pix', $data);
        
        if ($response && isset($response['pixCopiaECola'])) {
            $this->show_pix_qrcode($order, $response);
        } else { 
            echo json_encode($response);
            echo '<p class="error">Erro ao gerar PIX: ' . ($response['message'] === "Validation failed" ? "Verifique as informações fornecidas para o gateway." : $response['message'] ?? 'Erro desconhecido') . '</p>';
        }
    }
    
    private function process_boleto_payment($order) {
        echo '<p>Gerando boleto...</p>';
        
        $boleto_data = $order->get_meta('_silapay_boleto_data', true);
        
        $data = array(
            'order_id' => $order->get_id(),
            'amount' => $order->get_total(),
            'currency' => 'BRL',
            'payment_method' => 'boleto',
            'customer' => array(
                'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $order->get_billing_email(),
                'document' => $boleto_data['document'] ?? '',
            ),
            'return_url' => $this->get_return_url($order),
        );
        
        $response = $this->api_request('/payments/create', $data);
        
        if ($response && isset($response['boleto'])) {
            $this->show_boleto($order, $response);
        } else {
            
            echo '<p class="error">Erro ao gerar boleto:  ' . ($response['message'] ?? 'Erro desconhecido') . '</p>';
        }
    }
    
    private function show_pix_qrcode($order, $response) {
        $pix = $response['pixCopiaECola'];
        
        echo '<div class="silapay-pix-container" style="text-align: center; padding: 20px;">';
        echo '<h4>Pague com PIX</h4>';

        $expires_in = 900; // 15 minutos
        ?>
        
        <?php
        
        // QR Code
        if ($pix !== NULL) {
            echo '<img src="https://api.qrserver.com/v1/create-qr-code/?size=512x512&data=' . esc_url($pix) . '" alt="QR Code PIX" style="max-width: 250px; border: 1px solid #ddd; padding: 10px;">';
        } else {
            echo '<div style="background: #f5f5f5; padding: 20px; display: inline-block;">';
            echo '<p><strong>QR Code não disponível</strong></p>';
            echo '</div>';
        }
        
        // Código PIX (copia e cola)
        if ($pix !== NULL) {
            echo '<div style="margin: 20px 0;">';
            echo '<p><strong>Código PIX:</strong></p>';
            echo '<textarea readonly style="width: 100%; height: 60px; padding: 10px; font-family: monospace;">' . esc_textarea($pix) . '</textarea>';
            
            echo '</div>';
        }

        // Timer
      
        echo '<div id="pix-timer" style="color: #d32f2f; font-weight: bold;"></div>';
        
        echo '</div>';
        
        // Instruções
        echo '<div align="center" style="background: #f0f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        echo '<p style="text-align: center;">1. Abra o app do seu banco</p>';
        echo '<p style="text-align: center;">2. Escaneie o QR Code ou cole o código PIX</p>';
        echo '<p style="text-align: center;">3. Confirme o pagamento</p>';
        echo '</div>';
        
       
        
      ?>
        <script>
        // Timer
        var endTime = new Date().getTime() + (<?php echo $expires_in; ?> * 1000);
        
        function updateTimer() {
            var now = new Date().getTime();
            var distance = endTime - now;
            
            if (distance < 0) {
                document.getElementById('pix-timer').innerHTML = 'QR Code expirado!';
                return;
            }
            
            var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            var seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('pix-timer').innerHTML = 
                'Tempo restante: ' + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            
            setTimeout(updateTimer, 1000);
        }
        
     
        // Verifica status a cada 5 segundos
        function checkPaymentStatus() {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'silapay_check_status',
                    order_id: <?php echo $order->get_id(); ?>,
                    nonce: '<?php echo wp_create_nonce('silapay_status_' . $order->get_id()); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        if(response.data.status.toLowerCase() === 'paid'){
                            window.location.replace('<?php echo $this->get_return_url($order); ?>');
                        }
                    }
                }
            });
            
            setTimeout(checkPaymentStatus, 5000);
        }
        
        // Inicia
        updateTimer();
        checkPaymentStatus();
        </script>
      <?php
        
    }
    
    private function show_boleto($order, $response) {
        $boleto = $response['boleto'];
        
        echo '<div class="silapay-boleto-container" style="text-align: center; padding: 20px;">';
        echo '<h4>Boleto Bancário</h4>';
        
        // Linha digitável
        if (!empty($boleto['digitable_line'])) {
            echo '<div style="background: #fff; padding: 15px; border: 1px solid #ddd; margin: 20px 0;">';
            echo '<p><strong>Linha Digitável:</strong></p>';
            echo '<p style="font-family: monospace; font-size: 18px; letter-spacing: 1px;">' . esc_html($boleto['digitable_line']) . '</p>';
            echo '</div>';
        }
        
        // Código de barras
        if (!empty($boleto['barcode'])) {
            echo '<div style="margin: 20px 0;">';
            echo '<p><strong>Código de Barras:</strong></p>';
            echo '<p style="font-family: monospace; font-size: 14px;">' . esc_html($boleto['barcode']) . '</p>';
            echo '</div>';
        }
        
        // Link para PDF
        if (!empty($boleto['pdf_url'])) {
            echo '<div style="margin: 20px 0;">';
            echo '<a href="' . esc_url($boleto['pdf_url']) . '" target="_blank" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none;">';
            echo '📄 Baixar Boleto (PDF)';
            echo '</a>';
            echo '</div>';
        }
        
        // Informações
        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: left; display: inline-block;">';
        echo '<p><strong>Vencimento:</strong> ' . ($boleto['due_date'] ?? 'Consulte o boleto') . '</p>';
        echo '<p><strong>Valor:</strong> R$ ' . number_format($order->get_total(), 2, ',', '.') . '</p>';
        echo '<p><strong>Beneficiário:</strong> Silapay</p>';
        echo '</div>';
        
        // Instruções
        echo '<div style="margin-top: 30px;">';
        echo '<h5>Como pagar:</h5>';
        echo '<ol style="text-align: left; display: inline-block;">';
        echo '<li>Imprima o boleto ou pague via internet banking</li>';
        echo '<li>O pagamento pode levar até 3 dias úteis para ser confirmado</li>';
        echo '<li>Após o pagamento, seu pedido será processado automaticamente</li>';
        echo '</ol>';
        echo '</div>';
        
        echo '</div>';
        
        // Verifica status periodicamente
        ?>
        <script>
        function checkBoletoStatus() {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'silapay_check_status',
                    order_id: <?php echo $order->get_id(); ?>,
                    nonce: '<?php echo wp_create_nonce('silapay_status_' . $order->get_id()); ?>'
                },
                success: function(response) {
                    if (response.success && response.data.status === 'paid') {
                        window.location.href = '<?php echo $this->get_return_url($order); ?>';
                    }
                }
            });
            
            setTimeout(checkBoletoStatus, 10000); // A cada 10 segundos
        }
        
        checkBoletoStatus();
        </script>
        <?php
    }
    
    private function complete_payment($order, $response) {
        $order->add_order_note('Pagamento Silapay aprovado. ID: ' . ($response['id'] ?? ''));
        $order->payment_complete($response['id'] ?? '');
        
        echo '<p class="success" style="color: #4bb543;">Pagamento aprovado! Redirecionaremos você em 5 segundos.</p>';
        echo '<script>setTimeout(function() { window.location.href = "' . esc_url($this->get_return_url($order)) . '"; }, 5000);</script>';
    }

    private function api_request_get($endpoint) {
        if (empty($this->access_token) || empty($this->secret_key)) {
            return false;
        }
        
        $url = $this->api_url . $endpoint;
        $args = array(
            'method'  => 'GET',
            'timeout' => 30,
            'headers' => array(
                'api-key' => $this->access_token,
                'secret-key'  => $this->secret_key,
                'Content-Type'  => 'application/json',
            ),
        );

        
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->log('API GET Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $this->log('API GET Response: ' . $body);
        
        return $data;
    }
    
    private function api_request($endpoint, $data) {
        if (empty($this->access_token) || empty($this->secret_key)) {
            error_log('Silapay: Access Token ou Secret Key não configurados');
            return false;
        }
        
        $url = $this->api_url . $endpoint;
        $args = array(
            'method'  => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Content-Type'  => 'application/json',
                'api-key' => $this->access_token,
                'secret-key'  => $this->secret_key,
            ),
            'body' => json_encode($data),
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            error_log('Silapay API Error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }   

    
    public function is_available() {
        if (empty($this->store_settings)) {
            $this->fetch_store_settings();
        }

        return parent::is_available()
            && !empty($this->access_token)
            && !empty($this->secret_key);
    }

    function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Silapay: ' . $message);
        }
        
        // Opcional: salvar em arquivo próprio
        $log_file = WP_CONTENT_DIR . '/silapay-debug.log';
        $timestamp = current_time('mysql');
        file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
    }
}

// Handler AJAX para verificar status
add_action('wp_ajax_silapay_check_status', 'silapay_check_status_handler');
add_action('wp_ajax_nopriv_silapay_check_status', 'silapay_check_status_handler');

function silapay_check_status_handler() {

    if (!isset($_POST['order_id']) || !isset($_POST['nonce'])) {
        wp_send_json_error(['message' => 'Dados inválidos']);
    }

    $order_id = intval($_POST['order_id']);
    $nonce    = sanitize_text_field($_POST['nonce']);

    if (!wp_verify_nonce($nonce, 'silapay_status_' . $order_id)) {
        wp_send_json_error(['message' => 'Nonce inválido']);
    }

    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Pedido não encontrado']);
    }

    // Pega gateway
    $gateways = WC()->payment_gateways->payment_gateways();
    if (!isset($gateways['silapay_checkout'])) {
        wp_send_json_error(['message' => 'Gateway não encontrado']);
    }

    $gateway = $gateways['silapay_checkout'];

    // Monta endpoint
    $endpoint = 'integrations/woocommerce/order/' . $order_id;    
  
    $url = $gateway->api_url . $endpoint;

    $args = array(
        'method'  => 'GET',
        'timeout' => 20,
        'headers' => array(
            'Content-Type' => 'application/json',
            'api-key'      => $gateway->access_token,
            'secret-key'   => $gateway->secret_key,
        ),
    );

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error([
            'message' => $response->get_error_message()
        ]);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!$data) {
        wp_send_json_error(['message' => 'Resposta inválida da API']);
    }  
     


    $transaction_status = $data['status'] ?? 'pending';

    if ($transaction_status === 'paid') {

        if ($order->get_status() !== 'processing' && $order->get_status() !== 'completed') {
            $order->payment_complete();
            $order->add_order_note('Pagamento confirmado via API Silapay.');
        }

        wp_send_json_success(['status' => 'paid']);

    } elseif ($transaction_status === 'failed') {

        $order->update_status('failed', 'Pagamento recusado pela Silapay.');
        wp_send_json_success(['status' => 'failed']);

    } else {
        wp_send_json_success(['status' => 'pending']);
    }
}

