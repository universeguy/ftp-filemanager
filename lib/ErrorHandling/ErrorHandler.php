<?php

namespace FTPApp\ErrorHandling;

/**
 * A simple error & exception handler.
 */
class ErrorHandler
{
    /** @var callable */
    protected $handler;

    /**
     * ErrorHandling constructor.
     *
     * @param callable $handler
     */
    public function __construct(callable $handler)
    {
        if (is_callable($handler)) {
            $this->handler = $handler;
        }
    }

    public function start()
    {
        $this->setExceptionHandler($this->handler);
        $this->setErrorHandler();
    }

    public function restore()
    {
        restore_exception_handler();
        restore_error_handler();
    }

    protected function setExceptionHandler()
    {
        set_exception_handler(function ($ex) {
            http_response_code(500);
            call_user_func_array($this->handler, [$ex]);
        });
    }

    protected function setErrorHandler()
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (ini_get('error_reporting') == 0) {
                trigger_error($errstr);
                return;
            }

            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });
    }
}
