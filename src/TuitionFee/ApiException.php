<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\TuitionFee;

class ApiException extends \Exception
{
    /**
     * The HTTP status code of the API response leading to this error, in case there was any.
     *
     * @var ?int
     */
    public $httpStatusCode;
}
