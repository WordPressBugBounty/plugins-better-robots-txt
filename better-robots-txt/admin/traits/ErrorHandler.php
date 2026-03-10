<?php
namespace Pagup\BetterRobots\Traits;

trait ErrorHandler {
    private $is_test_mode = false;
    private $test_error = '';

    /**
     * Enable test mode for development
     *
     * @param string $error_type Error type to simulate
     */
    protected function enableTestMode(string $error_type = ''): void {
        $this->is_test_mode = true;
        $this->test_error = $error_type;
    }

    /**
     * Handle common error responses for CRUD operations
     *
     * @param string $type Error type
     * @param string $message Custom error message
     * @param mixed $data Additional error data
     * @param int $status HTTP status code
     */
    protected function handleError(string $type, string $message = '', $data = null, int $status = 400): void {
        if ($this->is_test_mode) {
            $type = $this->test_error ?: $type;
        }

        $error_messages = [
            'permission' => [
                'message' => 'Permission denied: You do not have sufficient permissions',
                'status' => 403
            ],
            'nonce' => [
                'message' => 'Security verification failed',
                'status' => 401
            ],
            'not_found' => [
                'message' => 'Requested item(s) not found',
                'status' => 404
            ],
            'db_error' => [
                'message' => 'Database operation failed',
                'status' => 500
            ],
            'invalid_data' => [
                'message' => 'Invalid or missing data provided',
                'status' => 400
            ],
            'validation_error' => [
                'message' => 'Data validation failed',
                'status' => 400
            ]
        ];

        $error = isset($error_messages[$type])
            ? $error_messages[$type]
            : ['message' => 'Unknown error occurred', 'status' => 500];

        $final_message = !empty($message) ? $message : $error['message'];
        $final_status = $status ?: $error['status'];

        wp_send_json_error(
            [
                'message' => $final_message,
                'data' => $data
            ],
            $final_status
        );
    }

    /**
     * Validate common requirements for CRUD operations
     *
     * @param string $nonce_action Nonce action to verify
     * @return bool True if valid, false otherwise
     */
    protected function validateRequest(string $nonce_action = 'rt__nonce'): bool {
        if ($this->is_test_mode) {
            $this->handleError($this->test_error);
            return false;
        }

        if (!current_user_can('manage_options')) {
            $this->handleError('permission');
            return false;
        }

        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            $this->handleError('nonce');
            return false;
        }

        return true;
    }
}
