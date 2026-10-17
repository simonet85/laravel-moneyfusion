<?php

namespace Simonet85\LaravelMoneyFusion\Exceptions;

use Exception;
use Throwable;

class MoneyFusionException extends Exception
{
    // Exception personnalisée pour les erreurs MoneyFusion
    protected $message = 'Une erreur est survenue avec MoneyFusion.';
    protected $code = 500;

    public function __construct($message = null, $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message ?? $this->message, $code, $previous);
    }
}
