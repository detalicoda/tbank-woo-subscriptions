<?php
/**
 * API класс для работы с Т-Банк Эквайринг
 *
 * @package Tbank_Woo_Subs
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * TBank_Subs_API class.
 */
class TBank_Subs_API {

    /**
     * Терминал Key
     *
     * @var string
     */
    private $terminal_key;

    /**
     * Пароль от терминала
     *
     * @var string
     */
    private $password;

    /**
     * Тестовый режим
     *
     * @var bool
     */
    private $test_mode;

    /**
     * Базовый URL для продакшн API
     *
     * @var string
     */
    private $api_url = 'https://securepay.tinkoff.ru/v2/';

    /**
     * Базовый URL для тестового API
     *
     * @var string
     */
    private $test_api_url = 'https://rest-api-test.tinkoff.ru/v2/';

    /**
     * Таймаут запросов в секундах
     *
     * @var int
     */
    private $timeout = 30;

    /**
     * Последняя ошибка
     *
     * @var string
     */
    private $last_error = '';

    /**
     * Последний HTTP код ответа
     *
     * @var int
     */
    private $last_http_code = 0;

    /**
     * Последний полный ответ
     *
     * @var array|null
     */
    private $last_response = null;

    /**
     * Конструктор.
     *
     * @param string $terminal_key Terminal Key (если не указан, будет взят из настроек).
     * @param string $password      Пароль (если не указан, будет взят из настроек).
     * @param bool   $test_mode     Тестовый режим.
     */
    public function __construct( $terminal_key = null, $password = null, $test_mode = false ) {
        if ( $terminal_key === null || $password === null ) {
            // Пытаемся получить настройки из WooCommerce
            $settings = get_option( 'woocommerce_tbank_subs_settings', array() );
            
            $this->terminal_key = $terminal_key ?? ( $settings['terminal_key'] ?? '' );
            $this->password      = $password ?? ( $settings['password'] ?? '' );
            $this->test_mode     = $test_mode || ( isset( $settings['testmode'] ) && 'yes' === $settings['testmode'] );
        } else {
            $this->terminal_key = $terminal_key;
            $this->password     = $password;
            $this->test_mode    = $test_mode;
        }
    }

    /**
     * Получить текущий базовый URL API
     *
     * @return string
     */
    private function get_api_url() {
        return $this->test_mode ? $this->test_api_url : $this->api_url;
    }

