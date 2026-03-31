<?php
namespace AICA;

defined( 'ABSPATH' ) || exit;

/**
 * Claude API Client für WordPress (PHP).
 * Kommuniziert mit der Anthropic Messages API.
 */
class API_Client {

    const API_BASE    = 'https://api.anthropic.com/v1';
    const API_VERSION = '2023-06-01';

    /**
     * Modellpreise in USD pro 1 Million Tokens (Stand 2025).
     * Format: [ 'model-id' => [ 'input' => x.xx, 'output' => x.xx ] ]
     */
    const PRICING = [
        'claude-opus-4-6'   => [ 'input' => 5.00,  'output' => 25.00 ],
        'claude-sonnet-4-6' => [ 'input' => 3.00,  'output' => 15.00 ],
        'claude-haiku-4-5'  => [ 'input' => 1.00,  'output' =>  5.00 ],
    ];

    private string $api_key;
    private int    $timeout;

    public function __construct( string $api_key = '', int $timeout = 120 ) {
        $this->api_key = $api_key ?: Settings::get_api_key();
        $this->timeout = $timeout;
    }

    /**
     * Sende eine Nachricht an Claude und erhalte eine Antwort.
     *
     * @param string $system_prompt    System-Prompt für den Agenten
     * @param array  $messages         Array von [role => ..., content => ...] Nachrichten
     * @param array  $params           Optionale Parameter (model, max_tokens, temperature, thinking)
     * @return string|\WP_Error        Antworttext oder WP_Error
     */
    public function send_message( string $system_prompt, array $messages, array $params = [] ) {
        if ( empty( $this->api_key ) ) {
            return new \WP_Error( 'no_api_key', 'API-Schlüssel nicht konfiguriert.' );
        }

        $model      = $params['model']      ?? Settings::get_model();
        $max_tokens = $params['max_tokens'] ?? Settings::get_max_tokens();
        $temperature = $params['temperature'] ?? Settings::get_temperature();

        $body = [
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'system'     => $system_prompt,
            'messages'   => $messages,
        ];

        // Temperature nur bei nicht-Thinking-Modellen setzen
        if ( empty( $params['thinking'] ) ) {
            $body['temperature'] = $temperature;
        }

        // Adaptive Thinking für Opus 4.6
        if ( ! empty( $params['thinking'] ) ) {
            $body['thinking'] = [ 'type' => 'adaptive' ];
        }

        $response = wp_remote_post(
            self::API_BASE . '/messages',
            [
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $this->api_key,
                    'anthropic-version' => self::API_VERSION,
                ],
                'body' => wp_json_encode( $body ),
            ]
        );

