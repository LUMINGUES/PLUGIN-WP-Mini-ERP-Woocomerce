<img width="1536" height="672" alt="UX TOTALMENTE MODERNO E DE FÁCIL OPERAÇÃO (1)" src="https://github.com/user-attachments/assets/013b62be-4609-4bb9-a3fe-1dbcb64be80b" />

## 🚀 PLUGIN Mini ERP WC - Produtos, Clientes e Vendas (WooCommerce Integrado)

**Plugin Name:** Mini ERP WC - Produtos, Clientes e Vendas (WooCommerce Integrado)
**Versão:** 1.7.0
**Descrição:** Mini ERP integrado ao WooCommerce. Possui módulos de Produtos, Clientes (CRM Central) e um Módulo PDV/Caixa com seleção de clientes e métodos de pagamento.

---

## 📋 Descrição Geral

O **Mini ERP WC** estende as capacidades do seu WooCommerce, oferecendo um painel de administração simplificado e centralizado para operações de varejo e B2B. Ele integra-se perfeitamente com a gestão de produtos e pedidos do WooCommerce, adicionando um **Módulo PDV (Ponto de Venda)** de alta agilidade e centralizando o gerenciamento de clientes através do **CRM Central de Clientes** (necessário plugin CRM).

Este plugin é ideal para operações que precisam de uma frente de caixa rápida (balcão/venda assistida) e uma visão consolidada de dados diretamente no painel.

## ✨ Recursos Principais

* **Ponto de Venda (PDV):** Interface ágil de frente de caixa para criar pedidos rapidamente.
    * **Leitura Inteligente:** Adiciona produtos ao carrinho via **SKU** ou **ID** com suporte a *debouncing* (leitura rápida de código de barras).
    * **Seleção de Cliente:** Busca clientes pelo **CPF/ID** na tabela centralizada do CRM.
    * **Desconto Dinâmico:** Aplica automaticamente o percentual de desconto configurado para o cliente no CRM.
    * **Pagamentos Flexíveis:** Suporte a Dinheiro, Cartão (Simulado), Pix (Simulado) e **Boleto com prazo de vencimento (dias)**.
    * **Criação de Pedido WC:** Finaliza a venda criando um pedido **nativo do WooCommerce** no status apropriado (`completed`, `on-hold`, etc.).
* **Gestão de Produtos:** Edição e visualização rápida dos seus produtos WooCommerce (limitado a campos essenciais) diretamente no painel do ERP.
* **Integração CRM Central:** Conecta-se à tabela `wpcrm_customers` para:
    * Listar e filtrar clientes por origem (PDV, Loja, ERP).
    * Redirecionar para a interface completa de edição do cliente no CRM.
* **Histórico de Pedidos:** Visualização simplificada e filtrável dos pedidos recentes do WooCommerce, permitindo alterações de status rápidas (Processando, Concluído, Cancelado) via modal.
* **UX Otimizada:** Design moderno focado na usabilidade com carregamento de dados via AJAX (modal e tabelas) para uma experiência de usuário mais fluida e rápida.

---

## 🛠️ Instalação

### Requisitos Mínimos:

1.  **WooCommerce:** O plugin WooCommerce deve estar instalado e ativo.
2.  **WP Customer CRM (Opcional, mas Recomendado):** Para usar o módulo de Clientes e aplicar descontos, o plugin WP Customer CRM (ou qualquer plugin que use a tabela `wp_wpcrm_customers`) deve estar ativo.

### Etapas de Instalação:

1.  Faça o upload da pasta do plugin (`mini-erp-wc`) para o diretório `/wp-content/plugins/` do seu WordPress.
2.  Ative o plugin através do menu 'Plugins' no painel de administração.
3.  Um novo item de menu, **Mini ERP WC**, aparecerá no seu painel.

---

## 🧑‍💻 Utilização

### 1. Ponto de Venda (PDV)

Acesse **Mini ERP WC > Ponto de Venda (PDV)**.

1.  **Adicionar Produto:** No campo de busca, digite o **SKU** ou **ID** e pressione **ENTER** (ou pause a digitação por 1 segundo). O produto será adicionado ao carrinho com a quantidade especificada.
2.  **Identificar Cliente:** No campo "CPF/ID do Cliente", insira o CPF ou ID e pressione **ENTER** para buscar os dados no CRM. Se o cliente tiver um `discount_percent` configurado no CRM, ele será aplicado aos itens do carrinho.
3.  **Finalizar:** Escolha o **Método de Pagamento**. Se for **Boleto** (`bacs`), defina os dias para vencimento. Clique em **Finalizar Venda**. O plugin cria o pedido no WooCommerce.

### 2. Gestão de Clientes e Pedidos

* **Clientes (CRM Central):** A lista de clientes é sincronizada do `WPCRM_TABLE`. Para adicionar ou editar um cliente, use o botão **Ver / Editar (CRM)**, que o redirecionará para o painel principal do CRM.
* **Produtos e Histórico de Pedidos:** Use os botões **Ver** nas tabelas para abrir um modal de edição/visualização rápida. As alterações de status de pedidos e de campos básicos de produtos são salvas diretamente no WooCommerce.

---

## 🧱 Integrações & Extensibilidade

O plugin se baseia em ações e filtros nativos do WooCommerce e em chamadas AJAX customizadas:

### Constantes Chave

| Constante | Valor Padrão | Descrição |
| :--- | :--- | :--- |
| `WPCRM_TABLE` | `wp_wpcrm_customers` | Nome da tabela para o CRM Central de Clientes. |
| `MINI_ERP_WC_NONCE` | `mini_erp_wc_nonce` | Chave de segurança para todas as chamadas AJAX. |
| `MPDV_DEFAULT_PAYMENT_DAYS` | `7` | Padrão de dias para vencimento do Boleto. |

### Chamadas AJAX Principais

| Ação AJAX | Descrição |
| :--- | :--- |
| `mini_erp_wc_get_product_for_pos` | Busca um produto WC por SKU ou ID para adicionar ao PDV. |
| `mini_erp_wc_get_customer_for_pos` | Busca um cliente no CRM por CPF ou ID para aplicar descontos no PDV. |
| `mini_erp_wc_finalize_sale` | **Cria um novo `shop_order` no WooCommerce** com base no carrinho do PDV e dados do cliente. |
| `mini_erp_wc_save` | Salva alterações rápidas em produtos ou status de pedidos no WooCommerce. |

**Desenvolvedores:** Todas as operações críticas (salvar/deletar/criar) utilizam funções nativas do WooCommerce (`wc_get_product`, `wc_create_order`, `wc_get_order`, `wp_trash_post`), garantindo a integridade dos dados e compatibilidade com outros plugins WC.