    /**
     * Инициализация платежа.
     *
     * @param int         $order_id     ID заказа в магазине.
     * @param int         $amount       Сумма в копейках.
     * @param string      $description  Описание платежа.
     * @param string      $customer_key Уникальный ключ клиента.
     * @param bool        $recurrent    Флаг рекуррентного платежа (сохранение карты).
     * @param string|null $pay_type     Тип оплаты (O - одностадийная, T - двухстадийная).
     * @param string|null $ip           IP адрес клиента.
     * @param array       $extra_data   Дополнительные данные.
     *
     * @return array|false Ответ API или false при ошибке.
     */
    public function init_payment( $order_id, $amount, $description = '', $customer_key = '', $recurrent = false, $pay_type = 'O', $ip = null, $extra_data = array() ) {
        $params = array(
            'TerminalKey'  => $this->terminal_key,
            'Amount'       => (int) $amount,
            'OrderId'      => (string) $order_id,
            'Description'  => mb_substr( $description, 0, 250 ), // Ограничение Т-Банка
            'CustomerKey'  => $customer_key,
            'PayType'      => $pay_type,
        );

        // Устанавливаем IP клиента
        if ( $ip !== null ) {
            $params['IP'] = $ip;
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $params['IP'] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // Устанавливаем язык интерфейса
        $params['Language'] = $this->get_current_language();

        // Флаг рекуррентного платежа для сохранения карты
        if ( $recurrent ) {
            $params['Recurrent'] = 'Y';
            
            // Для первого платежа с подпиской можно установить уведомления
            if ( ! isset( $extra_data['NotificationURL'] ) ) {
                $params['NotificationURL'] = home_url( '/wc-api/tbank_subs/' );
            }
        }

        // Добавляем URL для перенаправления после оплаты
        if ( ! isset( $extra_data['SuccessURL'] ) ) {
            $params['SuccessURL'] = add_query_arg(
                array(
                    'utm_nooverride' => '1',
                    'order_id'       => $order_id,
                ),
                wc_get_checkout_url()
            );
        }

        if ( ! isset( $extra_data['FailURL'] ) ) {
            $params['FailURL'] = wc_get_checkout_url();
        }

        // Объединяем с дополнительными параметрами
        $params = array_merge( $extra_data, $params );
        
        // Удаляем пустой Receipt если он есть и пустой
        if ( isset( $params['Receipt'] ) && ( empty( $params['Receipt'] ) || $params['Receipt'] === array() ) ) {
            unset( $params['Receipt'] );
        }

        return $this->request( 'Init', $params );
    }

    /**
     * Списание средств по сохраненной карте (рекуррентный платеж).
     *
     * @param string $payment_id ID платежа из Init.
     * @param string $rebill_id  Rebill ID сохраненной карты.
     * @param array  $extra_data Дополнительные данные.
     *
     * @return array|false
     */
    public function charge_payment( $payment_id, $rebill_id, $extra_data = array() ) {
        $params = array_merge( array(
            'TerminalKey' => $this->terminal_key,
            'PaymentId'   => (string) $payment_id,
            'RebillId'    => (string) $rebill_id,
        ), $extra_data );

        return $this->request( 'Charge', $params );
    }

    /**
     * Получение статуса платежа.
     *
     * @param string $payment_id ID платежа.
     *
     * @return array|false
     */
    public function get_payment_state( $payment_id ) {
        $params = array(
            'TerminalKey' => $this->terminal_key,
            'PaymentId'   => (string) $payment_id,
        );

        return $this->request( 'GetState', $params );
    }

    /**
     * Отмена платежа (полный или частичный возврат).
     *
     * @param string   $payment_id ID платежа.
     * @param int|null $amount     Сумма возврата в копейках (null для полного возврата).
     * @param array    $extra_data Дополнительные данные.
     *
     * @return array|false
     */
    public function cancel_payment( $payment_id, $amount = null, $extra_data = array() ) {
        $params = array_merge( array(
            'TerminalKey' => $this->terminal_key,
            'PaymentId'   => (string) $payment_id,
        ), $extra_data );

        if ( $amount !== null ) {
            $params['Amount'] = (int) $amount;
        }

        return $this->request( 'Cancel', $params );
    }

    /**
     * Подтверждение двухстадийного платежа.
     *
     * @param string $payment_id ID платежа.
     * @param int    $amount     Сумма подтверждения в копейках.
     * @param array  $extra_data Дополнительные данные.
     *
     * @return array|false
     */
    public function confirm_payment( $payment_id, $amount = null, $extra_data = array() ) {
        $params = array_merge( array(
            'TerminalKey' => $this->terminal_key,
            'PaymentId'   => (string) $payment_id,
        ), $extra_data );

        if ( $amount !== null ) {
            $params['Amount'] = (int) $amount;
        }

        return $this->request( 'Confirm', $params );
    }

    /**
     * Получение списка привязанных карт клиента.
     *
     * @param string $customer_key Ключ клиента.
     *
     * @return array|false
     */
    public function get_customer_cards( $customer_key ) {
        $params = array(
            'TerminalKey' => $this->terminal_key,
            'CustomerKey' => (string) $customer_key,
        );

        return $this->request( 'GetCardList', $params );
    }

    /**
     * Удаление привязанной карты клиента.
     *
     * @param string $customer_key Ключ клиента.
     * @param string $card_id      ID карты.
     *
     * @return array|false
     */
    public function remove_customer_card( $customer_key, $card_id ) {
        $params = array(
            'TerminalKey' => $this->terminal_key,
            'CustomerKey' => (string) $customer_key,
            'CardId'      => (string) $card_id,
        );

        return $this->request( 'RemoveCard', $params );
    }

    /**
     * Проверка уведомления от Т-Банка.
     *
     * @param array $data Данные уведомления.
     *
     * @return bool Валидно ли уведомление.
     */
    public function verify_notification( $data ) {
        if ( ! isset( $data['Token'] ) || ! isset( $data['TerminalKey'] ) ) {
            return false;
        }

        $token = $data['Token'];
        unset( $data['Token'] );

        // Генерируем токен из полученных данных
        $generated_token = $this->generate_token( $data );

        return hash_equals( $generated_token, $token );
    }

    /**
     * Получение информации о терминале.
     *
     * @param string $terminal_key Ключ терминала (опционально).
     *
     * @return array|false
     */
    public function get_terminal_info( $terminal_key = null ) {
        $params = array(
            'TerminalKey' => $terminal_key ?? $this->terminal_key,
        );

        return $this->request( 'GetTerminalInfo', $params );
    }

    /**
     * Отправка запроса к API.
     *
     * @param string $method Метод API.
     * @param array  $params Параметры запроса.
     *
     * @return array|false
     */
    private function request( $method, $params ) {
        $this->last_error     = '';
        $this->last_http_code = 0;
        $this->last_response  = null;

        // Проверяем наличие необходимых данных
        if ( empty( $this->terminal_key ) ) {
            $this->last_error = 'Terminal Key не установлен';
            return $this->error_response( 'Terminal Key не установлен' );
        }

        if ( empty( $this->password ) ) {
            $this->last_error = 'Пароль не установлен';
            return $this->error_response( 'Пароль не установлен' );
        }

        // Убедимся, что TerminalKey присутствует
        if ( ! isset( $params['TerminalKey'] ) ) {
            $params['TerminalKey'] = $this->terminal_key;
        }

        // Генерируем токен
        $params['Token'] = $this->generate_token( $params );

        $url  = $this->get_api_url() . $method;
        $body = wp_json_encode( $params );

        // Логируем запрос (без пароля и токена)
        $log_params = $params;
        unset( $log_params['Token'] );
        // Пароль уже не в params, он был только для генерации токена
        $this->log( sprintf( 'Request to %s: %s', $url, wp_json_encode( $log_params ) ) );

        $args = array(
            'body'        => $body,
            'timeout'     => $this->timeout,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array(
                'Content-Type' => 'application/json; charset=utf-8',
                'Accept'       => 'application/json',
            ),
            'cookies'     => array(),
        );

        $response = wp_remote_post( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->last_error = $response->get_error_message();
            $this->log( 'WP Error: ' . $this->last_error );
            return $this->error_response( 'Ошибка соединения: ' . $this->last_error );
        }

        $this->last_http_code = wp_remote_retrieve_response_code( $response );
        $body                 = wp_remote_retrieve_body( $response );

        // Логируем ответ
        $this->log( sprintf( 'Response [%d]: %s', $this->last_http_code, $body ) );

        if ( empty( $body ) ) {
            $this->last_error = 'Пустой ответ от сервера';
            return $this->error_response( 'Пустой ответ от сервера' );
        }

        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->last_error = 'Ошибка декодирования JSON: ' . json_last_error_msg();
            $this->log( 'JSON Error: ' . $this->last_error );
            return $this->error_response( 'Неверный формат ответа от сервера' );
        }

        $this->last_response = $data;

        // Проверяем успешность запроса
        if ( ! isset( $data['Success'] ) ) {
            $this->last_error = 'Некорректный ответ API: отсутствует поле Success';
            return $this->error_response( 'Некорректный ответ API' );
        }

        if ( ! $data['Success'] ) {
            $error_code    = $data['ErrorCode'] ?? 'unknown';
            $error_message = $data['Message'] ?? 'Неизвестная ошибка';
            $error_details = $data['Details'] ?? '';
            
            $this->last_error = sprintf( '[%s] %s %s', $error_code, $error_message, $error_details );
            
            return array(
                'Success'   => false,
                'ErrorCode' => $error_code,
                'Message'   => $error_message,
                'Details'   => $error_details,
            );
        }

        return $data;
    }