        return $this->parse_response( $response );
    }

    /**
     * Schnell-Aufruf für einfache Prompts.
     */
    public function complete( string $prompt, array $params = [] ) {
        $system  = $params['system'] ?? 'Du bist ein hilfreicher Assistent. Antworte auf Deutsch.';
        $messages = [ [ 'role' => 'user', 'content' => $prompt ] ];
        return $this->send_message( $system, $messages, $params );
    }

    /**
     * Verbindungstest – gibt Modell-Name zurück oder WP_Error.
     */
    public function test_connection() {
        $result = $this->complete(
            'Antworte nur mit dem Wort "OK".',
            [ 'max_tokens' => 10, 'temperature' => 0 ]
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return Settings::get_model();
    }

    /**
     * Parst die WP_HTTP-Response.
     *
     * @param array|\WP_Error $response
     * @return string|\WP_Error
     */
    private function parse_response( $response ) {
        if ( is_wp_error( $response ) ) {
            return new \WP_Error(
                'http_error',
                'HTTP-Fehler: ' . $response->get_error_message()
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $data        = json_decode( $body, true );

        if ( $status_code < 200 || $status_code >= 300 ) {
            $error_msg = $data['error']['message'] ?? "HTTP {$status_code}";
            $error_type = $data['error']['type'] ?? 'api_error';
            return new \WP_Error( $error_type, $error_msg );
        }

        if ( ! isset( $data['content'] ) || empty( $data['content'] ) ) {
            return new \WP_Error( 'empty_response', 'Leere Antwort von der API.' );
        }

        // Text aus Content-Blocks extrahieren
        $text = '';
        foreach ( $data['content'] as $block ) {
            if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
                $text .= $block['text'];
            }
        }

        if ( empty( $text ) ) {
            return new \WP_Error( 'no_text', 'Kein Text in der API-Antwort.' );
        }

        return trim( $text );
    }

    /**
     * Schätzt die Kosten für eine gegebene Token-Anzahl in USD.
     *
     * @param int    $total_tokens  Gesamte Tokens (Input + Output kombiniert)
     * @param string $model         Modell-ID
     * @param float  $input_ratio   Anteil Input-Tokens (Standard: 0.4)
     * @return float Geschätzte Kosten in USD
     */
    public static function estimate_cost( int $total_tokens, string $model, float $input_ratio = 0.4 ): float {
        $pricing = self::PRICING[ $model ] ?? self::PRICING['claude-sonnet-4-6'];
        $input   = $total_tokens * $input_ratio;
        $output  = $total_tokens * ( 1 - $input_ratio );
        return ( $input * $pricing['input'] + $output * $pricing['output'] ) / 1_000_000;
    }

    /**
     * Ruft das Kontoguthaben von der Anthropic API ab.
     * Gibt ein Array mit 'credits_remaining' (float, USD) zurück,
     * oder null wenn die API den Endpunkt nicht unterstützt.
     *
     * @return array|null
     */
    public function get_account_balance(): ?array {
        if ( empty( $this->api_key ) ) {
            return null;
        }

        $response = wp_remote_get(
            self::API_BASE . '/account',
            [
                'timeout' => 15,
                'headers' => [
                    'x-api-key'         => $this->api_key,
                    'anthropic-version' => self::API_VERSION,
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( $status < 200 || $status >= 300 ) {
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) ) {
            return null;
        }

        return $data;
    }

    /**
     * Gibt Nutzungsstatistiken der letzten Anfrage zurück.
     */
    public function get_last_usage(): array {
        return $this->last_usage ?? [];
    }

    private array $last_usage = [];

    /**
     * Erweiterte Version mit Usage-Tracking.
     */
    public function send_message_tracked( string $system_prompt, array $messages, array $params = [] ): array {
        if ( empty( $this->api_key ) ) {
            return [
                'success' => false,
                'error'   => 'API-Schlüssel nicht konfiguriert.',
                'text'    => '',
                'usage'   => [],
            ];
        }

        $model      = $params['model']      ?? Settings::get_model();
        $max_tokens = $params['max_tokens'] ?? Settings::get_max_tokens();

        $body = [
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'system'     => $system_prompt,
            'messages'   => $messages,
        ];

        if ( empty( $params['thinking'] ) ) {
            $body['temperature'] = $params['temperature'] ?? Settings::get_temperature();
        } else {
            $body['thinking'] = [ 'type' => 'adaptive' ];
        }

        $response = wp_remote_post(
            self::API_BASE . '/messages',
            [
                'timeout' => $this->timeout,
                'headers' => [
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $this->api_key,
                    'anthropic-version' => self::API_VERSION,
                ],
                'body' => wp_json_encode( $body ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
                'text'    => '',
                'usage'   => [],
            ];
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $raw_body    = wp_remote_retrieve_body( $response );
        $data        = json_decode( $raw_body, true );

        if ( $status_code < 200 || $status_code >= 300 ) {
            $error_msg = $data['error']['message'] ?? "HTTP {$status_code}";
            return [
                'success' => false,
                'error'   => $error_msg,
                'text'    => '',
                'usage'   => [],
            ];
        }

        $text = '';
        foreach ( ( $data['content'] ?? [] ) as $block ) {
            if ( 'text' === ( $block['type'] ?? '' ) ) {
                $text .= $block['text'];
            }
        }

        $usage = $data['usage'] ?? [];
        $this->last_usage = $usage;

        return [
            'success'    => true,
            'text'       => trim( $text ),
            'usage'      => $usage,
            'stop_reason' => $data['stop_reason'] ?? 'end_turn',
        ];
    }
}
