<?php
namespace FastValidate;

class ValidationException extends \Exception
{
    
    function __construct($message, $errors) {
        parent::__construct($message);

        $this->errors = $errors;
    }

}
