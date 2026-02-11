/**
 * Silapay Checkout - Versão Simplificada
 * Esta versão funciona sem complexidades
 */

// Aguarda tudo carregar
window.addEventListener('load', function() {
    console.log('🚀 Silapay: Iniciando...');
    
    // Pequeno delay para garantir
    setTimeout(initSilapay, 500);
});

function initSilapay() {
    console.log('🔧 Silapay: Inicializando...');
    
    // Verifica dependências CRÍTICAS
    if (typeof jQuery === 'undefined') {
        console.error('❌ jQuery não carregado');
        return;
    }
    
    if (typeof SilaPayTransparentCheckout === 'undefined') {
        console.error('❌ SDK Silapay não carregado');
        return;
    }
    
    if (typeof silapay_params === 'undefined') {
        console.warn('⚠️ silapay_params não definido, usando fallback');
    }
    
    const $ = jQuery;
    
    // 1. ENCONTRA OS ELEMENTOS
    const $openBtn = $('#open-silapay-checkout');
    const $modal = $('#silapay-checkout-modal');
    const $container = $('#silapay-checkout-container');
    const $closeBtn = $('.silapay-modal-close');
    const $overlay = $('.silapay-modal-overlay');
    const $placeOrderBtn = $('#place_order');
    const $paymentMethods = $('input[name="payment_method"]');
    
    console.log('🔍 Elementos encontrados:', {
        botao: $openBtn.length > 0,
        modal: $modal.length > 0,
        container: $container.length > 0
    });
    
    if (!$openBtn.length) {
        console.error('❌ Botão Silapay não encontrado no HTML');
        return;
    }
    
    // 2. FUNÇÃO PARA ABRIR CHECKOUT (GLOBAL)
    window.silapayOpenModal = function() {
        console.log('🎯 Abrindo checkout Silapay...');
        
        // Verifica carrinho
        let total = 0;
        if (silapay_params && silapay_params.cart_data) {
            total = parseFloat(silapay_params.cart_data.total) || 0;
        }
        
        if (total <= 0) {
            alert('Seu carrinho está vazio');
            return;
        }
        
        // Mostra modal
        $modal.fadeIn(300);
        $('body').addClass('silapay-modal-open');
        
        // Limpa container
        $container.html('<div class="silapay-loading">Carregando checkout...</div>');
        
        // Cria checkout após pequeno delay
        setTimeout(function() {
            try {
                // Dados do carrinho
                const cartData = {
                    id: 'cart-' + Date.now(),
                    amountTotal: total,
                    currency: 'BRL',
                    items: (silapay_params && silapay_params.cart_data.items) || [],
                    paymentMethods: ['creditCard', 'pix', 'billet']
                };
                
                console.log('🛒 Dados do carrinho:', cartData);
                
                // Configuração
                const config = {
                    cart: cartData,
                    backendUrl: (silapay_params && silapay_params.backend_url) || 
                               window.location.origin + '/wc-api/silapay_backend/',
                    onPaymentSuccess: function(data) {
                        console.log('✅ Pagamento aprovado!', data);
                        
                        // Redireciona para página de agradecimento
                        if (data.order_id) {
                            let redirectUrl = '';
                            
                            if (silapay_params && silapay_params.order_received_url) {
                                redirectUrl = silapay_params.order_received_url + data.order_id + 
                                             '/?key=' + (data.transaction_id || data.order_id);
                            } else if (typeof wc_checkout_params !== 'undefined') {
                                redirectUrl = wc_checkout_params.order_received_url.replace('%order%', data.order_id);
                            } else {
                                redirectUrl = window.location.origin + '/checkout/order-received/' + data.order_id + '/';
                            }
                            
                            window.location.href = redirectUrl;
                        }
                    },
                    onPaymentError: function(error) {
                        console.error('❌ Erro no pagamento:', error);
                        alert('Erro no pagamento: ' + (error.message || 'Tente novamente'));
                    },
                    onCheckoutClose: function() {
                        console.log('🔒 Checkout fechado');
                        $modal.fadeOut(300);
                        $('body').removeClass('silapay-modal-open');
                    }
                };
                
                // Cria o checkout
                const checkout = SilaPayTransparentCheckout.createTransparentCheckout(
                    '#silapay-checkout-container',
                    config
                );
                
                console.log('🎉 Checkout criado com sucesso!');
                
                // Salva referência para fechar depois
                window.silapayCurrentCheckout = checkout;
                
            } catch (error) {
                console.error('💥 Erro ao criar checkout:', error);
                $container.html(`
                    <div style="padding: 40px; text-align: center; color: #e74c3c;">
                        <h3>Erro ao carregar checkout</h3>
                        <p>${error.message}</p>
                        <button onclick="location.reload()" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Tentar Novamente
                        </button>
                    </div>
                `);
            }
        }, 100);
    };
    
    // 3. FUNÇÃO PARA FECHAR (GLOBAL)
    window.silapayCloseModal = function() {
        console.log('🔒 Fechando checkout...');
        $modal.fadeOut(300);
        $('body').removeClass('silapay-modal-open');
        
        // Destroi checkout se existir
        if (window.silapayCurrentCheckout && typeof window.silapayCurrentCheckout.destroy === 'function') {
            try {
                window.silapayCurrentCheckout.destroy();
            } catch (e) {
                console.warn('Erro ao destruir checkout:', e);
            }
        }
    };
    
    // 4. CONFIGURA EVENTOS SIMPLES
    $openBtn.on('click', function(e) {
        e.preventDefault();
        window.silapayOpenModal();
    });
    
    $closeBtn.on('click', window.silapayCloseModal);
    $overlay.on('click', window.silapayCloseModal);
    
    // Fecha com ESC
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $modal.is(':visible')) {
            window.silapayCloseModal();
        }
    });
    
    // Controla visibilidade dos botões
    $paymentMethods.on('change', function() {
        if ($(this).val() === 'silapay') {
            $placeOrderBtn.hide();
            $openBtn.show();
        } else {
            $placeOrderBtn.show();
            $openBtn.hide();
        }
    });
    
    // Verifica seleção inicial
    if ($paymentMethods.filter('[value="silapay"]').is(':checked')) {
        $placeOrderBtn.hide();
        $openBtn.show();
    }
    
    // Previne envio do formulário quando Silapay está selecionado
    $('form.checkout').on('submit', function(e) {
        if ($('input[name="payment_method"]:checked').val() === 'silapay') {
            e.preventDefault();
            window.silapayOpenModal();
            return false;
        }
    });
    
    console.log('✅ Silapay inicializado com sucesso!');
    console.log('🎯 Digite no console: silapayOpenModal()');
    
    // Exibe mensagem de sucesso no console
    setTimeout(function() {
        console.log('=====================================');
        console.log('SILAPAY PRONTO PARA USO!');
        console.log('Para testar, digite: silapayOpenModal()');
        console.log('Para fechar: silapayCloseModal()');
        console.log('=====================================');
    }, 1000);
}