    /**
     * Генерация токена для подписи запроса.
     *
     * @param array $params Параметры запроса.
     * 
     * @return string
     */
    private function generate_token( $params ) {
        // Добавляем пароль ВНУТРЬ параметров для генерации токена
        $params['Password'] = $this->password;
        
        // Сортируем параметры по ключам в алфавитном порядке
        ksort( $params );
        
        // Формируем строку для хеширования
        $token = '';
        foreach ( $params as $key => $value ) {
            // Пропускаем массивы (например, Receipt, DATA)
            if ( ! is_array( $value ) ) {
                $token .= $value;
            }
        }
        
        $token = hash( 'sha256', $token );
        
        return $token;
    }

    /**
     * Рекурсивная обработка вложенных параметров для токена
     */
    private function flatten_params( $params, $excluded_keys = array() ) {
        $result = '';
        
        ksort( $params );
        
        foreach ( $params as $key => $value ) {
            if ( in_array( $key, $excluded_keys, true ) ) {
                continue;
            }
            
            if ( $value === null || $value === '' ) {
                continue;
            }
            
            if ( is_bool( $value ) ) {
                $value = $value ? 'true' : 'false';
            }
            
            if ( is_array( $value ) ) {
                $result .= $this->flatten_params( $value, $excluded_keys );
            } else {
                $result .= $value;
            }
        }
        
        return $result;
    }

