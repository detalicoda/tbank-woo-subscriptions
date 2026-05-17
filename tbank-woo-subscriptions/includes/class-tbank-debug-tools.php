<?php
/**
 * Отладочные инструменты для T-Bank WooCommerce Subscriptions
 *
 * @package Tbank_Woo_Subs
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

class TBank_Subs_Debug_Tools {

    public function __construct() {
        // Добавляем страницу в админ-меню
        add_action( 'admin_menu', array( $this, 'add_debug_page' ) );
        
        // Добавляем ссылку в меню WooCommerce при отладке
        add_action( 'admin_menu', array( $this, 'add_wc_submenu' ), 99 );
    }

    /**
     * Добавление основной страницы отладки.
     */
    public function add_debug_page() {
        // Показываем страницу только если включен режим отладки или WP_DEBUG
        if ( ! $this->is_debug_enabled() ) {
            return;
        }
        
        add_management_page(
            __( 'T-Bank Debug', 'tbank-woo-subs' ),
            __( 'T-Bank Debug', 'tbank-woo-subs' ),
            'manage_options',
            'tbank-debug',
            array( $this, 'debug_page_content' )
        );
    }

    /**
     * Добавление подменю в WooCommerce.
     */
    public function add_wc_submenu() {
        if ( ! $this->is_debug_enabled() ) {
            return;
        }
        
        add_submenu_page(
            'woocommerce',
            __( 'T-Bank Debug', 'tbank-woo-subs' ),
            __( 'T-Bank Debug', 'tbank-woo-subs' ),
            'manage_options',
            'tbank-debug',
            array( $this, 'debug_page_content' )
        );
    }

    /**
     * Проверка, включен ли режим отладки.
     *
     * @return bool
     */
    private function is_debug_enabled() {
        $settings = get_option( 'woocommerce_tbank_subs_settings', array() );
        $debug_enabled = isset( $settings['debug'] ) && 'yes' === $settings['debug'];
        
        return $debug_enabled || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
    }

    /**
     * Содержимое страницы отладки.
     */
    public function debug_page_content() {
        $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
        $check_payment = isset( $_POST['check_payment'] );
        $save_rebill = isset( $_POST['save_rebill'] );
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Отладка T-Bank Subscriptions', 'tbank-woo-subs' ); ?></h1>
            
            <?php $this->render_search_form( $order_id ); ?>

            <?php if ( $order_id ) : ?>
                <?php
                $order = wc_get_order( $order_id );
                
                if ( ! $order ) : ?>
                    <div class="notice notice-error">
                        <p><?php printf( esc_html__( 'Заказ #%d не найден!', 'tbank-woo-subs' ), esc_html( $order_id ) ); ?></p>
                    </div>
                <?php else : ?>
                    <hr>
                    <?php
                    $this->render_order_info( $order );
                    $this->render_tbank_meta( $order );
                    $this->render_all_meta( $order );
                    $this->render_subscriptions_info( $order );
                    $this->render_user_subscriptions( $order );
                    $this->render_actions( $order, $check_payment, $save_rebill );
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Форма поиска заказа.
     *
     * @param int $order_id ID заказа.
     */
    private function render_search_form( $order_id ) {
        ?>
        <form method="post" action="">
            <?php wp_nonce_field( 'tbank_debug_action', 'tbank_debug_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="order_id"><?php esc_html_e( 'ID Заказа', 'tbank-woo-subs' ); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="order_id" 
                               id="order_id" 
                               value="<?php echo esc_attr( $order_id ?: '' ); ?>" 
                               class="regular-text" 
                               placeholder="<?php esc_attr_e( 'Введите ID заказа...', 'tbank-woo-subs' ); ?>" />
                        <p class="description">
                            <?php esc_html_e( 'Введите ID заказа для проверки данных Т-Банка', 'tbank-woo-subs' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Проверить', 'tbank-woo-subs' ); ?>">
            </p>
        </form>
        <?php
    }

    /**
     * Основная информация о заказе.
     *
     * @param WC_Order $order Заказ.
     */
    private function render_order_info( $order ) {
        ?>
        <h2><?php printf( esc_html__( 'Информация о заказе #%s', 'tbank-woo-subs' ), esc_html( $order->get_id() ) ); ?></h2>
        
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Параметр', 'tbank-woo-subs' ); ?></th>
                    <th><?php esc_html_e( 'Значение', 'tbank-woo-subs' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e( 'ID', 'tbank-woo-subs' ); ?></strong></td>
                    <td>
                        <a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
                            #<?php echo esc_html( $order->get_id() ); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Статус', 'tbank-woo-subs' ); ?></strong></td>
                    <td><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Метод оплаты', 'tbank-woo-subs' ); ?></strong></td>
                    <td><?php echo esc_html( $order->get_payment_method_title() ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Сумма', 'tbank-woo-subs' ); ?></strong></td>
                    <td><?php echo wp_kses_post( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Родительский заказ', 'tbank-woo-subs' ); ?></strong></td>
                    <td>
                        <?php if ( $order->get_parent_id() ) : ?>
                            <a href="<?php echo esc_url( get_edit_post_link( $order->get_parent_id() ) ); ?>">
                                #<?php echo esc_html( $order->get_parent_id() ); ?>
                            </a>
                            (<?php esc_html_e( 'продление подписки', 'tbank-woo-subs' ); ?>)
                        <?php else : ?>
                            <?php esc_html_e( 'Корневой заказ', 'tbank-woo-subs' ); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Дата создания', 'tbank-woo-subs' ); ?></strong></td>
                    <td><?php echo esc_html( $order->get_date_created()->date( 'Y-m-d H:i:s' ) ); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e( 'Email клиента', 'tbank-woo-subs' ); ?></strong></td>
                    <td><?php echo esc_html( $order->get_billing_email() ); ?></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * Мета-данные Т-Банка.
     *
     * @param WC_Order $order Заказ.
     */
    private function render_tbank_meta( $order ) {
        $tbank_meta = array(
            '_tbank_payment_id'      => __( 'Payment ID', 'tbank-woo-subs' ),
            '_tbank_customer_key'    => __( 'Customer Key', 'tbank-woo-subs' ),
            '_tbank_recurrent_order' => __( 'Recurrent Order', 'tbank-woo-subs' ),
            '_tbank_rebill_id'       => __( 'Rebill ID', 'tbank-woo-subs' ),
            '_tbank_masked_pan'      => __( 'Masked PAN', 'tbank-woo-subs' ),
            '_tbank_card_exp_date'   => __( 'Card Exp Date', 'tbank-woo-subs' ),
        );
        
        ?>
        <h3><?php esc_html_e( 'Мета-данные Т-Банка', 'tbank-woo-subs' ); ?></h3>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Параметр', 'tbank-woo-subs' ); ?></th>
                    <th><?php esc_html_e( 'Значение', 'tbank-woo-subs' ); ?></th>
                    <th><?php esc_html_e( 'Статус', 'tbank-woo-subs' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $tbank_meta as $meta_key => $meta_label ) : ?>
                    <?php
                    $value = $order->get_meta( $meta_key, true );
                    $has_value = ! empty( $value );
                    $status_class = $has_value ? 'notice-success' : 'notice-error';
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $meta_label ); ?></strong></td>
                        <td>
                            <?php if ( $has_value ) : ?>
                                <code><?php echo esc_html( $value ); ?></code>
                            <?php else : ?>
                                <span style="color: #d63638;"><?php esc_html_e( 'НЕ ЗАПОЛНЕНО', 'tbank-woo-subs' ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="dashicons <?php echo $has_value ? 'dashicons-yes-alt' : 'dashicons-no-alt'; ?>" 
                                  style="color: <?php echo $has_value ? '#46b450' : '#d63638'; ?>;"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Все мета-данные заказа.
     *
     * @param WC_Order $order Заказ.
     */
    private function render_all_meta( $order ) {
        $all_meta = $order->get_meta_data();
        
        ?>
        <h3><?php esc_html_e( 'Все мета-данные заказа', 'tbank-woo-subs' ); ?></h3>
        <?php if ( empty( $all_meta ) ) : ?>
            <p><?php esc_html_e( 'Нет мета-данных', 'tbank-woo-subs' ); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Ключ', 'tbank-woo-subs' ); ?></th>
                        <th><?php esc_html_e( 'Значение', 'tbank-woo-subs' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $all_meta as $meta ) : ?>
                        <?php
                        $data = $meta->get_data();
                        $value = $data['value'];
                        
                        if ( is_array( $value ) ) {
                            $display = '<pre>' . esc_html( print_r( $value, true ) ) . '</pre>';
                        } elseif ( is_object( $value ) ) {
                            $display = '<pre>' . esc_html( print_r( $value, true ) ) . '</pre>';
                        } else {
                            $display = esc_html( $value );
                        }
                        ?>
                        <tr>
                            <td><code><?php echo esc_html( $data['key'] ); ?></code></td>
                            <td><?php echo $display; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    /**
     * Информация о связанных подписках.
     *
     * @param WC_Order $order Заказ.
     */
    private function render_subscriptions_info( $order ) {
        if ( ! function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'Функция wcs_get_subscriptions_for_order недоступна. Установлен ли WooCommerce Subscriptions?', 'tbank-woo-subs' );
            echo '</p></div>';
            return;
        }
        
        ?>
        <h3><?php esc_html_e( 'Связанные подписки', 'tbank-woo-subs' ); ?></h3>
        
        <?php
        $order_types = array(
            'parent'      => __( 'Родительские', 'tbank-woo-subs' ),
            'renewal'     => __( 'Продления', 'tbank-woo-subs' ),
            'resubscribe' => __( 'Переподписки', 'tbank-woo-subs' ),
            'switch'      => __( 'Переключения', 'tbank-woo-subs' ),
            'any'         => __( 'Любые', 'tbank-woo-subs' ),
        );
        
        foreach ( $order_types as $order_type => $type_label ) : ?>
            <h4><?php echo esc_html( $type_label ); ?> (order_type: <code><?php echo esc_html( $order_type ); ?></code>)</h4>
            
            <?php
            $subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => $order_type ) );
            
            if ( empty( $subscriptions ) ) : ?>
                <p style="color: #d63638;"><?php esc_html_e( 'Подписки не найдены', 'tbank-woo-subs' ); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID подписки', 'tbank-woo-subs' ); ?></th>
                            <th><?php esc_html_e( 'Статус', 'tbank-woo-subs' ); ?></th>
                            <th><?php esc_html_e( 'Rebill ID', 'tbank-woo-subs' ); ?></th>
                            <th><?php esc_html_e( 'Customer Key', 'tbank-woo-subs' ); ?></th>
                            <th><?php esc_html_e( 'Masked PAN', 'tbank-woo-subs' ); ?></th>
                            <th><?php esc_html_e( 'Действия', 'tbank-woo-subs' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $subscriptions as $subscription ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( $subscription->get_edit_order_url() ); ?>">
                                        #<?php echo esc_html( $subscription->get_id() ); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( wcs_get_subscription_status_name( $subscription->get_status() ) ); ?></td>
                                <td>
                                    <?php 
                                    $rebill_id = $subscription->get_meta( '_tbank_rebill_id', true );
                                    echo $rebill_id ? '<code>' . esc_html( $rebill_id ) . '</code>' : '<span style="color:#d63638;">' . esc_html__( 'НЕТ', 'tbank-woo-subs' ) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $customer_key = $subscription->get_meta( '_tbank_customer_key', true );
                                    echo $customer_key ? '<code>' . esc_html( $customer_key ) . '</code>' : '<span style="color:#d63638;">' . esc_html__( 'НЕТ', 'tbank-woo-subs' ) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $masked_pan = $subscription->get_meta( '_tbank_masked_pan', true );
                                    echo $masked_pan ? esc_html( $masked_pan ) : '<span style="color:#d63638;">' . esc_html__( 'НЕТ', 'tbank-woo-subs' ) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url( $subscription->get_edit_order_url() ); ?>" 
                                       class="button button-small">
                                        <?php esc_html_e( 'Изменить', 'tbank-woo-subs' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach;
    }

    /**
     * Подписки пользователя.
     *
     * @param WC_Order $order Заказ.
     */
    private function render_user_subscriptions( $order ) {
        $customer_id = $order->get_customer_id();
        
        if ( ! $customer_id || ! function_exists( 'wcs_get_users_subscriptions' ) ) {
            return;
        }
        
        ?>
        <h3><?php printf( esc_html__( 'Все подписки пользователя #%d', 'tbank-woo-subs' ), esc_html( $customer_id ) ); ?></h3>
        
        <?php
        $user_subscriptions = wcs_get_users_subscriptions( $customer_id );
        
        if ( empty( $user_subscriptions ) ) : ?>
            <p><?php esc_html_e( 'У пользователя нет подписок', 'tbank-woo-subs' ); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID', 'tbank-woo-subs' ); ?></th>
                        <th><?php esc_html_e( 'Статус', 'tbank-woo-subs' ); ?></th>
                        <th><?php esc_html_e( 'Rebill ID', 'tbank-woo-subs' ); ?></th>
                        <th><?php esc_html_e( 'Customer Key', 'tbank-woo-subs' ); ?></th>
                        <th><?php esc_html_e( 'След. платеж', 'tbank-woo-subs' ); ?></th>
                        <th><?php esc_html_e( 'Связанный заказ', 'tbank-woo-subs' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $user_subscriptions as $subscription ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( $subscription->get_edit_order_url() ); ?>">
                                    #<?php echo esc_html( $subscription->get_id() ); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html( wcs_get_subscription_status_name( $subscription->get_status() ) ); ?></td>
                            <td>
                                <?php 
                                $rebill_id = $subscription->get_meta( '_tbank_rebill_id', true );
                                echo $rebill_id ? '<code>' . esc_html( $rebill_id ) . '</code>' : '<span style="color:#d63638;">' . esc_html__( 'НЕТ', 'tbank-woo-subs' ) . '</span>';
                                ?>
                            </td>
                            <td>
                                <?php 
                                $customer_key = $subscription->get_meta( '_tbank_customer_key', true );
                                echo $customer_key ? '<code>' . esc_html( $customer_key ) . '</code>' : '<span style="color:#d63638;">' . esc_html__( 'НЕТ', 'tbank-woo-subs' ) . '</span>';
                                ?>
                            </td>
                            <td>
                                <?php
                                $next_payment = $subscription->get_time( 'next_payment' );
                                if ( $next_payment ) {
                                    echo esc_html( date_i18n( 'Y-m-d H:i:s', $next_payment ) );
                                } else {
                                    echo '<span style="color:#d63638;">' . esc_html__( 'Не запланировано', 'tbank-woo-subs' ) . '</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $parent_order_id = $subscription->get_parent_id();
                                if ( $parent_order_id ) {
                                    echo '<a href="' . esc_url( get_edit_post_link( $parent_order_id ) ) . '">#' . esc_html( $parent_order_id ) . '</a>';
                                } else {
                                    esc_html_e( 'Нет', 'tbank-woo-subs' );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    /**
     * Действия с платежом.
     *
     * @param WC_Order $order         Заказ.
     * @param bool     $check_payment Проверить платеж.
     * @param bool     $save_rebill   Сохранить Rebill ID.
     */
    private function render_actions( $order, $check_payment, $save_rebill ) {
        $payment_id = $order->get_meta( '_tbank_payment_id', true );
        
        if ( ! $payment_id ) {
            return;
        }
        
        ?>
        <h3><?php esc_html_e( 'Действия', 'tbank-woo-subs' ); ?></h3>
        
        <!-- Форма проверки статуса платежа -->
        <form method="post" action="" style="margin-bottom: 20px;">
            <?php wp_nonce_field( 'tbank_debug_action', 'tbank_debug_nonce' ); ?>
            <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
            <input type="hidden" name="check_payment" value="1">
            <button type="submit" class="button button-secondary">
                <?php printf( esc_html__( 'Проверить статус платежа %s через API', 'tbank-woo-subs' ), '<code>' . esc_html( $payment_id ) . '</code>' ); ?>
            </button>
        </form>
        
        <?php
        // Обработка проверки платежа
        if ( $check_payment && check_admin_referer( 'tbank_debug_action', 'tbank_debug_nonce' ) ) {
            $this->check_payment_status( $payment_id, $order );
        }
        
        // Обработка сохранения Rebill ID
        if ( $save_rebill && check_admin_referer( 'tbank_debug_action', 'tbank_debug_nonce' ) ) {
            $this->save_rebill_id_to_subscriptions( $payment_id, $order );
        }
    }

    /**
     * Проверка статуса платежа через API.
     *
     * @param string   $payment_id ID платежа.
     * @param WC_Order $order      Заказ.
     */
    private function check_payment_status( $payment_id, $order ) {
        $api = $this->get_api_instance();
        
        if ( ! $api ) {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'API не настроен. Проверьте Terminal Key и Password в настройках плагина.', 'tbank-woo-subs' );
            echo '</p></div>';
            return;
        }
        
        $state = $api->get_payment_state( $payment_id );
        
        echo '<h4>' . esc_html__( 'Ответ API (GetState)', 'tbank-woo-subs' ) . '</h4>';
        
        if ( ! $state ) {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'Не удалось получить ответ от API', 'tbank-woo-subs' );
            echo '</p></div>';
            return;
        }
        
        echo '<pre style="background:#f0f0f0; padding:15px; overflow:auto; max-height:300px;">';
        print_r( $state );
        echo '</pre>';
        
        if ( ! empty( $state['Success'] ) && ! empty( $state['RebillId'] ) ) {
            ?>
            <div class="notice notice-info">
                <p>
                    <strong><?php esc_html_e( 'Rebill ID доступен:', 'tbank-woo-subs' ); ?></strong>
                    <code><?php echo esc_html( $state['RebillId'] ); ?></code>
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field( 'tbank_debug_action', 'tbank_debug_nonce' ); ?>
                    <input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
                    <input type="hidden" name="save_rebill" value="1">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e( 'Сохранить Rebill ID в подписки', 'tbank-woo-subs' ); ?>
                    </button>
                </form>
            </div>
            <?php
        }
    }

    /**
     * Сохранение Rebill ID в связанные подписки.
     *
     * @param string   $payment_id ID платежа.
     * @param WC_Order $order      Заказ.
     */
    private function save_rebill_id_to_subscriptions( $payment_id, $order ) {
        $api = $this->get_api_instance();
        
        if ( ! $api ) {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'API не настроен.', 'tbank-woo-subs' );
            echo '</p></div>';
            return;
        }
        
        $state = $api->get_payment_state( $payment_id );
        
        if ( ! $state || empty( $state['Success'] ) || empty( $state['RebillId'] ) ) {
            echo '<div class="notice notice-error"><p>';
            esc_html_e( 'Не удалось получить Rebill ID из API.', 'tbank-woo-subs' );
            echo '</p></div>';
            return;
        }
        
        // Сохраняем в заказ
        $order->update_meta_data( '_tbank_rebill_id', $state['RebillId'] );
        if ( ! empty( $state['Pan'] ) ) {
            $order->update_meta_data( '_tbank_masked_pan', $state['Pan'] );
        }
        if ( ! empty( $state['ExpDate'] ) ) {
            $order->update_meta_data( '_tbank_card_exp_date', $state['ExpDate'] );
        }
        $order->save();
        
        // Сохраняем в подписки
        if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            $subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );
            
            if ( empty( $subscriptions ) ) {
                echo '<div class="notice notice-warning"><p>';
                esc_html_e( 'Rebill ID сохранен в заказ, но подписки для этого заказа не найдены!', 'tbank-woo-subs' );
                echo '</p></div>';
                return;
            }
            
            foreach ( $subscriptions as $subscription ) {
                $subscription->update_meta_data( '_tbank_rebill_id', $state['RebillId'] );
                $subscription->update_meta_data( '_tbank_customer_key', $order->get_meta( '_tbank_customer_key', true ) );
                
                if ( ! empty( $state['Pan'] ) ) {
                    $subscription->update_meta_data( '_tbank_masked_pan', $state['Pan'] );
                }
                if ( ! empty( $state['ExpDate'] ) ) {
                    $subscription->update_meta_data( '_tbank_card_exp_date', $state['ExpDate'] );
                }
                
                $subscription->save();
                
                echo '<div class="notice notice-success"><p>';
                printf(
                    esc_html__( 'Rebill ID %1$s успешно сохранен в подписку #%2$d', 'tbank-woo-subs' ),
                    '<code>' . esc_html( $state['RebillId'] ) . '</code>',
                    esc_html( $subscription->get_id() )
                );
                echo '</p></div>';
            }
        }
    }

    /**
     * Получение экземпляра API.
     *
     * @return TBank_Subs_API|null
     */
    private function get_api_instance() {
        if ( ! class_exists( 'TBank_Subs_API' ) ) {
            require_once TBANK_SUBS_PLUGIN_DIR . 'includes/class-tbank-api.php';
        }
        
        $settings = get_option( 'woocommerce_tbank_subs_settings', array() );
        
        if ( empty( $settings['terminal_key'] ) || empty( $settings['password'] ) ) {
            return null;
        }
        
        $test_mode = isset( $settings['testmode'] ) && 'yes' === $settings['testmode'];
        
        return new TBank_Subs_API(
            $settings['terminal_key'],
            $settings['password'],
            $test_mode
        );
    }
}