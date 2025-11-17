/**
 * Plugin Name: Mini ERP WC - Produtos, Clientes e Vendas (WooCommerce Integrado)
 * Description: Mini ERP integrado ao WooCommerce. Possui módulos de Produtos, Clientes (CRM Central) e um Módulo PDV/Caixa com seleção de clientes e métodos de pagamento.
 * Version: 1.7.0
 * Author: Lumingues
 * Text Domain: minierpwc
 */

if (!defined('ABSPATH')) exit;

// Requer WooCommerce
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function(){
        echo '<div class="notice notice-error"><p>Mini ERP WC: WooCommerce não está ativo. Por favor ative o plugin WooCommerce.</p></div>';
    });
    return;
}

// Verifica se o WP Customer CRM está ativo e define a constante da tabela CRM.
if (!defined('WPCRM_TABLE')) {
    define('WPCRM_TABLE', $GLOBALS['wpdb']->prefix . 'wpcrm_customers'); // Define o nome da tabela do CRM
}
define('MINI_ERP_WC_NONCE', 'mini_erp_wc_nonce');
define('MPDV_DEFAULT_PAYMENT_DAYS', 7); // Padrão de dias para boleto

// ====================================================================
// FUNÇÕES PRINCIPAIS PROTEGIDAS CONTRA REDECLARAÇÃO
// ====================================================================

add_action('admin_menu', 'mini_erp_wc_admin_menu');
if (!function_exists('mini_erp_wc_admin_menu')) {
    function mini_erp_wc_admin_menu() {
        add_menu_page('Mini ERP WC', 'Mini ERP WC', 'manage_woocommerce', 'mini-erp-wc', 'mini_erp_wc_admin_page', 'dashicons-portfolio', 56);
    }
}

add_action('admin_enqueue_scripts', 'mini_erp_wc_admin_assets');
if (!function_exists('mini_erp_wc_admin_assets')) {
    function mini_erp_wc_admin_assets($hook) {
        if ($hook !== 'toplevel_page_mini-erp-wc') return;
        wp_enqueue_script('jquery');
        
        // Dados de configuração para o novo PDV
        wp_localize_script('jquery', 'mini_erp_ajax', array(
            'ajaxurl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('mini_erp_wc_nonce'),
            'default_payment_days' => MPDV_DEFAULT_PAYMENT_DAYS,
        ));
    }
}

