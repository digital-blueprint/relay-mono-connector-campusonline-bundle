<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;

class HealthCheck implements CheckInterface
{
    private $ldap;
    private $tuitionfee;

    public function __construct(LdapService $ldap, TuitionFeeService $tuitionfee)
    {
        $this->ldap = $ldap;
        $this->tuitionfee = $tuitionfee;
    }

    public function getName(): string
    {
        return 'mono-connector-campusonline';
    }

    private function checkMethod(string $description, callable $func): CheckResult
    {
        $result = new CheckResult($description);
        try {
            $func();
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);

            return $result;
        }
        $result->set(CheckResult::STATUS_SUCCESS);

        return $result;
    }

    public function check(CheckOptions $options): array
    {
        $results = [];
        $results[] = $this->checkMethod('Check if we can connect to the LDAP server', [$this->ldap, 'checkConnection']);
        $results[] = $this->checkMethod('Check if we can connect to the CO API', [$this->tuitionfee, 'checkConnectionNoAuth']);
        $results[] = $this->checkMethod('Check if we can authenticate with the CO API', [$this->tuitionfee, 'checkConnection']);

        return $results;
    }
}
