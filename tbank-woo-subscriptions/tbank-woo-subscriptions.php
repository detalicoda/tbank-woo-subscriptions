<?php
/**
 * Plugin Name: T-Bank для WooCommerce Subscriptions
 * Plugin URI: https://github.com/detalicoda/tbank-woo-subscriptions
 * Description: Принимает регулярные и разовые платежи через Т-Банк Эквайринг с поддержкой WooCommerce Subscriptions. Сохраняет карты клиентов для автоматических списаний.
 * Version: 2.0.0
 * Author: detalicoda
 * Author URI: https://github.com/detalicoda
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: tbank-woo-subs
 * Domain Path: /languages
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce, woocommerce-subscriptions
 * WC requires at least: 10.7
 * WCS requires at least: 8.5
 *
 * @package Tbank_Woo_Subs
 */

// Защита от прямого доступа
defined( 'ABSPATH' ) || exit;

// Определяем константы плагина
define( 'TBANK_SUBS_VERSION', '2.0.0' );
define( 'TBANK_SUBS_PLUGIN_FILE', __FILE__ );
define( 'TBANK_SUBS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TBANK_SUBS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TBANK_SUBS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'TBANK_SUBS_MIN_PHP_VERSION', '8.2' );
define( 'TBANK_SUBS_MIN_WP_VERSION', '6.9' );
define( 'TBANK_SUBS_MIN_WC_VERSION', '10.7' );
define( 'TBANK_SUBS_MIN_WCS_VERSION', '8.5' );

/**
 * Проверка системных требований при активации.
 */
function tbank_subs_activation_check() {
    $errors = array();
    
    // Проверка версии PHP
    if ( version_compare( PHP_VERSION, TBANK_SUBS_MIN_PHP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: %s: минимальная версия PHP */
            __( 'Требуется PHP версии %s или выше. Текущая версия: %s', 'tbank-woo-subs' ),
            TBANK_SUBS_MIN_PHP_VERSION,
            PHP_VERSION
        );
    }
    
    // Проверка версии WordPress
    global $wp_version;
    if ( version_compare( $wp_version, TBANK_SUBS_MIN_WP_VERSION, '<' ) ) {
        $errors[] = sprintf(
            /* translators: %s: минимальная версия WordPress */
            __( 'Требуется WordPress версии %s или выше. Текущая версия: %s', 'tbank-woo-subs' ),
            TBANK_SUBS_MIN_WP_VERSION,
            $wp_version
        );
    }
    
    if ( ! empty( $errors ) ) {
        deactivate_plugins( TBANK_SUBS_PLUGIN_BASENAME );
        wp_die(
            '<h1>' . esc_html__( 'Ошибка активации плагина', 'tbank-woo-subs' ) . '</h1>' .
            '<p>' . implode( '</p><p>', $errors ) . '</p>' .
            '<p><a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Вернуться к списку плагинов', 'tbank-woo-subs' ) . '</a></p>'
        );
    }
}

register_activation_hook( __FILE__, 'tbank_subs_activation_check' );

/**
 * Проверка наличия необходимых плагинов.
 *
 * @return bool
 */
function tbank_subs_check_dependencies() {
    // Проверяем WooCommerce
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'tbank_subs_missing_wc_notice' );
        return false;
    }
    
    // Проверяем версию WooCommerce
    if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, TBANK_SUBS_MIN_WC_VERSION, '<' ) ) {
        add_action( 'admin_notices', 'tbank_subs_outdated_wc_notice' );
        return false;
    }
    
    // Проверяем WooCommerce Subscriptions
    if ( ! class_exists( 'WC_Subscriptions' ) ) {
        add_action( 'admin_notices', 'tbank_subs_missing_wcs_notice' );
        return false;
    }
    
    // Проверяем версию WooCommerce Subscriptions
    if ( class_exists( 'WC_Subscriptions' ) && defined( 'WCS_VERSION' ) && version_compare( WCS_VERSION, TBANK_SUBS_MIN_WCS_VERSION, '<' ) ) {
        add_action( 'admin_notices', 'tbank_subs_outdated_wcs_notice' );
        return false;
    }
    
    return true;
}

/**
 * Уведомление: WooCommerce не найден.
 */
function tbank_subs_missing_wc_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'T-Bank для WooCommerce Subscriptions', 'tbank-woo-subs' ); ?></strong>
            <?php esc_html_e( 'требует установленный и активированный плагин WooCommerce.', 'tbank-woo-subs' ); ?>
        </p>
    </div>
    <?php
}

/**
 * Уведомление: устаревшая версия WooCommerce.
 */