add_action('admin_head', 'mini_erp_wc_inline_assets');
if (!function_exists('mini_erp_wc_inline_assets')) {
    function mini_erp_wc_inline_assets() {
        $screen = get_current_screen();
        if ($screen->id !== 'toplevel_page_mini-erp-wc') return;
        ?>
        <style>
        /* Estilos Existentes */
        .mini-erp-wrap{font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial; padding:20px}
        .mini-erp-tabs{display:flex;gap:10px;margin-bottom:18px}
        .mini-erp-tab{padding:8px 14px;border-radius:10px;background:linear-gradient(135deg,#ece9e6,#ffffff);cursor:pointer;box-shadow:0 6px 18px rgba(16,24,40,.06);}
        .mini-erp-tab.active{background:linear-gradient(90deg,#6a11cb 0%,#2575fc 100%);color:#fff}
        .mini-erp-actions{margin-bottom:12px; display:flex; gap: 10px; align-items: center;}
        .mini-erp-table{width:100%;border-collapse:collapse;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(2,6,23,.08);margin-bottom:18px}
        .mini-erp-table thead th{padding:12px 10px;text-align:left;background:linear-gradient(90deg,#ff9a9e 0%,#fad0c4 100%);color:#111;font-weight:600}
        .mini-erp-table tbody td{padding:11px;border-bottom:1px solid rgba(0,0,0,.04);background:linear-gradient(180deg,#ffffff,#fbfbff)}
        .mini-erp-table tbody tr:hover td{transform:translateX(4px);transition:all .15s}
        .mini-erp-btn{display:inline-block;padding:8px 12px;border-radius:8px;background:#111;color:#fff;text-decoration:none;margin-right:8px}
        .mini-erp-small{font-size:13px;padding:6px 10px;border-radius:8px}
        .mini-erp-modal{position:fixed;left:0;top:0;width:100%;height:100%;display:none;align-items:center;justify-content:center;background:rgba(2,6,23,.45);z-index:9999}
        .mini-erp-modal.open{display:flex}
        .mini-erp-modal .card{width:820px;max-width:96%;background:#fff;border-radius:12px;padding:18px;box-shadow:0 20px 50px rgba(2,6,23,.3);position:relative}
        .mini-erp-modal .close-x{position:absolute;right:12px;top:12px;width:36px;height:36px;border-radius:8px;border:none;background:transparent;color:#333;font-size:20px;cursor:pointer}
        .mini-erp-form .row{display:flex;gap:10px;margin-bottom:10px}
        .mini-erp-form input[type=text],.mini-erp-form input[type=number],.mini-erp-form textarea,.mini-erp-form select,.mini-erp-form input[type=email]{flex:1;padding:8px;border-radius:8px;border:1px solid #e6e9ef}
        .mini-erp-ops{display:flex;gap:8px;justify-content:flex-end;margin-top:12px}
        .mini-erp-danger{background:#f43f5e;color:#fff;padding:8px 10px;border-radius:8px;border:none}
        .mini-erp-primary{background:linear-gradient(90deg,#06b6d4,#2563eb);color:#fff;padding:8px 10px;border-radius:8px;border:none}
        .mini-erp-summary { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 250px; display: block; }
        .wpcrm-origin-badge{padding:4px 8px; border-radius:5px; color:#fff; font-size:12px; font-weight:bold; display:inline-block;}
        .wpcrm-origin-pdv{background:#e74c3c;}
        .wpcrm-origin-erp{background:#2ecc71;}
        .wpcrm-origin-woocommerce{background:#3498db;}
        
        /* PDV E CAIXA */
        #mpdv-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            min-height: 70vh;
        }
        #mpdv-left, #mpdv-right {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        #mpdv-left { display: flex; flex-direction: column; }
        #mpdv-cart-list {
            list-style: none;
            padding: 0;
            max-height: 40vh;
            overflow-y: auto;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }
        #mpdv-cart-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed #f0f0f0;
            font-size: 14px;
        }
        .mpdv-product-search-group {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }
        #mpdv-product-id {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        #mpdv-quantity {
             width: 70px;
             padding: 10px;
             border: 1px solid #ccc;
             border-radius: 6px;
             text-align: center;
        }
        #mpdv-total-display {
            background: linear-gradient(135deg, #4CAF50 0%, #81C784 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
        }
        #mpdv-total-value { font-size: 2.5em; font-weight: bold; display: block; }
        .mpdv-section-title { font-size: 1.2em; border-bottom: 1px solid #eee; padding-bottom: 8px; margin-top: 20px; }
        .mpdv-payment-group select, .mpdv-payment-group input {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
            margin-top: 5px;
        }
        .mpdv-info-box { background: #e0f7fa; padding: 10px; border-radius: 4px; margin-top: 10px; font-size: 0.9em; border-left: 3px solid #00BCD4; }
        .mpdv-checkout-buttons button { width: 100%; margin-top: 10px; }

        /* NOVO: Estilo da lixeira */
        .delete-item-btn { 
            background: none;
            border: none;
            color: #e74c3c;
            cursor: pointer;
            font-size: 1.1em;
            padding: 0 5px;
            opacity: 0.8;
            transition: opacity 0.2s;
            line-height: 1;
            margin-left: 10px;
        }
        .delete-item-btn:hover {
            opacity: 1;
        }
        /* Ocultar Lixeira na impressão */
        @media print {
            .delete-item-btn { display: none !important; }
        }
        </style>

        <script>
        (function($){
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce = '<?php echo wp_create_nonce(MINI_ERP_WC_NONCE); ?>';
            var crmUrl = '<?php echo esc_url(admin_url('admin.php?page=wp-customer-crm')); ?>';
            var cart = [];
            var currentCustomer = null;
            var defaultPaymentDays = mini_erp_ajax.default_payment_days;
            
            // VARIÁVEIS PARA A LÓGICA DE DEBOUNCING (1 SEGUNDO)
            var typingTimer;                
            const doneTypingInterval = 1000; // 1 segundo

            // --- PDV FUNCTIONS ---
            
            function updateCartUI() {
                var total = 0;
                var subtotal = 0;
                var totalDiscount = 0;
                var $list = $('#mpdv-cart-list');
                $list.empty();

                $.each(cart, function(index, item) {
                    var itemPrice = parseFloat(item.price);
                    var itemQty = parseInt(item.quantity);
                    var itemSubtotal = itemPrice * itemQty;
                    
                    // Aplica desconto (se houver no produto)
                    var itemDiscountPercent = parseFloat(item.discount || 0);
                    var itemDiscountAmount = itemSubtotal * (itemDiscountPercent / 100);
                    
                    var itemTotal = itemSubtotal - itemDiscountAmount;

                    subtotal += itemSubtotal;
                    totalDiscount += itemDiscountAmount;
                    total += itemTotal;

                    var discountDisplay = itemDiscountAmount > 0 ? ` (-R$ ${itemDiscountAmount.toFixed(2).replace('.', ',')})` : '';
                    
                    // NOVO: Botão de Lixeira
                    var deleteBtn = `<button class="delete-item-btn" data-index="${index}" title="Remover item"><span class="dashicons dashicons-trash"></span></button>`;

                    var listItem = `
                        <li>
                            <span>${item.name} (x${itemQty})</span>
                            <span style="font-weight: bold;">R$ ${itemTotal.toFixed(2).replace('.', ',')}${discountDisplay}</span>
                            ${deleteBtn}
                        </li>
                    `;
                    $list.append(listItem);
                });
                
                // NOVO: Handler de exclusão
                $('.delete-item-btn').on('click', function(e) {
                    e.preventDefault();
                    var index = $(this).data('index');
                    if (confirm('Deseja realmente remover este item do carrinho?')) {
                        cart.splice(index, 1);
                        updateCartUI();
                        $('#mpdv-product-id').focus();
                    }
                });

                $('#mpdv-total-value').text('R$ ' + total.toFixed(2).replace('.', ','));
                $('#mpdv-customer-discount-info').html(totalDiscount > 0 ? `Desconto Aplicado: R$ ${totalDiscount.toFixed(2).replace('.', ',')}` : '');
                
                // Habilita/Desabilita botão de checkout
                $('#mpdv-checkout-btn').prop('disabled', total <= 0);
            }

            function fetchProductAndAddToCart(identifier, quantity) {
                $.post(ajaxUrl, {
                    action: 'mini_erp_wc_get_product_for_pos',
                    nonce: nonce,
                    identifier: identifier
                }, function(response) {
                    if (response.success && response.data) {
                        var product = response.data;
                        product.quantity = quantity;

                        // Verifica se o produto já está no carrinho
                        var existingItem = cart.find(item => item.sku === product.sku);
                        if (existingItem) {
                            existingItem.quantity += quantity;
                        } else {
                            // Aplica o desconto do cliente se houver (o desconto do produto já vem no AJAX)
                            if (currentCustomer && currentCustomer.discount_percent > 0) {
                                product.discount = parseFloat(currentCustomer.discount_percent || 0);
                            }
                            cart.push(product);
                        }
                        
                        $('#mpdv-product-id').val('');
                        updateCartUI();
                        $('#mpdv-quantity').val(1);
                        $('#mpdv-product-id').focus();

                    } else {
                        // Feedback visual de erro
                        $('#mpdv-product-id').css('background-color', '#f8d7da').animate({backgroundColor: 'transparent'}, 500);
                        alert('Produto não encontrado! Tente buscar por SKU.');
                    }
                }, 'json').fail(function() {
                    $('#mpdv-product-id').css('background-color', '#f8d7da').animate({backgroundColor: 'transparent'}, 500);
                    alert('Erro de comunicação ao buscar produto.');
                });
            }

            // Função para processar a adição (chamada pelo Debouncing ou ENTER)
            function processIdentifier() {
                var identifier = $('#mpdv-product-id').val().trim();
                var quantity = parseInt($('#mpdv-quantity').val()) || 1;
                
                if (identifier && quantity > 0) {
                    fetchProductAndAddToCart(identifier, quantity);
                }
            }

            function fetchCustomerAndApply() {
                var identifier = $('#mpdv-customer-id').val();
                if (!identifier) {
                    currentCustomer = null;
                    $('#mpdv-customer-info').html('Nenhum cliente selecionado.').removeClass('mpdv-info-box').css('background', '#fff');
                    updateCartUI(); // Remove descontos se o cliente for removido
                    return;
                }

                $.post(ajaxUrl, {
                    action: 'mini_erp_wc_get_customer_for_pos',
                    nonce: nonce,
                    identifier: identifier
                }, function(response) {
                    if (response.success && response.data) {
                        currentCustomer = response.data;
                        $('#mpdv-customer-info').html(`Cliente: <strong>${currentCustomer.name}</strong> (Desconto: ${currentCustomer.discount_percent}%)`).addClass('mpdv-info-box').css('background', '#e0f7fa');
                        
                        // Aplica o desconto do cliente nos itens do carrinho
                        cart.forEach(function(item) {
                            item.discount = parseFloat(currentCustomer.discount_percent || 0);
                        });
                        updateCartUI();
                    } else {
                        currentCustomer = null;
                        $('#mpdv-customer-info').html('Cliente não encontrado no CRM.').removeClass('mpdv-info-box').css('background', '#fbeae5');
                        cart.forEach(function(item) { item.discount = 0; });
                        updateCartUI();
                    }
                }, 'json');
            }
            
            // --- Handlers PDV ---

            $(document).on('click', '#mpdv-product-add-btn', function() { // Botão de Adicionar (AGORA REMOVIDO DO HTML)
                processIdentifier();
            });

            // NOVO: Busca de Produto com Debouncing (1s)
            $(document).on('keyup', '#mpdv-product-id', function(e) {
                var $input = $(this);
                
                // Se for ENTER (key 13), cancela o temporizador e processa imediatamente
                if (e.which == 13) {
                    clearTimeout(typingTimer);
                    processIdentifier();
                    return;
                }
                
                clearTimeout(typingTimer);
                
                if ($input.val()) { 
                    typingTimer = setTimeout(function() {
                        processIdentifier();
                    }, doneTypingInterval);
                }
            });

            // Keydown: Limpa o temporizador para evitar processamento durante a digitação
            $(document).on('keydown', '#mpdv-product-id', function() {
                clearTimeout(typingTimer);
            });
            
            // Busca de Cliente (Enter)
            $(document).on('keypress', '#mpdv-customer-id', function(e) {
                if (e.which == 13) {
                    e.preventDefault();
                    fetchCustomerAndApply();
                }
            });
            
            // Mudança do método de pagamento
            $(document).on('change', '#mpdv-payment-method', function() {
                var method = $(this).val();
                $('#mpdv-days-group').toggle(method === 'bacs'); // Mostra campo de dias apenas para Boleto (bacs)
            });
            
            // Finalizar Venda
            $(document).on('click', '#mpdv-checkout-btn', function() {
                if (cart.length === 0) {
                    alert('O carrinho está vazio.');
                    return;
                }
                
                var totalAmount = parseFloat($('#mpdv-total-value').text().replace('R$', '').replace(',', '.').trim());
                var paymentMethod = $('#mpdv-payment-method').val();
                var paymentDays = (paymentMethod === 'bacs') ? parseInt($('#mpdv-payment-days').val()) || defaultPaymentDays : 0;
                
                // Validação
                if (totalAmount <= 0) {
                    alert('O valor total deve ser positivo.');
                    return;
                }
                if (paymentMethod === 'bacs' && paymentDays <= 0) {
                    alert('Selecione um número de dias válido para o Boleto.');
                    return;
                }

                if (!confirm(`Confirmar venda de R$ ${totalAmount.toFixed(2).replace('.', ',')} via ${paymentMethod.toUpperCase()}?`)) {
                    return;
                }

                // Desativa o botão
                $(this).prop('disabled', true).text('Processando...');

                $.post(ajaxUrl, {
                    action: 'mini_erp_wc_finalize_sale',
                    nonce: nonce,
                    cart_items: JSON.stringify(cart),
                    total: totalAmount,
                    customer_cpf: currentCustomer ? currentCustomer.cpf : '',
                    payment_method: paymentMethod,
                    payment_days: paymentDays
                }, function(response) {
                    $('#mpdv-checkout-btn').prop('disabled', false).text('Finalizar Venda');
                    if (response.success) {
                        alert('Venda finalizada com sucesso! Pedido #' + response.data.order_id + ' criado no WooCommerce.');
                        
                        // Limpa o PDV
                        cart = [];
                        currentCustomer = null;
                        $('#mpdv-customer-id').val('');
                        $('#mpdv-quantity').val(1);
                        $('#mpdv-customer-info').html('Nenhum cliente selecionado.').removeClass('mpdv-info-box').css('background', '#fff');
                        updateCartUI();
                        // Se estiver na aba de pedidos, recarrega-a
                        if ($('.mini-erp-tab[data-tab="orders"]').hasClass('active')) {
                            window.loadOrdersTable();
                        }

                    } else {
                        alert('Erro ao finalizar venda: ' + (response.data || 'Erro desconhecido. Verifique o console.'));
                        console.error(response);
                    }
                }, 'json').fail(function() {
                    $('#mpdv-checkout-btn').prop('disabled', false).text('Finalizar Venda');
                    alert('Erro de comunicação ao finalizar venda.');
                });
            });
            
            // Inicializa a UI na carga
            $(document).on('click', '.mini-erp-tab[data-tab="pos"]', function() { updateCartUI(); });

            // --- END PDV HANDLERS ---
            
            // ... (Funções de Modal e Handlers de Tabela/Ações mantidas) ...

            // Funções de Modal - Mantidas
            function openModal(type,id){
                // Redireciona clientes para o CRM
                if (type === 'customer') {
                    if (id > 0) {
                        var crmId = $('button[data-type="customer"][data-id="' + id + '"]').closest('tr').data('crm-id');
                        window.location.href = crmUrl + '&action=edit&id=' + crmId;
                    } else {
                        window.location.href = crmUrl + '&action=new';
                    }
                    return;
                }
                
                var $modal = $('.mini-erp-modal');
                $modal.addClass('open');
                $modal.find('.card .body').html('<p>Carregando...</p>');
                $.post(ajaxUrl,{action:'mini_erp_wc_get',nonce:nonce,type:type,id:id},function(r){
                    if(!r.success){ $modal.find('.card .body').html('<p>Erro ao carregar: ' + (r.data || 'desconhecido') + '</p>'); return; }
                    $modal.find('.card .body').html(r.data.html);
                }, 'json').fail(function() {
                    $modal.find('.card .body').html('<p>Erro de comunicação ao carregar dados.</p>');
                });
            }
            function closeModal(){ $('.mini-erp-modal').removeClass('open'); }

            window.loadCustomersTable = function(originFilter) {
                $('#customers-table-container').html('<p>Carregando clientes do CRM Central...</p>');
                $.post(ajaxUrl, {
                    action: 'mini_erp_wc_get_customers_table',
                    nonce: nonce,
                    origin_filter: originFilter
                }, function(r) {
                    if (r.success) {
                        $('#customers-table-container').html(r.data.html);
                    } else {
                        $('#customers-table-container').html('<p>Erro ao carregar clientes do CRM: ' + (r.data || 'desconhecido') + '</p>');
                    }
                }, 'json').fail(function() {
                    $('#customers-table-container').html('<p>Erro de comunicação com o servidor ao carregar clientes.</p>');
                });
            }

            // NOVO: Função para carregar a tabela de pedidos via AJAX
            window.loadOrdersTable = function() {
                $('#orders-table-container').html('<p>Carregando pedidos...</p>');
                $.post(ajaxUrl, {
                    action: 'mini_erp_wc_get_orders_table',
                    nonce: nonce
                }, function(r) {
                    if (r.success) {
                        $('#orders-table-container').html(r.data.html);
                    } else {
                        $('#orders-table-container').html('<p>Erro ao carregar pedidos: ' + (r.data || 'desconhecido') + '</p>');
                    }
                }, 'json').fail(function() {
                    $('#orders-table-container').html('<p>Erro de comunicação com o servidor ao carregar pedidos.</p>');
                });
            }


            $(document).ready(function(){
                $('.mini-erp-tab').on('click',function(){
                    var t = $(this).data('tab');
                    $('.mini-erp-tab').removeClass('active');
                    $(this).addClass('active');
                    $('.mini-erp-section').hide();
                    $('#'+t).show();

                    // Recarrega a tabela de clientes quando a aba é ativada
                    if (t === 'customers') {
                        var currentFilter = $('#customer_origin_filter').val() || 'all';
                        window.loadCustomersTable(currentFilter);
                    }
                    
                    // Inicializa PDV na aba PDV
                    if (t === 'pos') {
                        updateCartUI(); // Reset visual
                    }

                    // NOVO: Recarrega a tabela de pedidos
                    if (t === 'orders') {
                        window.loadOrdersTable();
                    }
                });

                // Inicia na aba de PDV (Vendas)
                $('.mini-erp-tab[data-tab="pos"]').click();

                // Lógica para o filtro de clientes
                $(document).on('change', '#customer_origin_filter', function() {
                    window.loadCustomersTable($(this).val());
                });

                // Ação de abrir o modal ao clicar na célula ou botão "Ver"
                $(document).on('click','.cell-open, .mini-erp-small:not(.mini-erp-delete)',function(){
                    var type = $(this).data('type') || $(this).closest('td').data('type');
                    var id = $(this).data('id') || $(this).closest('td').data('id');
                    
                    if ($(this).hasClass('mini-erp-delete')) return;
                    if (type === 'customer') {
                        var crmId = $(this).closest('tr').data('crm-id');
                        if (crmId) {
                            window.location.href = crmUrl + '&action=edit&id=' + crmId;
                            return;
                        }
                    }
                    
                    if (type && id) {
                        openModal(type,id);
                    }
                });

                $(document).on('click','.mini-erp-modal .close-x',function(){ closeModal(); });
                $(document).on('click','.mini-erp-modal',function(e){ if(e.target.classList && e.target.classList.contains('mini-erp-modal')) closeModal(); });

                // Ação de ADICIONAR 
                $(document).on('click','.mini-erp-add',function(){ 
                    var type = $(this).data('type');
                    if (type === 'customer') {
                        window.location.href = crmUrl + '&action=new';
                    } else if (type === 'product') {
                        openModal(type, 0); 
                    } else {
                        // Novo Pedido não PDV (rascunho)
                        openModal(type, 0); 
                    }
                });

                // Ação de Salvar (Apenas para Produtos/Pedidos)
                $(document).on('click','.mini-erp-save',function(){
                    var form = $(this).closest('form');
                    var type = form.find('input[name="type"]').val();
                    
                    if (type === 'customer') {
                        alert('A edição de clientes foi movida para o CRM Central de Clientes.');
                        return;
                    }

                    var data = form.serializeArray();
                    data.push({name:'action',value:'mini_erp_wc_save'});
                    data.push({name:'nonce',value:nonce});
                    
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Salvando...');

                    $.post(ajaxUrl,data,function(r){
                        $btn.prop('disabled', false).text('Salvar');
                        if(r.success){  
                            alert('Salvo com sucesso'); 
                            closeModal(); 
                            // Recarrega a tabela correta
                            if(type === 'product' && $('.mini-erp-tab[data-tab="products"]').hasClass('active')) {
                                location.reload(); // Recarga mais fácil para produtos por enquanto.
                            } else if (type === 'order' && $('.mini-erp-tab[data-tab="orders"]').hasClass('active')) {
                                window.loadOrdersTable(); // Recarrega a tabela de pedidos
                            } else {
                                location.reload();
                            }
                        } else {
                            alert(r.data||'Erro ao salvar');
                        }
                    }, 'json').fail(function() {
                        $btn.prop('disabled', false).text('Salvar');
                        alert('Erro de comunicação com o servidor');
                    });
                });

                // Ação de Excluir/Cancelar
                $(document).on('click','.mini-erp-delete',function(){
                    var type = $(this).data('type');
                    
                    if (type === 'customer') {
                        alert('A exclusão de clientes foi movida para o CRM Central.');
                        return;
                    }

                    if(!confirm('Confirma ação de Exclusão/Cancelamento?')) return;
                    
                    var id = $(this).data('id');
                    var $btn = $(this);
                    $btn.prop('disabled', true).text('Processando...');

                    $.post(ajaxUrl,{action:'mini_erp_wc_delete',nonce:nonce,id:id,type:type},function(r){
                        $btn.prop('disabled', false).text('Excluir');
                        if(r.success){  
                            alert('Ação de Exclusão/Cancelamento concluída');  
                            if(type === 'product' && $('.mini-erp-tab[data-tab="products"]').hasClass('active')) {
                                location.reload(); // Recarga mais fácil para produtos por enquanto.
                            } else if (type === 'order' && $('.mini-erp-tab[data-tab="orders"]').hasClass('active')) {
                                window.loadOrdersTable(); // Recarrega a tabela de pedidos
                            } else {
                                location.reload();
                            }
                        } else {
                            alert(r.data||'Erro ao processar');
                        }
                    }, 'json').fail(function() {
                        $btn.prop('disabled', false).text('Excluir');
                        alert('Erro de comunicação com o servidor');
                    });
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}

// Admin page markup - Adicionado aba 'PDV'
if (!function_exists('mini_erp_wc_admin_page')) {
    function mini_erp_wc_admin_page(){
        if (!current_user_can('manage_woocommerce')) wp_die('Acesso negado');
        
        $wc_products_url = admin_url('edit.php?post_type=product');
        $wc_orders_url = admin_url('edit.php?post_type=shop_order');
        $crm_url = admin_url('admin.php?page=wp-customer-crm');
        
        ?>
        <div class="wrap mini-erp-wrap">
            <h1>Mini ERP WC - Integração WooCommerce</h1>
            <div class="mini-erp-tabs">
                <div class="mini-erp-tab" data-tab="pos">Ponto de Venda (PDV)</div>
                <div class="mini-erp-tab" data-tab="products">Produtos (WooCommerce)</div>
                <div class="mini-erp-tab" data-tab="customers">Clientes (CRM Central)</div>
                <div class="mini-erp-tab" data-tab="orders">Histórico de Pedidos</div>
            </div>

            <div class="mini-erp-section" id="pos" style="display:none">
                <h2>Frente de Caixa (PDV)</h2>
                <div id="mpdv-container">
                    <div id="mpdv-left">
                        <div class="mpdv-product-search-group">
                            <input type="text" id="mpdv-product-id" placeholder="SKU, ID ou Código de Barras">
                            <input type="number" id="mpdv-quantity" value="1" min="1" style="width: 70px;">
                            </div>
                        <ul id="mpdv-cart-list">
                            </ul>
                    </div>
                    <div id="mpdv-right">
                        <h3 class="mpdv-section-title">Cliente & Desconto</h3>
                        <div class="mpdv-customer-group">
                            <input type="text" id="mpdv-customer-id" placeholder="CPF/ID do Cliente (Enter p/ buscar)">
                            <div id="mpdv-customer-info" class="mpdv-info-box">Nenhum cliente selecionado.</div>
                            <div id="mpdv-customer-discount-info" style="font-weight: bold; color: #2ecc71; margin-top: 5px;"></div>
                        </div>

                        <div id="mpdv-total-display">
                            <span>TOTAL A PAGAR</span>
                            <span id="mpdv-total-value">R$ 0,00</span>
                        </div>

                        <h3 class="mpdv-section-title">Pagamento</h3>
                        <div class="mpdv-payment-group">
                            <label for="mpdv-payment-method">Método:</label>
                            <select id="mpdv-payment-method">
                                <option value="cod">Dinheiro</option>
                                <option value="bacs">Boleto</option>
                                <option value="stripe">Cartão (Simulado)</option>
                                <option value="pix">Pix (Simulado)</option>
                            </select>
                            <div id="mpdv-days-group" style="display: none;">
                                <label for="mpdv-payment-days">Dias para Vencimento do Boleto:</label>
                                <input type="number" id="mpdv-payment-days" value="<?php echo MPDV_DEFAULT_PAYMENT_DAYS; ?>" min="1">
                            </div>
                        </div>
                        
                        <div class="mpdv-checkout-buttons">
                            <button id="mpdv-checkout-btn" class="mini-erp-btn mini-erp-primary" disabled>Finalizar Venda</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mini-erp-section" id="products" style="display:none">
                <div class="mini-erp-actions">
                    <button class="mini-erp-btn mini-erp-add" data-type="product">+ Novo produto (WC)</button>
                    <a href="<?php echo esc_url($wc_products_url); ?>" class="mini-erp-btn" style="background:#007cba;">Ver todos no WC</a>
                </div>
                <?php mini_erp_wc_products_table(); ?>
            </div>

            <div class="mini-erp-section" id="customers" style="display:none">
                <div class="mini-erp-actions">
                    <a href="<?php echo esc_url($crm_url . '&action=new'); ?>" class="mini-erp-btn" style="background:#2ecc71;">+ Novo Cliente (Via CRM Central)</a>
                    <a href="<?php echo esc_url($crm_url); ?>" class="mini-erp-btn" style="background:#007cba;">Ir para o CRM Central de Clientes</a>
                    
                    <label for="customer_origin_filter">Filtrar por Origem:</label>
                    <select id="customer_origin_filter">
                        <option value="all">Todos (CRM Central)</option>
                        <option value="pdv">WPPDV</option>
                        <option value="erp">Mini ERP WC</option>
                        <option value="woocommerce">WooCommerce (Loja)</option>
                    </select>
                </div>
                <div id="customers-table-container">
                    <?php 
                    // Carregamento AJAX
                    echo mini_erp_wc_customers_table_from_crm('all');
                    ?>
                </div>
            </div>

            <div class="mini-erp-section" id="orders" style="display:none">
                <div class="mini-erp-actions">
                    <a href="<?php echo esc_url($wc_orders_url); ?>" class="mini-erp-btn" style="background:#007cba;">Ver todos no WC</a>
                </div>
                <div id="orders-table-container">
                    <p>Clique na aba "Histórico de Pedidos" para carregar os dados...</p>
                </div>
            </div>

            <div class="mini-erp-modal">
                <div class="card">
                    <button class="close-x">×</button>
                    <div class="body">Carregando...</div>
                </div>
            </div>
        </div>
        <?php
    }
}

// Products table
if (!function_exists('mini_erp_wc_products_table')) {
    // ... (Mantido o código existente)
    function mini_erp_wc_products_table(){
        if (!function_exists('wc_get_products')) return;

        $args = array('limit' => 200);
        $products = wc_get_products($args);
        echo '<table class="mini-erp-table">';
        echo '<thead><tr><th>SKU</th><th>Produto</th><th>Preço</th><th>Estoque</th><th>Ações</th></tr></thead><tbody>';
        foreach($products as $p){
            $sku = $p->get_sku();
            $name = $p->get_name();
            $price = $p->get_price() ? wc_price($p->get_price()) : 'Grátis'; 
            $stock = $p->managing_stock() ? $p->get_stock_quantity() : 'N/A';
            $id = $p->get_id();
            echo '<tr>';
            echo '<td class="cell-open" data-type="product" data-id="'.esc_attr($id).'">'.esc_html($sku).'</td>';
            echo '<td class="cell-open" data-type="product" data-id="'.esc_attr($id).'">'.esc_html($name).'</td>';
            echo '<td class="cell-open" data-type="product" data-id="'.esc_attr($id).'">'.strip_tags($price).'</td>';
            echo '<td class="cell-open" data-type="product" data-id="'.esc_attr($id).'">'.esc_html($stock).'</td>';
            echo '<td>';
            echo '<button class="mini-erp-small" data-type="product" data-id="'.esc_attr($id).'">Ver</button> ';
            echo '<button class="mini-erp-small mini-erp-danger mini-erp-delete" data-type="product" data-id="'.esc_attr($id).'">Excluir (move p/ lixo)</button>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
}

// NOVO AJAX: Busca produto por SKU/ID para o PDV
add_action('wp_ajax_mini_erp_wc_get_product_for_pos', 'mini_erp_wc_get_product_for_pos_callback');
if (!function_exists('mini_erp_wc_get_product_for_pos_callback')) {
    function mini_erp_wc_get_product_for_pos_callback() {
        check_ajax_referer(MINI_ERP_WC_NONCE, 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Permissão negada');

        $identifier = sanitize_text_field($_POST['identifier'] ?? '');
        if (empty($identifier)) wp_send_json_error('Identificador de produto vazio.');

        $product_id = wc_get_product_id_by_sku($identifier);
        
        // Tenta buscar por ID se o SKU não funcionar e for numérico
        if (!$product_id && is_numeric($identifier)) {
             $product_id = intval($identifier);
        }

        $product = wc_get_product($product_id);

        if (!$product || !$product->exists() || $product->get_status() !== 'publish') {
            wp_send_json_error('Produto não encontrado ou indisponível.');
        }

        $product_data = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => floatval($product->get_price()),
            'discount' => floatval($product->get_meta('_pdv_discount') ?: 0), // Assumindo meta do PDV
            'stock_quantity' => $product->get_stock_quantity(),
        ];

        wp_send_json_success($product_data);
    }
}

// NOVO AJAX: Busca cliente por CPF/ID para o PDV no CRM Central
add_action('wp_ajax_mini_erp_wc_get_customer_for_pos', 'mini_erp_wc_get_customer_for_pos_callback');
if (!function_exists('mini_erp_wc_get_customer_for_pos_callback')) {
    function mini_erp_wc_get_customer_for_pos_callback() {
        check_ajax_referer(MINI_ERP_WC_NONCE, 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Permissão negada');
        
        global $wpdb;
        $identifier = sanitize_text_field($_POST['identifier'] ?? '');

        if (empty($identifier)) wp_send_json_error('Identificador de cliente vazio.');

        // Busca por CPF ou ID na tabela CRM
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, email, cpf, discount_percent FROM " . WPCRM_TABLE . " WHERE cpf = %s OR id = %d", 
            $identifier, intval($identifier)
        ), ARRAY_A);

        if ($customer) {
            wp_send_json_success($customer);
        } else {
            wp_send_json_error('Cliente não encontrado.');
        }
    }
}


// NOVO AJAX: Finaliza a Venda e Cria o Pedido WooCommerce
add_action('wp_ajax_mini_erp_wc_finalize_sale', 'mini_erp_wc_finalize_sale_callback');
if (!function_exists('mini_erp_wc_finalize_sale_callback')) {
    function mini_erp_wc_finalize_sale_callback() {
        check_ajax_referer(MINI_ERP_WC_NONCE, 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Permissão negada');
        if (!function_exists('wc_create_order')) wp_send_json_error('WooCommerce functions missing.');

        global $wpdb;
        
        $cart_items = json_decode(stripslashes($_POST['cart_items']), true);
        $total_amount = floatval($_POST['total'] ?? 0);
        $customer_cpf = sanitize_text_field($_POST['customer_cpf'] ?? '');
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? 'cod'); // cash on delivery (Dinheiro)
        $payment_days = intval($_POST['payment_days'] ?? MPDV_DEFAULT_PAYMENT_DAYS);

        if (empty($cart_items) || $total_amount <= 0) {
            wp_send_json_error('Carrinho vazio ou total inválido.');
        }

        // --- 1. Determina o Cliente WC e Dados ---
        $customer_id = 0;
        $customer_data = []; 
        
        if (!empty($customer_cpf)) {
            // Busca dados completos do CRM
            $customer_data_crm = $wpdb->get_row($wpdb->prepare("SELECT * FROM " . WPCRM_TABLE . " WHERE cpf = %s", $customer_cpf), ARRAY_A);
            
            if ($customer_data_crm) {
                 // Sincroniza/Cria o usuário WC se necessário (Função do CRM)
                 if (function_exists('wpcrm_get_or_create_wc_customer')) {
                     $wc_user_id = wpcrm_get_or_create_wc_customer($customer_cpf, $customer_data_crm);
                 } else {
                    // Se o CRM não estiver ativo, tenta achar pelo email/CPF no WP
                    $wc_user_id = get_user_by('email', $customer_data_crm['email'])->ID ?? 0;
                 }
                
                $customer_id = $wc_user_id;
                
                // Mapeia dados para o pedido
                $customer_data = [
                    'name' => $customer_data_crm['name'],
                    'email' => $customer_data_crm['email'],
                    'address' => $customer_data_crm['address']
                ];
            }
        }
        
        // --- 2. Cria o Pedido WooCommerce ---
        
        $order = wc_create_order();

        // Configura o cliente
        if ($customer_id > 0) {
            $order->set_customer_id($customer_id);
            $user_info = get_userdata($customer_id);
             $name_parts = explode(' ', $customer_data['name'] ?? $user_info->display_name);
             $first_name = $name_parts[0];
             $last_name = implode(' ', array_slice($name_parts, 1));
        } else {
            // Cliente Anônimo
             $first_name = 'Cliente';
             $last_name = 'PDV';
        }

        // Define endereço de faturamento/entrega
        $address = array(
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $customer_data['email'] ?? '',
            'address_1'  => $customer_data['address'] ?? 'PDV/Sem endereço',
            'city'       => 'N/A', 
            'state'      => 'N/A', 
            'postcode'   => '00000000',
            'country'    => 'BR'
        );
        $order->set_address($address, 'billing');
        $order->set_address($address, 'shipping');
        
        // --- 3. Adiciona Itens e Calcula Totais ---
        
        $order_subtotal = 0;
        $order_discount_total = 0;

        foreach ($cart_items as $item_data) {
            $product = wc_get_product(wc_get_product_id_by_sku($item_data['sku']));
            $quantity = intval($item_data['quantity']);
            $price = floatval($item_data['price']);
            $discount_percent = floatval($item_data['discount'] ?? 0);

            $line_subtotal = $price * $quantity;
            $line_discount_amount = $line_subtotal * ($discount_percent / 100);
            $line_total = $line_subtotal - $line_discount_amount;
            
            $order_subtotal += $line_subtotal;
            $order_discount_total += $line_discount_amount;

            if ($product) {
                // Produto Cadastrado
                $item_id = $order->add_product($product, $quantity, [
                    'subtotal' => $line_subtotal,
                    'total' => $line_total,
                ]);
                
                // Adiciona metadados de desconto
                if ($discount_percent > 0) {
                    $order->get_item($item_id)->add_meta_data('_pdv_discount_percent', $discount_percent, true);
                    $order->get_item($item_id)->save();
                }

            } else {
                // Item Não Cadastrado (Taxa/Serviço)
                $item_fee = new WC_Order_Item_Fee();
                $item_fee->set_name($item_data['name']);
                $item_fee->set_amount($line_total);
                $item_fee->set_total($line_total);
                $order->add_item($item_fee);
            }
        }
        
        // Define o total da ordem manualmente se for necessário aplicar um desconto global ou taxa.
        $order->set_total($total_amount); 

        // --- 4. Define Pagamento e Status ---
        
        $payment_gateway = new WC_Payment_Gateways();
        $gateways = $payment_gateway->get_available_payment_gateways();
        
        $chosen_gateway = $gateways[$payment_method] ?? null;

        if ($chosen_gateway) {
            $order->set_payment_method($chosen_gateway);
            $order->set_payment_method_title($chosen_gateway->get_title() . ' (PDV)');
        } else {
            $order->set_payment_method('cod'); // Fallback para Dinheiro
            $order->set_payment_method_title(ucfirst($payment_method) . ' (PDV)');
        }

        // Define status
        if ($payment_method === 'bacs') { // Boleto
            $order->set_status('on-hold', 'Aguardando Pagamento de Boleto (' . $payment_days . ' dias).');
            // Adiciona a meta do vencimento
            $order->add_meta_data('_pdv_vencimento', date('Y-m-d', strtotime('+' . $payment_days . ' days')), true);
        } else if ($payment_method === 'cod' || $payment_method === 'pix' || $payment_method === 'stripe') {
            // Assume pago na hora (Dinheiro, Pix, Cartão)
            $order->set_status('completed', 'Venda finalizada no PDV.');
        } else {
            $order->set_status('processing');
        }

        // Metadados PDV
        $order->add_meta_data('_pdv_sale', 'Mini ERP WC', true);
        $order->add_meta_data('_pdv_customer_cpf', $customer_cpf, true);
        
        $order->calculate_totals(); // Recalcula totais (garante consistência)
        $order->save();

        wp_send_json_success(['order_id' => $order->get_id()]);
    }
}


// Customers table
if (!function_exists('mini_erp_wc_customers_table_from_crm')) {
    function mini_erp_wc_customers_table_from_crm($origin_filter = 'all'){
        global $wpdb;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '" . WPCRM_TABLE . "'") != WPCRM_TABLE) {
            return '<div class="notice notice-error"><p>O plugin **WP Customer CRM** não está ativo ou a tabela de clientes centralizada não foi criada. Por favor, ative o plugin CRM para visualizar os clientes.</p></div>';
        }

        $where = '1=1';
        $params = [];
        
        if ($origin_filter !== 'all') {
            $where = 'origin = %s';
            $params[] = $origin_filter;
        }

        $sql = "SELECT id, name, email, wc_user_id, cpf, origin, discount_percent FROM " . WPCRM_TABLE . " WHERE " . $where . " ORDER BY name ASC LIMIT 200";
        $customers = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        ob_start();

        echo '<table class="mini-erp-table">';
        echo '<thead><tr><th>Nome</th><th>Email</th><th>CPF</th><th>Desconto (%)</th><th>Origem</th><th>Ações</th></tr></thead><tbody>';
        foreach($customers as $c){
            $origin_display = ($c->origin === 'erp') ? 'Mini ERP' : (($c->origin === 'pdv') ? 'WPPDV' : 'WooCommerce');
            $badge_class = 'wpcrm-origin-' . esc_attr($c->origin);
            
            echo '<tr data-crm-id="'.esc_attr($c->id).'">';
            echo '<td class="cell-open" data-type="customer" data-id="'.esc_attr($c->wc_user_id).'">'.esc_html($c->name).'</td>';
            echo '<td class="cell-open" data-type="customer" data-id="'.esc_attr($c->wc_user_id).'">'.esc_html($c->email).'</td>';
            echo '<td class="cell-open" data-type="customer" data-id="'.esc_attr($c->wc_user_id).'">'.esc_html($c->cpf).'</td>';
            echo '<td class="cell-open" data-type="customer" data-id="'.esc_attr($c->wc_user_id).'">'.esc_html($c->discount_percent).'%</td>';
            echo '<td class="cell-open" data-type="customer" data-id="'.esc_attr($c->wc_user_id).'"><span class="wpcrm-origin-badge '.esc_attr($badge_class).'">'.esc_html($origin_display).'</span></td>';
            echo '<td>';
            echo '<button class="mini-erp-small" data-type="customer" data-id="'.esc_attr($c->id).'">Ver / Editar (CRM)</button> ';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        return ob_get_clean();
    }
}

// Novo AJAX para carregar a tabela de clientes (para uso com o filtro)
add_action('wp_ajax_mini_erp_wc_get_customers_table', 'mini_erp_wc_get_customers_table_ajax');
if (!function_exists('mini_erp_wc_get_customers_table_ajax')) {
    function mini_erp_wc_get_customers_table_ajax() {
        check_ajax_referer(MINI_ERP_WC_NONCE, 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Permissão negada');
        
        $origin_filter = sanitize_text_field($_POST['origin_filter'] ?? 'all');
        
        $html = mini_erp_wc_customers_table_from_crm($origin_filter);

        if ($html !== false) {
            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_error('Erro ao gerar tabela de clientes.');
        }
    }
}


// Orders table
if (!function_exists('mini_erp_wc_orders_table')) {
    /**
     * Gera o HTML da tabela de pedidos (agora retorna o HTML em vez de imprimir).
     * @return string O HTML da tabela.
     */
    function mini_erp_wc_orders_table(){
        if (!function_exists('wc_get_orders')) return 'Funções WooCommerce indisponíveis.';
        
        $args = array('limit' => 200, 'orderby' => 'date', 'order' => 'DESC');
        $orders = wc_get_orders($args);
        
        ob_start();
        
        echo '<table class="mini-erp-table">';
        echo '<thead><tr><th>Pedido</th><th>Cliente</th><th>Total</th><th>Status</th><th>Resumo</th><th>Data</th><th>Ações</th></tr></thead><tbody>';
        foreach($orders as $o){
            $id = $o->get_id();
            $customer_id = $o->get_customer_id();
            $customer_name = $o->get_formatted_billing_full_name() ?: ($customer_id ? get_user_by('id',$customer_id)->display_name : 'Consumidor');
            $total = strip_tags($o->get_formatted_order_total());
            $status = $o->get_status();
            $date = $o->get_date_created() ? $o->get_date_created()->date_i18n('Y-m-d H:i') : 'N/D';

            $items_names = [];
            foreach ($o->get_items() as $item) {
                $items_names[] = $item->get_quantity() . 'x ' . $item->get_name();
            }
            $summary_text = implode(' + ', $items_names);

            $max_length = 60;  
            if (mb_strlen($summary_text) > $max_length) {
                $summary_display = mb_substr($summary_text, 0, $max_length) . '...';
            } else {
                $summary_display = $summary_text;
            }

            if (empty($summary_display)) $summary_display = 'Nenhum item';
            
            echo '<tr>';
            echo '<td class="cell-open" data-type="order" data-id="'.esc_attr($id).'">#'.esc_html($id).'</td>';
            echo '<td class="cell-open" data-type="order" data-id="'.esc_attr($id).'">'.esc_html($customer_name).'</td>';
            echo '<td class="cell-open" data-type="order" data-id="'.esc_attr($id).'">'.esc_html($total).'</td>';
            echo '<td class="cell-open" data-type="order" data-id="'.esc_attr($id).'">'.esc_html(wc_get_order_status_name($status)).'</td>';
            
            echo '<td class="cell-open" data-type="order" data-id="'.esc_attr($id).'"><span class="mini-erp-summary" title="'.esc_attr($summary_text).'">'.esc_html($summary_display).'</span></td>';
            
            echo '<td>'.esc_html($date).'</td>';
            echo '<td>';
            echo '<button class="mini-erp-small" data-type="order" data-id="'.esc_attr($id).'">Ver</button> ';
            echo '<button class="mini-erp-small mini-erp-danger mini-erp-delete" data-type="order" data-id="'.esc_attr($id).'">Cancelar</button>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        
        return ob_get_clean();
    }
}

// NOVO AJAX para carregar a tabela de pedidos
add_action('wp_ajax_mini_erp_wc_get_orders_table', 'mini_erp_wc_get_orders_table_ajax');
if (!function_exists('mini_erp_wc_get_orders_table_ajax')) {
    function mini_erp_wc_get_orders_table_ajax() {
        check_ajax_referer(MINI_ERP_WC_NONCE, 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Permissão negada');
        
        $html = mini_erp_wc_orders_table();

        if ($html !== false) {
            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_error('Erro ao gerar tabela de pedidos.');
        }
    }
}


// AJAX: Get item (product/order) - returns HTML form for modal
add_action('wp_ajax_mini_erp_wc_get', 'mini_erp_wc_get_ajax');
if (!function_exists('mini_erp_wc_get_ajax')) {
    function mini_erp_wc_get_ajax(){
        check_ajax_referer(MINI_ERP_WC_NONCE,'nonce');
        if (!current_user_can('manage_woocommerce')) wp_die('Permissão negada');
        
        $type = sanitize_text_field($_POST['type'] ?? '');
        $id = intval($_POST['id'] ?? 0);

        if($type === 'product'){
            if($id){
                $prod = wc_get_product($id);
                if(!$prod) wp_send_json_error('Produto não encontrado');
                $sku = $prod->get_sku();
                $name = $prod->get_name();
                $desc = $prod->get_description();
                $price = wc_format_decimal($prod->get_price(), 2);
                $cost = $prod->get_meta('_cost');
                $stock = $prod->get_stock_quantity();
                $manage_stock = $prod->managing_stock() ? 1 : 0;
            } else {
                $sku='';$name='';$desc='';$price='';$cost='';$stock='';$manage_stock=1;
            }
            ob_start();
            ?>
            <h2>Produto - <?php echo $id? '#'.$id : 'Novo'?></h2>
            <form class="mini-erp-form">
                <input type="hidden" name="type" value="product">
                <input type="hidden" name="id" class="mini-erp-id" value="<?php echo esc_attr($id)?>">
                <div class="row">
                    <input type="text" name="sku" placeholder="SKU" value="<?php echo esc_attr($sku)?>">
                    <input type="text" name="name" placeholder="Nome do produto" value="<?php echo esc_attr($name)?>">
                </div>
                <div class="row">
                    <input type="text" name="price" placeholder="Preço" value="<?php echo esc_attr($price)?>">
                    <input type="text" name="cost" placeholder="Custo" value="<?php echo esc_attr($cost)?>">
                </div>
                <div class="row">
                    <input type="number" name="stock" placeholder="Estoque" value="<?php echo esc_attr($stock)?>">
                    <label style="align-self:center"><input type="checkbox" name="manage_stock" value="1" <?php checked($manage_stock,1) ?>> Gerenciar estoque</label>
                </div>
                <div class="row">
                    <textarea name="description" placeholder="Descrição" rows="4"><?php echo esc_textarea($desc)?></textarea>
                </div>
                <div class="mini-erp-ops">
                    <?php if($id): ?>
                        <button type="button" class="mini-erp-danger mini-erp-delete" data-type="product" data-id="<?php echo esc_attr($id)?>">Excluir</button>
                    <?php endif; ?>
                    <button type="button" class="mini-erp-primary mini-erp-save">Salvar</button>
                </div>
            </form>
            <?php
            $html = ob_get_clean();
            wp_send_json_success(array('html'=>$html));
        }

        if($type === 'customer'){
            wp_send_json_error('O gerenciamento de clientes foi movido para o CRM Central.');
        }

        if($type === 'order'){
            if($id){
                $order = wc_get_order($id);
                if(!$order) wp_send_json_error('Pedido não encontrado');
                $status = $order->get_status();
                $customer_name = $order->get_formatted_billing_full_name() ?: ($order->get_customer_id()? get_user_by('id',$order->get_customer_id())->display_name : 'Consumidor');
                $total = strip_tags($order->get_formatted_order_total());
                $date = $order->get_date_created() ? $order->get_date_created()->date_i18n('Y-m-d H:i') : 'N/D';
            } else { 
                $status='draft'; 
                $customer_name='N/D'; $total='R$ 0,00'; $date='N/D';
            }
            ob_start();
            ?>
            <h2>Pedido - <?php echo $id? '#'.$id : 'Novo'?></h2>
            
            <?php if($id): ?>
                <p><strong>Cliente:</strong> <?php echo esc_html($customer_name); ?></p>
                <p><strong>Total:</strong> <?php echo esc_html($total); ?></p>
                <p><strong>Data:</strong> <?php echo esc_html($date); ?></p>
                
                <h3>Itens do Pedido:</h3>
                <table class="mini-erp-table">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Qtd</th>
                            <th>Preço Unit.</th>
                            <th>Total Linha</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    foreach ($order->get_items() as $item_id => $item) {
                        $product_name = $item->get_name();
                        $qty = $item->get_quantity();
                        $unit_price = ($qty > 0) ? wc_price($item->get_total() / $qty) : wc_price(0);
                        $line_total = wc_price($item->get_total());

                        echo '<tr>';
                        echo '<td>'.esc_html($product_name).'</td>';
                        echo '<td>'.esc_html($qty).'</td>';
                        echo '<td>'.strip_tags($unit_price).'</td>';
                        echo '<td>'.strip_tags($line_total).'</td>';
                        echo '</tr>';
                    }
                    if(empty($order->get_items()) && $id) {
                        echo '<tr><td colspan="4">Este pedido não contém itens.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <form class="mini-erp-form">
                <input type="hidden" name="type" value="order">
                <input type="hidden" name="id" class="mini-erp-id" value="<?php echo esc_attr($id)?>">
                <div class="row" style="margin-top: 15px;">
                    <label style="flex: unset; display: flex; align-items: center; gap: 5px;">Status:
                        <select name="status">
                            <option value="draft" <?php selected($status,'draft')?>>Rascunho</option>
                            <option value="processing" <?php selected($status,'processing')?>>Processando</option>
                            <option value="completed" <?php selected($status,'completed')?>>Concluído</option>
                            <option value="cancelled" <?php selected($status,'cancelled')?>>Cancelado</option>
                            <option value="on-hold" <?php selected($status,'on-hold')?>>Aguardando</option>
                        </select>
                    </label>
                </div>
                <div class="mini-erp-ops">
                    <?php if($id): ?>
                        <button type="button" class="mini-erp-danger mini-erp-delete" data-type="order" data-id="<?php echo esc_attr($id)?>">Cancelar Pedido</button>
                    <?php endif; ?>
                    <button type="button" class="mini-erp-primary mini-erp-save">Salvar</button>
                </div>
                <?php if(!$id): ?>
                    <p class="mini-erp-muted">Um novo pedido será criado no status "Rascunho". Você pode editá-lo e adicionar itens na interface do WooCommerce.</p>
                <?php endif; ?>
            </form>
            <?php
            $html = ob_get_clean();
            wp_send_json_success(array('html'=>$html));
        }

        wp_send_json_error('Tipo inválido');
    }
}

// AJAX: Save (Apenas Produto/Pedido)
add_action('wp_ajax_mini_erp_wc_save', 'mini_erp_wc_save_ajax');
if (!function_exists('mini_erp_wc_save_ajax')) {
    function mini_erp_wc_save_ajax(){
        check_ajax_referer(MINI_ERP_WC_NONCE,'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Permissão negada');
        
        $type = sanitize_text_field($_POST['type'] ?? '');
        $id = intval($_POST['id'] ?? 0);

        if($type === 'product'){
            $sku = sanitize_text_field($_POST['sku'] ?? '');
            $name = sanitize_text_field($_POST['name'] ?? '');
            $price = wc_format_decimal($_POST['price'] ?? 0);
            $cost = sanitize_text_field($_POST['cost'] ?? '');
            $stock = intval($_POST['stock'] ?? 0);
            $manage_stock = isset($_POST['manage_stock']) ? 1 : 0;
            $desc = sanitize_textarea_field($_POST['description'] ?? '');

            if($id){
                $product = wc_get_product($id);
                if(!$product) wp_send_json_error('Produto não encontrado');
                $product->set_sku($sku);
                $product->set_name($name);
                $product->set_price($price);
                $product->set_regular_price($price);
                $product->set_description($desc);
                $product->set_manage_stock($manage_stock);
                if($manage_stock) $product->set_stock_quantity($stock);
                $product->update_meta_data('_cost', $cost);
                $product->save();
                wp_send_json_success();
            } else {
                if(!function_exists('wc_create_product')) wp_send_json_error('Função wc_create_product não disponível.');
                $new_id = wc_create_product($name);
                if(is_wp_error($new_id)) wp_send_json_error('Erro ao criar produto: ' . $new_id->get_error_message());
                $product = wc_get_product($new_id);
                $product->set_sku($sku);
                $product->set_regular_price($price);
                $product->set_price($price);
                $product->set_description($desc);
                $product->set_manage_stock($manage_stock);
                if($manage_stock) $product->set_stock_quantity($stock);
                $product->update_meta_data('_cost', $cost);
                $product->save();
                wp_send_json_success();
            }
        }

        if($type === 'customer'){
            wp_send_json_error('A edição de clientes foi movida para o CRM Central. Use o botão "Ver / Editar (CRM)" para gerenciar este cliente.');
        }

        if($type === 'order'){
            $status = sanitize_text_field($_POST['status'] ?? 'draft');
            $allowed = array_keys(wc_get_order_statuses());
            $allowed[] = 'draft';
            
            if(!in_array("wc-{$status}", $allowed) && $status !== 'draft') wp_send_json_error('Status inválido');

            if($id){
                $order = wc_get_order($id);
                if(!$order) wp_send_json_error('Pedido não encontrado');
                
                if($status === 'cancelled'){
                    $order->update_status('cancelled','Cancelado via Mini ERP');
                } else {
                    $order->update_status($status);
                }
                wp_send_json_success();
            } else {
                if(!function_exists('wc_create_order')) wp_send_json_error('Função wc_create_order não disponível.');
                $order = wc_create_order();
                if(is_wp_error($order)) wp_send_json_error('Erro ao criar novo pedido: ' . $order->get_error_message());
                
                $order->set_status('draft');
                $order->set_customer_id(0);
                $order->set_created_via('mini-erp-wc');
                $order->save();
                
                if($status !== 'draft') {
                    $order->update_status($status);
                }
                
                wp_send_json_success(array('id' => $order->get_id()));
            }
        }

        wp_send_json_error('Tipo inválido');
    }
}

// AJAX: Delete (soft delete where applicable)
add_action('wp_ajax_mini_erp_wc_delete', 'mini_erp_wc_delete_ajax');
if (!function_exists('mini_erp_wc_delete_ajax')) {
    function mini_erp_wc_delete_ajax(){
        check_ajax_referer(MINI_ERP_WC_NONCE,'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Permissão negada');
        
        $type = sanitize_text_field($_POST['type'] ?? '');
        $id = intval($_POST['id'] ?? 0);
        if(!$id) wp_send_json_error('ID inválido');

        if($type === 'product'){
            wp_trash_post($id); // Move para o lixo
            wp_send_json_success();
        }
        if($type === 'customer'){
            wp_send_json_error('A exclusão de clientes foi movida para o CRM Central.');
        }
        if($type === 'order'){
            $order = wc_get_order($id);
            if(!$order) wp_send_json_error('Pedido não encontrado');
            $order->update_status('cancelled','Cancelado via Mini ERP');
            wp_send_json_success();
        }
        wp_send_json_error('Tipo inválido');
    }
}

// Helper: wc_create_product (simple wrapper)
if(!function_exists('wc_create_product')){
    function wc_create_product($name){
        $post = array(
            'post_title' => $name,
            'post_status' => 'publish',
            'post_type' => 'product',
        );
        $pid = wp_insert_post($post);
        if(is_wp_error($pid) || !$pid) return new WP_Error('error','erro');
        wp_set_object_terms($pid, 'simple', 'product_type');
        return $pid;
    }
}