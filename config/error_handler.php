<?php
class ErrorHandler {
    private $logFile;
    private $displayErrors;
    
    public function __construct($logFile = 'logs/error.log', $displayErrors = false) {
        $this->logFile = $logFile;
        $this->displayErrors = $displayErrors;
        
        // Create logs directory
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Set error handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalError']);
    }
    
    public function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorTypes = [
            E_ERROR => 'Fatal Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated'
        ];
        
        $errorType = $errorTypes[$severity] ?? 'Unknown Error';
        $this->logError($errorType, $message, $file, $line);
        
        if ($this->displayErrors) {
            echo "<div class='alert alert-danger'>$errorType: $message in $file on line $line</div>";
        }
        
        return true;
    }
    
    public function handleException($exception) {
        $this->logError(
            'Uncaught Exception',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        if ($this->displayErrors) {
            echo "<div class='alert alert-danger'>Exception: " . $exception->getMessage() . "</div>";
        } else {
            echo "<div class='alert alert-danger'>An error occurred. Please try again later.</div>";
        }
    }
    
    public function handleFatalError() {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->logError('Fatal Error', $error['message'], $error['file'], $error['line']);
            
            if (!$this->displayErrors) {
                echo "<div class='alert alert-danger'>A fatal error occurred. Please contact support.</div>";
            }
        }
    }
    
    private function logError($type, $message, $file, $line, $trace = null) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'Unknown';
        
        $logEntry = "[$timestamp] $type: $message in $file on line $line\n";
        $logEntry .= "IP: $ip | User Agent: $userAgent | URI: $requestUri\n";
        
        if ($trace) {
            $logEntry .= "Stack Trace:\n$trace\n";
        }
        
        $logEntry .= str_repeat('-', 80) . "\n";
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public static function handleDatabaseError($e, $operation = 'Database operation') {
        error_log("$operation failed: " . $e->getMessage());
        
        if (strpos($e->getMessage(), 'Connection refused') !== false) {
            throw new Exception("Database connection failed. Please check your database server.");
        } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
            throw new Exception("Database access denied. Please check your credentials.");
        } elseif (strpos($e->getMessage(), "doesn't exist") !== false) {
            throw new Exception("Database table missing. Please run setup again.");
        } else {
            throw new Exception("$operation failed. Please try again later.");
        }
    }
    
    public static function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = ucfirst($field) . " is required";
                continue;
            }
            
            if (!empty($value)) {
                if (isset($rule['type'])) {
                    switch ($rule['type']) {
                        case 'email':
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $errors[$field] = "Invalid email format";
                            }
                            break;
                        case 'int':
                            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                                $errors[$field] = ucfirst($field) . " must be a number";
                            }
                            break;
                        case 'float':
                            if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                                $errors[$field] = ucfirst($field) . " must be a decimal number";
                            }
                            break;
                    }
                }
                
                if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                    $errors[$field] = ucfirst($field) . " must be at least {$rule['min_length']} characters";
                }
                
                if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                    $errors[$field] = ucfirst($field) . " must not exceed {$rule['max_length']} characters";
                }
                
                if (isset($rule['min']) && $value < $rule['min']) {
                    $errors[$field] = ucfirst($field) . " must be at least {$rule['min']}";
                }
                
                if (isset($rule['max']) && $value > $rule['max']) {
                    $errors[$field] = ucfirst($field) . " must not exceed {$rule['max']}";
                }
            }
        }
        
        return $errors;
    }
}

// Initialize error handler
$errorHandler = new ErrorHandler('logs/error.log', false);
?>