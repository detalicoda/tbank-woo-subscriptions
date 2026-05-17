<?php
/**
 * Платежный шлюз Т-Банка с поддержкой WooCommerce Subscriptions
 *
 * @package Tbank_Woo_Subs
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Gateway_TBank_Subs class.
 */
class WC_Gateway_TBank_Subs extends WC_Payment_Gateway {

    /**
     * Terminal Key для API Т-Банка
     *
     * @var string
     */
    public $terminal_key;

    /**
     * Пароль (Secret Key) для API Т-Банка
     *
     * @var string
     */
    public $password;

    /**
     * Тестовый режим
     *
     * @var bool
     */
    public $testmode;

    /**
     * Режим отладки
     *
     * @var bool
     */
    public $debug;

    /**
     * Логирование
     *
     * @var bool
     */
    public $logging;

    /**
     * Система налогообложения
     *
     * @var bool
     */
    public $taxation;

    /**
     * Constructor.
     */
    public function __construct() {
        // Базовые параметры шлюза
        $this->id                 = 'tbank_subs';
        $this->icon               = apply_filters( 'woocommerce_tbank_subs_icon', '' );
        $this->has_fields         = false;
        $this->method_title       = __( 'Т-Банк (Подписки)', 'tbank-woo-subs' );
        $this->method_description = __( 'Принимайте регулярные и разовые платежи через интернет-эквайринг Т-Банка с сохранением карт.', 'tbank-woo-subs' );
        $this->order_button_text  = __( 'Перейти к оплате', 'tbank-woo-subs' );

        // Поддержка функций WooCommerce и WC Subscriptions
        $this->supports = array(
            'products',
            'refunds',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'multiple_subscriptions',
        );

        // Загружаем настройки
        $this->init_form_fields();
        $this->init_settings();

        // Сохраняем настройки в свойства
        $this->title         = $this->get_option( 'title' );
        $this->description   = $this->get_option( 'description' );
        $this->enabled       = $this->get_option( 'enabled' );
        $this->terminal_key  = $this->get_option( 'terminal_key' );
        $this->password      = $this->get_option( 'password' );
        $this->taxation      = $this->get_option( 'taxation', 'osn' );
        $this->testmode      = 'yes' === $this->get_option( 'testmode' );
        $this->debug         = 'yes' === $this->get_option( 'debug' );
        $this->logging       = 'yes' === $this->get_option( 'logging' );

        // Административные хуки
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

        // Хуки обработки подписок
        add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );
        add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array( $this, 'update_failing_payment_method' ), 10, 2 );

        // Webhook обработчик
        add_action( 'woocommerce_api_' . $this->id, array( $this, 'webhook_handler' ) );
    }

    /**
     * Инициализация полей настроек в админке.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __( 'Включить/Выключить', 'tbank-woo-subs' ),
                'label'       => __( 'Включить оплату через Т-Банк', 'tbank-woo-subs' ),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'testmode' => array(
                'title'       => __( 'Тестовый режим', 'tbank-woo-subs' ),
                'label'       => __( 'Включить тестовый режим', 'tbank-woo-subs' ),
                'type'        => 'checkbox',
                'description' => __( 'Используйте тестовые ключи API для отладки.', 'tbank-woo-subs' ),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'title' => array(
                'title'       => __( 'Название', 'tbank-woo-subs' ),
                'type'        => 'text',
                'description' => __( 'Название метода оплаты, которое видит клиент при оформлении заказа.', 'tbank-woo-subs' ),
                'default'     => __( 'Банковская карта (Т-Банк)', 'tbank-woo-subs' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Описание', 'tbank-woo-subs' ),
                'type'        => 'textarea',
                'description' => __( 'Описание метода оплаты на странице оформления заказа.', 'tbank-woo-subs' ),
                'default'     => __( 'Оплата банковской картой Visa, MasterCard, МИР. Подписки будут продлеваться автоматически.', 'tbank-woo-subs' ),
                'desc_tip'    => true,
            ),
            'terminal_key' => array(
                'title'       => __( 'Terminal Key', 'tbank-woo-subs' ),
                'type'        => 'text',
                'description' => __( 'Идентификатор терминала из личного кабинета Т-Банка.', 'tbank-woo-subs' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'password' => array(
                'title'       => __( 'Secret Key', 'tbank-woo-subs' ),
                'type'        => 'password',
                'description' => __( 'Secret Key (пароль для API) из личного кабинета Т-Банка.', 'tbank-woo-subs' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'logging' => array(
                'title'       => __( 'Логирование', 'tbank-woo-subs' ),
                'label'       => __( 'Включить логирование', 'tbank-woo-subs' ),
                'type'        => 'checkbox',
                'description' => __( 'Записывать логи взаимодействия с API Т-Банка.', 'tbank-woo-subs' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'debug' => array(
                'title'       => __( 'Отладка', 'tbank-woo-subs' ),
                'label'       => __( 'Включить режим отладки', 'tbank-woo-subs' ),
                'type'        => 'checkbox',
                'description' => __( 'Выводить дополнительную отладочную информацию.', 'tbank-woo-subs' ),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'taxation' => array(
                'title'       => __( 'Система налогообложения', 'tbank-woo-subs' ),
                'type'        => 'select',
                'description' => __( 'Выберите систему налогообложения для чеков (54-ФЗ).', 'tbank-woo-subs' ),
                'default'     => 'osn',
                'desc_tip'    => true,
                'options'     => array(
                    'osn'                => __( 'Общая (ОСН)', 'tbank-woo-subs' ),
                    'usn_income'         => __( 'Упрощённая доходы (УСН доходы)', 'tbank-woo-subs' ),
                    'usn_income_outcome' => __( 'Упрощённая доходы-расходы (УСН доходы-расходы)', 'tbank-woo-subs' ),
                    'envd'               => __( 'ЕНВД', 'tbank-woo-subs' ),
                    'esn'                => __( 'ЕСХН', 'tbank-woo-subs' ),
                    'patent'             => __( 'Патентная (ПСН)', 'tbank-woo-subs' ),
                ),
            ),
        );
    }

    /**
     * Обработка основного платежа (разового или первого для подписки).
     *
     * @param int $order_id ID заказа.
     * 
     * @return array Результат обработки.
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'Ошибка: Заказ не найден.', 'tbank-woo-subs' ), 'error' );
            return array( 'result' => 'fail' );
        }

        try {
            // Получаем API объект
            $api = $this->get_api();

            // Генерируем уникальный ключ клиента
            $customer_key = $this->generate_customer_key( $order );

            // Проверяем, содержит ли заказ подписку
            $has_subscription = $this->order_contains_subscription( $order );

            // Сумма заказа в копейках
            $amount = $this->format_amount( $order->get_total() );

            // Описание платежа
            $description = $this->get_payment_description( $order, $has_subscription );

            // Формируем чек (Receipt) для соблюдения 54-ФЗ
            $receipt = $this->build_receipt( $order );

            // Инициализируем платеж с чеком
            $response = $api->init_payment(
                $order_id,
                $amount,
                $description,
                $customer_key,
                $has_subscription,
                'O',           // pay_type: O - одностадийная
                null,          // IP (будет определен автоматически)
                array(
                    'Receipt' => $receipt,
                )
            );

            if ( ! $response || empty( $response['Success'] ) ) {
                $error = isset( $response['Message'] ) ? $response['Message'] : __( 'Неизвестная ошибка API', 'tbank-woo-subs' );
                $this->log( 'Init payment failed: ' . print_r( $response, true ) );
                
                wc_add_notice( __( 'Ошибка при создании платежа: ', 'tbank-woo-subs' ) . $error, 'error' );
                $order->add_order_note( __( 'T-Bank Init Error: ', 'tbank-woo-subs' ) . $error );
                
                return array( 'result' => 'fail' );
            }

            // Сохраняем метаданные платежа
            $order->update_meta_data( '_tbank_payment_id', $response['PaymentId'] );
            $order->update_meta_data( '_tbank_customer_key', $customer_key );
            
            if ( $has_subscription ) {
                $order->update_meta_data( '_tbank_recurrent_order', 'true' );
            }
            
            $order->save();

            // Очищаем корзину
            WC()->cart->empty_cart();

            $this->log( 'Payment ' . $response['PaymentId'] . ' initiated successfully. Redirecting to: ' . $response['PaymentURL'] );

            return array(
                'result'   => 'success',
                'redirect' => $response['PaymentURL'],
            );

        } catch ( Exception $e ) {
            $this->log( 'Process payment exception: ' . $e->getMessage() );
            
            wc_add_notice( __( 'Произошла ошибка при обработке платежа. Пожалуйста, попробуйте еще раз.', 'tbank-woo-subs' ), 'error' );
            $order->add_order_note( __( 'T-Bank Exception: ', 'tbank-woo-subs' ) . $e->getMessage() );
            
            return array( 'result' => 'fail' );
        }
    }

    /**
     * Формирование чека (Receipt) для API Т-Банка (54-ФЗ).
     *
     * @param WC_Order $order Заказ.
     * 
     * @return array
     */
    private function build_receipt( $order ) {
        $items = array();
        
        // Система налогообложения (можно вынести в настройки)
        $taxation = $this->get_option( 'taxation', 'osn' );
        
        // Перебираем товары в заказе
        foreach ( $order->get_items() as $item ) {
            /** @var WC_Order_Item_Product $item */
            $product = $item->get_product();
            
            // Цена за единицу в копейках
            $price = $this->format_amount( $order->get_item_subtotal( $item, false ) / $item->get_quantity() );
            
            // Сумма по позиции в копейках
            $amount = $this->format_amount( $order->get_item_subtotal( $item, false ) );
            
            // Название товара (ограничение 128 символов)
            $name = mb_substr( $item->get_name(), 0, 128 );
            
            // Ставка НДС
            $tax = $this->get_tax_rate( $item );
            
            // Признак предмета расчёта
            $payment_object = $product && $product->is_virtual() ? 'service' : 'commodity';
            
            // Признак способа расчёта
            $payment_method = 'full_payment';
            
            $items[] = array(
                'Name'          => $name,
                'Price'         => $price,
                'Quantity'      => $item->get_quantity(),
                'Amount'        => $amount,
                'Tax'           => $tax,
                'PaymentObject' => $payment_object,
                'PaymentMethod' => $payment_method,
            );
        }
        
        // Добавляем доставку как отдельную позицию
        if ( $order->get_shipping_total() > 0 ) {
            $shipping_total = $this->format_amount( $order->get_shipping_total() );
            
            $items[] = array(
                'Name'          => mb_substr( $order->get_shipping_method(), 0, 128 ),
                'Price'         => $shipping_total,
                'Quantity'      => 1,
                'Amount'        => $shipping_total,
                'Tax'           => 'none',
                'PaymentObject' => 'service',
                'PaymentMethod' => 'full_payment',
            );
        }
        
        // Собираем чек
        $receipt = array(
            'Email'     => $order->get_billing_email(),
            'Phone'     => $this->format_phone( $order->get_billing_phone() ),
            'Taxation'  => $taxation,
            'Items'     => $items,
        );
        
        // Если нет email, но есть телефон — используем телефон
        if ( empty( $receipt['Email'] ) && ! empty( $receipt['Phone'] ) ) {
            unset( $receipt['Email'] );
        }
        
        // Если нет телефона, но есть email — используем email
        if ( empty( $receipt['Phone'] ) && ! empty( $receipt['Email'] ) ) {
            unset( $receipt['Phone'] );
        }
        
        return $receipt;
    }

    /**
     * Получение ставки НДС для позиции заказа.
     *
     * @param WC_Order_Item $item Позиция заказа.
     * 
     * @return string
     */
    private function get_tax_rate( $item ) {
        // Если WooCommerce считает налоги
        $tax_status = $item->get_tax_status();
        
        if ( $tax_status === 'none' || $tax_status === 'zero-rate' ) {
            return 'none';
        }
        
        // Получаем налоги по позиции
        $taxes = $item->get_taxes();
        $total_tax = 0;
        
        if ( isset( $taxes['total'] ) && is_array( $taxes['total'] ) ) {
            $total_tax = array_sum( $taxes['total'] );
        }
        
        if ( $total_tax <= 0 ) {
            return 'none';
        }
        
        // Определяем ставку по сумме налога
        $subtotal = $item->get_subtotal();
        
        if ( $subtotal > 0 ) {
            $rate = ( $total_tax / $subtotal ) * 100;
            
            if ( $rate >= 19 ) {
                return 'vat20';
            } elseif ( $rate >= 9 ) {
                return 'vat10';
            }
        }
        
        // По умолчанию НДС 20%
        return 'vat20';
    }

    /**
     * Форматирование телефона для чека.
     *
     * @param string $phone Номер телефона.
     * 
     * @return string
     */
    private function format_phone( $phone ) {
        // Убираем всё кроме цифр
        $phone = preg_replace( '/[^0-9]/', '', $phone );
        
        // Приводим к формату +7XXXXXXXXXX
        if ( strlen( $phone ) === 11 && $phone[0] === '8' ) {
            $phone = '+7' . substr( $phone, 1 );
        } elseif ( strlen( $phone ) === 11 && $phone[0] === '7' ) {
            $phone = '+' . $phone;
        } elseif ( strlen( $phone ) === 10 ) {
            $phone = '+7' . $phone;
        }
        
        return $phone;
    }

    /**
     * Обработка регулярного платежа подписки.
     *
     * @param float    $amount Сумма к оплате.
     * @param WC_Order $renewal_order Заказ на продление.
     */
    public function scheduled_subscription_payment( $amount, $renewal_order ) {
        $this->log( 'Processing scheduled subscription payment for order: ' . $renewal_order->get_id() );

        try {
            // Получаем связанные подписки
            $subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
            
            if ( empty( $subscriptions ) ) {
                $this->log( 'No subscriptions found for renewal order: ' . $renewal_order->get_id() );
                $renewal_order->update_status( 'failed', __( 'Не найдена связанная подписка.', 'tbank-woo-subs' ) );
                return;
            }

            $subscription = reset( $subscriptions );
            
            // Получаем сохраненный RebillId
            $rebill_id = $subscription->get_meta( '_tbank_rebill_id', true );
            
            if ( empty( $rebill_id ) ) {
                $this->log( 'No RebillId found for subscription: ' . $subscription->get_id() );
                $renewal_order->update_status( 'failed', __( 'Не найден токен для автоплатежа (RebillId).', 'tbank-woo-subs' ) );
                return;
            }

            // Получаем API объект
            $api = $this->get_api();

            // Формируем чек для автоплатежа
            $receipt = $this->build_receipt( $renewal_order );

            // Инициализируем новый платеж для списания
            $init_response = $api->init_payment(
                $renewal_order->get_id(),
                $this->format_amount( $amount ),
                sprintf( __( 'Автопродление подписки #%s', 'tbank-woo-subs' ), $subscription->get_id() ),
                $subscription->get_customer_id(),
                false,          // Не нужен Recurrent=Y для последующих списаний
                'O',            // pay_type: O - одностадийная
                null,           // IP (будет определен автоматически)
                array(
                    'Receipt' => $receipt,  // Добавляем чек
                )
            );

            if ( ! $init_response || empty( $init_response['Success'] ) ) {
                $error = isset( $init_response['Message'] ) ? $init_response['Message'] : __( 'Неизвестная ошибка API', 'tbank-woo-subs' );
                $this->log( 'Init for renewal failed: ' . print_r( $init_response, true ) );
                
                $renewal_order->update_status( 'failed', __( 'Ошибка инициализации автоплатежа: ', 'tbank-woo-subs' ) . $error );
                return;
            }

            // Проводим списание
            $charge_response = $api->charge_payment( $init_response['PaymentId'], $rebill_id );

            if ( ! $charge_response || empty( $charge_response['Success'] ) ) {
                $error = isset( $charge_response['Message'] ) ? $charge_response['Message'] : __( 'Неизвестная ошибка API', 'tbank-woo-subs' );
                $this->log( 'Charge failed for renewal order: ' . $renewal_order->get_id() . '. Error: ' . print_r( $charge_response, true ) );
                
                $renewal_order->update_status( 'failed', __( 'Ошибка списания автоплатежа: ', 'tbank-woo-subs' ) . $error );
                return;
            }

            // Платеж успешен
            $renewal_order->payment_complete( $init_response['PaymentId'] );
            
            $order_note = sprintf(
                __( 'Автоплатеж через Т-Банк успешен. Сумма: %s. Payment ID: %s', 'tbank-woo-subs' ),
                wc_price( $amount ),
                $init_response['PaymentId']
            );
            
            $renewal_order->add_order_note( $order_note );
            
            $this->log( 'Successfully charged subscription renewal: ' . $renewal_order->get_id() );

        } catch ( Exception $e ) {
            $this->log( 'Exception in scheduled subscription payment: ' . $e->getMessage() );
            
            $renewal_order->update_status( 'failed', __( 'Исключение при автоплатеже: ', 'tbank-woo-subs' ) . $e->getMessage() );
        }
    }

    /**
     * Обработчик Webhook от Т-Банка.
     */
    public function webhook_handler() {
        // Проверяем метод запроса
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            http_response_code( 405 );
            die( __( 'Метод не поддерживается. Используйте POST.', 'tbank-woo-subs' ) );
        }

        // Получаем тело запроса
        $raw_post = file_get_contents( 'php://input' );
        $data = json_decode( $raw_post, true );

        $this->log( 'Webhook received: ' . print_r( $data, true ) );

        // Базовая валидация
        if ( ! $data || empty( $data['OrderId'] ) || empty( $data['PaymentId'] ) ) {
            http_response_code( 400 );
            die( __( 'Недействительный запрос: отсутствуют обязательные поля.', 'tbank-woo-subs' ) );
        }

        $order_id   = absint( $data['OrderId'] );
        $payment_id = sanitize_text_field( $data['PaymentId'] );
        
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            $this->log( 'Order not found: ' . $order_id );
            http_response_code( 404 );
            die( __( 'Заказ не найден.', 'tbank-woo-subs' ) );
        }

        // Проверяем, что это наш метод оплаты
        if ( $order->get_payment_method() !== $this->id ) {
            http_response_code( 200 );
            die( 'OK - different payment method' );
        }

        try {
            // Получаем статус платежа
            $api = $this->get_api();
            $state_response = $api->get_payment_state( $payment_id );

            if ( ! $state_response || empty( $state_response['Success'] ) ) {
                $this->log( 'Failed to get payment state for: ' . $payment_id );
                http_response_code( 500 );
                die( __( 'Ошибка получения статуса платежа.', 'tbank-woo-subs' ) );
            }

            $status = isset( $state_response['Status'] ) ? $state_response['Status'] : 'UNKNOWN';

            $this->log( sprintf( 'Payment %s status: %s', $payment_id, $status ) );

            // Обрабатываем статус платежа
            switch ( $status ) {
                case 'CONFIRMED':
                    if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
                        $order->payment_complete( $payment_id );
                        $order->add_order_note( __( 'Платеж через Т-Банк подтвержден.', 'tbank-woo-subs' ) );
                        
                        // Сохраняем RebillId в подписки
                        $this->maybe_save_rebill_id( $order, $data );
                    }
                    break;

                case 'REJECTED':
                    $order->update_status( 'failed', __( 'Платеж отклонен Т-Банком.', 'tbank-woo-subs' ) );
                    break;

                case 'CANCELED':
                    $order->update_status( 'cancelled', __( 'Платеж отменен.', 'tbank-woo-subs' ) );
                    break;
            }

            http_response_code( 200 );
            die( 'OK' );

        } catch ( Exception $e ) {
            $this->log( 'Webhook exception: ' . $e->getMessage() );
            http_response_code( 500 );
            die( __( 'Внутренняя ошибка сервера.', 'tbank-woo-subs' ) );
        }
    }

    /**
     * Сохранение RebillId в подписки.
     *
     * @param WC_Order $order  Заказ.
     * @param array    $response Ответ API.
     */
    private function maybe_save_rebill_id( $order, $response ) {
        if ( ! isset( $response['RebillId'] ) || empty( $response['RebillId'] ) ) {
            return;
        }

        if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            return;
        }

        $subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'parent' ) );

        if ( empty( $subscriptions ) ) {
            return;
        }

        foreach ( $subscriptions as $subscription ) {
            $subscription->update_meta_data( '_tbank_rebill_id', $response['RebillId'] );
            $subscription->update_meta_data( '_tbank_customer_key', $order->get_meta( '_tbank_customer_key', true ) );
            
            // Сохраняем информацию о карте
            if ( isset( $response['Pan'] ) ) {
                $subscription->update_meta_data( '_tbank_masked_pan', $response['Pan'] );
            }
            if ( isset( $response['ExpDate'] ) ) {
                $subscription->update_meta_data( '_tbank_card_exp_date', $response['ExpDate'] );
            }
            
            $subscription->save();
            
            $this->log( 'RebillId ' . $response['RebillId'] . ' saved to subscription ' . $subscription->get_id() );
        }
    }

    /**
     * Обновление метода оплаты при сбоях.
     *
     * @param WC_Subscription $subscription Подписка.
     * @param WC_Order        $renewal_order Заказ продления.
     */
    public function update_failing_payment_method( $subscription, $renewal_order ) {
        $this->log( 'Updating failing payment method for subscription: ' . $subscription->get_id() );
        
        // Обновляем метод оплаты в подписке
        $subscription->set_payment_method( $this->id );
        $subscription->save();
    }

    /**
     * Получение экземпляра API.
     *
     * @return TBank_Subs_API
     */
    private function get_api() {
        require_once TBANK_SUBS_PLUGIN_DIR . 'includes/class-tbank-api.php';
        return new TBank_Subs_API( $this->terminal_key, $this->password, $this->testmode );
    }

    /**
     * Проверка, содержит ли заказ подписку.
     *
     * @param WC_Order $order Заказ.
     * 
     * @return bool
     */
    private function order_contains_subscription( $order ) {
        if ( ! function_exists( 'wcs_order_contains_subscription' ) ) {
            return false;
        }
        return (bool) wcs_order_contains_subscription( $order, array( 'parent', 'renewal', 'resubscribe', 'switch' ) );
    }

    /**
     * Генерация уникального ключа клиента.
     *
     * @param WC_Order $order Заказ.
     * 
     * @return string
     */
    private function generate_customer_key( $order ) {
        $customer_id = $order->get_customer_id();
        
        if ( $customer_id ) {
            return 'user_' . $customer_id . '_' . wp_generate_password( 8, false );
        }
        
        return 'guest_' . $order->get_billing_email() . '_' . $order->get_id();
    }

    /**
     * Форматирование суммы для API Т-Банка.
     *
     * @param float $amount Сумма.
     * 
     * @return int Сумма в копейках.
     */
    private function format_amount( $amount ) {
        // Конвертируем в копейки и округляем
        $kopeks = round( (float) $amount * 100 );
        
        // Логируем для отладки
        $this->log( sprintf( 'Amount conversion: %.2f RUB -> %d kopeks', $amount, $kopeks ) );
        
        return (int) $kopeks;
    }

    /**
     * Получение описания платежа.
     *
     * @param WC_Order $order            Заказ.
     * @param bool     $has_subscription Есть ли подписка.
     * 
     * @return string
     */
    private function get_payment_description( $order, $has_subscription ) {
        $blog_name = get_bloginfo( 'name' );
        
        if ( $has_subscription ) {
            return sprintf(
                __( '%1$s - Заказ #%2$s + Подписка', 'tbank-woo-subs' ),
                $blog_name,
                $order->get_order_number()
            );
        }
        
        return sprintf(
            __( '%1$s - Заказ #%2$s', 'tbank-woo-subs' ),
            $blog_name,
            $order->get_order_number()
        );
    }

    /**
     * Синхронизация метода доставки из заказа в подписку (публичный метод).
     *
     * @param WC_Order        $order        Родительский заказ.
     * @param WC_Subscription $subscription Подписка.
     */
    public function sync_shipping_to_subscription( $order, $subscription ) {
        // Получаем методы доставки из заказа
        $shipping_items = $order->get_items( 'shipping' );
        
        if ( empty( $shipping_items ) ) {
            return;
        }
        
        // Удаляем существующие методы доставки в подписке (если есть)
        $existing_shipping = $subscription->get_items( 'shipping' );
        foreach ( $existing_shipping as $item_id => $item ) {
            $subscription->remove_item( $item_id );
        }
        
        // Копируем каждый метод доставки
        foreach ( $shipping_items as $item ) {
            $shipping_item = new WC_Order_Item_Shipping();
            
            // Копируем основные свойства
            $shipping_item->set_method_title( $item->get_method_title() );
            $shipping_item->set_method_id( $item->get_method_id() );
            $shipping_item->set_instance_id( $item->get_instance_id() );
            $shipping_item->set_total( $item->get_total() );
            $shipping_item->set_taxes( $item->get_taxes() );
            
            // Копируем все метаданные
            foreach ( $item->get_meta_data() as $meta ) {
                $shipping_item->add_meta_data( $meta->key, $meta->value, true );
            }
            
            $subscription->add_item( $shipping_item );
        }
        
        // Копируем адрес доставки
        $shipping_address = $order->get_address( 'shipping' );
        $subscription->set_address( $shipping_address, 'shipping' );
        
        // Сохраняем информацию о доставке в метаданных
        $shipping_methods = $order->get_shipping_methods();
        if ( ! empty( $shipping_methods ) ) {
            $first_method = reset( $shipping_methods );
            $subscription->update_meta_data( '_shipping_method', $first_method->get_method_id() );
            $subscription->update_meta_data( '_shipping_method_title', $first_method->get_method_title() );
        }
        
        // Пересчитываем и сохраняем
        $subscription->calculate_totals();
        $subscription->save();
        
        $this->log( sprintf( 
            'Shipping synced to subscription #%s from order #%s. Method: %s', 
            $subscription->get_id(),
            $order->get_id(),
            ! empty( $shipping_methods ) ? $first_method->get_method_title() : 'none'
        ) );
    }

    /**
     * Логирование сообщений.
     *
     * @param string $message Сообщение.
     */
    private function log( $message ) {
        if ( $this->debug || $this->logging ) {
            $logger = wc_get_logger();
            $context = array( 'source' => $this->id );
            $logger->info( $message, $context );
        }
    }
}