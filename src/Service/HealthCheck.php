<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

use Dbp\Relay\BasePersonBundle\API\PersonProviderInterface;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;
use Dbp\Relay\CoreBundle\Rest\Options;
use Symfony\Component\HttpFoundation\Response;

class HealthCheck implements CheckInterface
{
    private $tuitionfee;
    private PersonProviderInterface $personProvider;

    public function __construct(PersonProviderInterface $personProvider, TuitionFeeService $tuitionfee)
    {
        $this->personProvider = $personProvider;
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
        $results[] = $this->checkMethod('Check if we can connect to the CO API', [$this->tuitionfee, 'checkConnectionNoAuth']);
        $results[] = $this->checkMethod('Check if we can authenticate with the CO API', [$this->tuitionfee, 'checkConnection']);
        $results[] = $this->checkMethod('Check if the CO API can talk to its backend', [$this->tuitionfee, 'checkBackendConnection']);
        $results[] = $this->checkMethod('Check if the person provider is configured to deliver the title', [$this, 'checkPersonProvider']);

        return $results;
    }

    /**
     * @throws \Throwable
     */
    public function checkPersonProvider()
    {
        try {
            $personProviderOptions = [];
            $this->personProvider->getPerson('foo', Options::requestLocalDataAttributes(
                $personProviderOptions, [TuitionFeeService::PERSON_TITLE_LOCAL_DATA_ATTRIBUTE]));
        } catch (\Throwable $exception) {
            if ($exception instanceof ApiError === false || $exception->getStatusCode() !== Response::HTTP_NOT_FOUND) {
                throw $exception;
            }
        }
    }
}