function tbank_subs_outdated_wc_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'T-Bank для WooCommerce Subscriptions', 'tbank-woo-subs' ); ?></strong>
            <?php
            printf(
                /* translators: %s: минимальная версия WooCommerce */
                esc_html__( 'требует WooCommerce версии %s или выше. Пожалуйста, обновите WooCommerce.', 'tbank-woo-subs' ),
                esc_html( TBANK_SUBS_MIN_WC_VERSION )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Уведомление: WooCommerce Subscriptions не найден.
 */
function tbank_subs_missing_wcs_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'T-Bank для WooCommerce Subscriptions', 'tbank-woo-subs' ); ?></strong>
            <?php esc_html_e( 'требует установленный и активированный плагин WooCommerce Subscriptions.', 'tbank-woo-subs' ); ?>
        </p>
    </div>
    <?php
}

/**
 * Уведомление: устаревшая версия WooCommerce Subscriptions.
 */
function tbank_subs_outdated_wcs_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'T-Bank для WooCommerce Subscriptions', 'tbank-woo-subs' ); ?></strong>
            <?php
            printf(
                /* translators: %s: минимальная версия WooCommerce Subscriptions */
                esc_html__( 'требует WooCommerce Subscriptions версии %s или выше. Пожалуйста, обновите WooCommerce Subscriptions.', 'tbank-woo-subs' ),
                esc_html( TBANK_SUBS_MIN_WCS_VERSION )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Уведомление: не заполнены настройки плагина.
 */
function tbank_subs_settings_not_configured_notice() {
    $settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=tbank_subs' );
    ?>
    <div class="notice notice-warning">
        <p>
            <strong><?php esc_html_e( 'T-Bank для WooCommerce Subscriptions', 'tbank-woo-subs' ); ?></strong>
            <?php
            printf(
                /* translators: %s: URL настроек */
                wp_kses_post( __( 'почти готов! Перейдите на <a href="%s">страницу настроек</a>, чтобы указать Terminal Key и Пароль.', 'tbank-woo-subs' ) ),
                esc_url( $settings_url )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Автозагрузка классов плагина.
 */
spl_autoload_register( 'tbank_subs_autoload' );

function tbank_subs_autoload( $class ) {
    $prefix   = 'TBank_Subs_';
    $base_dir = TBANK_SUBS_PLUGIN_DIR . 'includes/';
    
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    
    $relative_class = substr( $class, $len );
    
    // Преобразуем имя класса в имя файла
    // Например: TBank_Subs_API -> class-tbank-subs-api.php
    $file_name = 'class-' . strtolower( str_replace( '_', '-', $relative_class ) ) . '.php';
    $file      = $base_dir . $file_name;
    
    if ( file_exists( $file ) ) {
        require_once $file;
    }
}

/**
 * Инициализация плагина.
 */
function tbank_subs_init() {
    // Проверяем зависимости
    if ( ! tbank_subs_check_dependencies() ) {
        return;
    }
    
    // Загружаем текстовый домен для переводов
    load_plugin_textdomain( 'tbank-woo-subs', false, dirname( TBANK_SUBS_PLUGIN_BASENAME ) . '/languages' );
    
    // Загружаем класс шлюза (не подпадает под автозагрузку из-за префикса WC)
    require_once TBANK_SUBS_PLUGIN_DIR . 'includes/class-wc-gateway-tbank-subs.php';
    
    // Проверяем настройки плагина
    $settings = get_option( 'woocommerce_tbank_subs_settings', array() );
    if ( empty( $settings['terminal_key'] ) || empty( $settings['password'] ) ) {
        add_action( 'admin_notices', 'tbank_subs_settings_not_configured_notice' );
    }
    
    // Регистрируем платежный шлюз
    add_filter( 'woocommerce_payment_gateways', 'tbank_subs_add_gateway' );
    
    // Добавляем ссылки на странице плагинов
    add_filter( 'plugin_action_links_' . TBANK_SUBS_PLUGIN_BASENAME, 'tbank_subs_plugin_action_links' );
    add_filter( 'plugin_row_meta', 'tbank_subs_plugin_row_meta', 10, 2 );
    
    // Вызываем хук инициализации
    do_action( 'tbank_subs_loaded' );
}

add_action( 'plugins_loaded', 'tbank_subs_init', 0 );

/**
 * Добавление платежного шлюза в WooCommerce.
 *
 * @param array $gateways Список шлюзов.
 * 
 * @return array
 */
function tbank_subs_add_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_TBank_Subs';
    return $gateways;
}

/**
 * Добавление ссылок на странице плагинов.
 *
 * @param array $links Ссылки.
 * 
 * @return array
 */
function tbank_subs_plugin_action_links( $links ) {
    $custom_links = array(
        '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=tbank_subs' ) ) . '">' . esc_html__( 'Настройки', 'tbank-woo-subs' ) . '</a>',
    );
    
    if ( ! tbank_subs_is_configured() ) {
        $custom_links[] = '<a href="https://www.tbank.ru/business/acquiring/" target="_blank" style="color: #d63638;">' . esc_html__( 'Подключить эквайринг', 'tbank-woo-subs' ) . '</a>';
    }
    
    return array_merge( $custom_links, $links );
}