    /**
     * Рекурсивная сортировка параметров в алфавитном порядке.
     *
     * @param array $params        Параметры.
     * @param array $excluded_keys Ключи для исключения.
     *
     * @return array
     */
    private function sort_params( $params, $excluded_keys = array() ) {
        $sorted = array();
        
        foreach ( $params as $key => $value ) {
            if ( in_array( $key, $excluded_keys, true ) ) {
                $sorted[ $key ] = $value;
                continue;
            }
            
            if ( is_array( $value ) ) {
                // Рекурсивно сортируем вложенные массивы
                $sorted[ $key ] = $this->sort_params( $value, $excluded_keys );
            } else {
                $sorted[ $key ] = $value;
            }
        }
        
        ksort( $sorted, SORT_STRING );
        
        return $sorted;
    }

    /**
     * Создание ответа с ошибкой.
     *
     * @param string $message Сообщение об ошибке.
     *
     * @return array
     */
    private function error_response( $message ) {
        return array(
            'Success' => false,
            'Message' => $message,
        );
    }

    /**
     * Получение текущего языка для интерфейса оплаты.
     *
     * @return string
     */
    private function get_current_language() {
        $locale = get_locale();
        
        $language_map = array(
            'ru' => 'ru',
        );
        
        $lang_code = substr( $locale, 0, 2 );
        
        return $language_map[ $lang_code ] ?? 'en';
    }

    /**
     * Проверка валидности конфигурации API.
     *
     * @return bool
     */
    public function is_configured() {
        return ! empty( $this->terminal_key ) && ! empty( $this->password );
    }

    /**
     * Тестовое соединение с API.
     *
     * @return array|false
     */
    public function test_connection() {
        return $this->get_terminal_info();
    }

    /**
     * Получение последней ошибки.
     *
     * @return string
     */
    public function get_last_error() {
        return $this->last_error;
    }

    /**
     * Получение последнего HTTP кода.
     *
     * @return int
     */
    public function get_last_http_code() {
        return $this->last_http_code;
    }

    /**
     * Получение последнего полного ответа.
     *
     * @return array|null
     */
    public function get_last_response() {
        return $this->last_response;
    }

    /**
     * Установка тестового режима.
     *
     * @param bool $test_mode Тестовый режим.
     */
    public function set_test_mode( $test_mode ) {
        $this->test_mode = (bool) $test_mode;
    }

    /**
     * Установка таймаута запросов.
     *
     * @param int $timeout Таймаут в секундах.
     */
    public function set_timeout( $timeout ) {
        $this->timeout = absint( $timeout );
    }

    /**
     * Логирование сообщения.
     *
     * @param string $message Сообщение.
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $logger = wc_get_logger();
            $context = array( 'source' => 'tbank-subs-api' );
            $logger->debug( $message, $context );
        }
    }
}