<?php

namespace VotreVendor\LaravelMoneyFusion\Exceptions;

use Exception;

class MoneyFusionException extends Exception
{
    // Exception personnalisée pour les erreurs MoneyFusion
    protected $message = 'Une erreur est survenue avec MoneyFusion.';
    protected $code = 500;

    public function __construct($message = null, $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    
}