/**
 * Добавление мета-данных в строке плагина.
 *
 * @param array  $links Ссылки.
 * @param string $file  Файл плагина.
 * 
 * @return array
 */
function tbank_subs_plugin_row_meta( $links, $file ) {
    if ( TBANK_SUBS_PLUGIN_BASENAME === $file ) {
        $row_meta = array(
            'docs'    => '<a href="https://developer.tbank.ru/docs/api/" target="_blank">' . esc_html__( 'Документация API', 'tbank-woo-subs' ) . '</a>',
        );
        
        return array_merge( $links, $row_meta );
    }
    
    return $links;
}

/**
 * Проверка, настроен ли плагин.
 *
 * @return bool
 */
function tbank_subs_is_configured() {
    $settings = get_option( 'woocommerce_tbank_subs_settings', array() );
    return ! empty( $settings['terminal_key'] ) && ! empty( $settings['password'] );
}

/**
 * Получение экземпляра API Т-Банка.
 *
 * @return TBank_Subs_API|null
 */
function tbank_subs_get_api() {
    if ( ! tbank_subs_is_configured() ) {
        return null;
    }
    
    $settings = get_option( 'woocommerce_tbank_subs_settings', array() );
    
    return new TBank_Subs_API(
        $settings['terminal_key'],
        $settings['password'],
        isset( $settings['testmode'] ) && 'yes' === $settings['testmode']
    );
}

/**
 * Логирование сообщений плагина.
 *
 * @param string $message Сообщение.
 * @param string $level   Уровень логирования.
 */
function tbank_subs_log( $message, $level = 'info' ) {
    $settings = get_option( 'woocommerce_tbank_subs_settings', array() );
    $logging  = isset( $settings['logging'] ) && 'yes' === $settings['logging'];
    $debug    = isset( $settings['debug'] ) && 'yes' === $settings['debug'];
    
    if ( $logging || $debug || ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
        $logger = wc_get_logger();
        $context = array( 'source' => 'tbank-woo-subs' );
        
        switch ( $level ) {
            case 'error':
                $logger->error( $message, $context );
                break;
            case 'warning':
                $logger->warning( $message, $context );
                break;
            case 'debug':
                $logger->debug( $message, $context );
                break;
            default:
                $logger->info( $message, $context );
                break;
        }
    }
}

/**
 * Обработчик деактивации плагина.
 */
function tbank_subs_deactivate() {
    // Очищаем запланированные задачи, если есть
    wp_clear_scheduled_hook( 'tbank_subs_daily_cleanup' );
    
    // Вызываем хук деактивации
    do_action( 'tbank_subs_deactivated' );
}

register_deactivation_hook( __FILE__, 'tbank_subs_deactivate' );

/**
 * Обработчик удаления плагина.
 */
function tbank_subs_uninstall() {
    // Удаляем настройки плагина
    delete_option( 'woocommerce_tbank_subs_settings' );
    
    // Очищаем логи, если используется WC Logger
    if ( class_exists( 'WC_Log_Handler_File' ) ) {
        $log_handler = new WC_Log_Handler_File();
        $log_handler->clear( 'tbank-woo-subs' );
        $log_handler->clear( 'tbank-subs-api' );
    }
    
    // Вызываем хук удаления
    do_action( 'tbank_subs_uninstalled' );
}

register_uninstall_hook( __FILE__, 'tbank_subs_uninstall' );

/**
 * Добавление ссылки на настройки в меню плагинов.
 */
function tbank_subs_add_settings_link( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=tbank_subs' ) ) . '">' . esc_html__( 'Настройки', 'tbank-woo-subs' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}

add_filter( 'plugin_action_links_' . TBANK_SUBS_PLUGIN_BASENAME, 'tbank_subs_add_settings_link' );

// =============================================================================
// Хуки совместимости
// =============================================================================

/**
 * Объявляем совместимость с HPOS (High-Performance Order Storage).
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

/**
 * Объявляем совместимость с Cart and Checkout Blocks.
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            false
        );
    }
});

/**
 * Синхронизация при создании подписки.
 */
add_action( 'woocommerce_checkout_subscription_created', function( $subscription, $order ) {
    // Проверяем, что оплата через наш шлюз
    if ( $order->get_payment_method() !== 'tbank_subs' ) {
        return;
    }
    
    // Получаем экземпляр шлюза
    $gateways = WC()->payment_gateways()->payment_gateways();
    if ( ! isset( $gateways['tbank_subs'] ) ) {
        return;
    }
    
    $gateway = $gateways['tbank_subs'];
    
    // Используем рефлексию для доступа к приватному методу
    $reflection = new ReflectionMethod( $gateway, 'sync_shipping_to_subscription' );
    $reflection->setAccessible( true );
    $reflection->invoke( $gateway, $order, $subscription );
    
}, 20, 2 );

// =============================================================================
// Отладочная информация
// =============================================================================

/**
 * Добавляем секцию в системный отчет WooCommerce
 */
add_action( 'woocommerce_system_status_report', 'tbank_subs_render_system_status_section' );

function tbank_subs_render_system_status_section() {
    $settings = get_option( 'woocommerce_tbank_subs_settings', array() );
    
    // Подготовка данных
    $fields = array(
        'version'     => array(
            'label' => __( 'Версия плагина', 'tbank-woo-subs' ),
            'value' => TBANK_SUBS_VERSION,
        ),
        'configured'  => array(
            'label' => __( 'Настроен', 'tbank-woo-subs' ),
            'value' => tbank_subs_is_configured() ? 'yes' : 'no',
            'text'  => tbank_subs_is_configured() ? __( 'Да', 'tbank-woo-subs' ) : __( 'Нет', 'tbank-woo-subs' ),
        ),
        'test_mode'   => array(
            'label' => __( 'Тестовый режим', 'tbank-woo-subs' ),
            'value' => ( $settings['testmode'] ?? 'no' ) === 'yes' ? 'yes' : 'no',
            'text'  => ( $settings['testmode'] ?? 'no' ) === 'yes' ? __( 'Да', 'tbank-woo-subs' ) : __( 'Нет', 'tbank-woo-subs' ),
        ),
        'logging'     => array(
            'label' => __( 'Логирование', 'tbank-woo-subs' ),
            'value' => ( $settings['logging'] ?? 'no' ) === 'yes' ? 'yes' : 'no',
            'text'  => ( $settings['logging'] ?? 'no' ) === 'yes' ? __( 'Да', 'tbank-woo-subs' ) : __( 'Нет', 'tbank-woo-subs' ),
        ),
        'wcs_version' => array(
            'label' => __( 'Версия WC Subscriptions', 'tbank-woo-subs' ),
            'value' => class_exists( 'WC_Subscriptions' ) ? WC_Subscriptions::$version : __( 'Не установлено', 'tbank-woo-subs' ),
        ),
    );

    ?>
    <table class="wc_status_table widefat" cellspacing="0">
        <thead>
            <tr>
                <th colspan="3" data-export-label="T-Bank Subscriptions">
                    <h2><?php _e( 'T-Bank для Подписок', 'tbank-woo-subs' ); ?></h2>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $fields as $field ) : ?>
                <tr>
                    <td data-export-label="<?php echo esc_attr( $field['label'] ); ?>">
                        <?php echo esc_html( $field['label'] ); ?>:
                    </td>
                    <td class="help"><?php echo wc_help_tip( $field['label'] ); ?></td>
                    <td>
                        <?php 
                        if ( isset( $field['value'] ) && $field['value'] === 'yes' ) {
                            echo '<mark class="yes"><span class="dashicons dashicons-yes"></span> ' . esc_html( $field['text'] ) . '</mark>';
                        } elseif ( isset( $field['value'] ) && $field['value'] === 'no' ) {
                            echo '<mark class="no">&ndash; ' . esc_html( $field['text'] ) . '</mark>';
                        } else {
                            echo esc_html( $field['value'] );
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}


/**
 * Инициализация отладочных инструментов.
 */
function tbank_subs_init_debug_tools() {
    // Загружаем отладочный инструмент только в админке
    if ( ! is_admin() ) {
        return;
    }
    
    require_once TBANK_SUBS_PLUGIN_DIR . 'includes/class-tbank-debug-tools.php';
    new TBank_Subs_Debug_Tools();
}

add_action( 'plugins_loaded', 'tbank_subs_init_debug_tools', 1 ); // Низкий приоритет для ранней загрузки