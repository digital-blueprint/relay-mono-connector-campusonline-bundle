<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Command;

use Dbp\Relay\MonoConnectorCampusonlineBundle\Service\ConfigurationService;
use Dbp\Relay\MonoConnectorCampusonlineBundle\Service\TuitionFeeService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListTuitionFeesCommand extends Command
{
    private TuitionFeeService $tuitionFeeService;
    private ConfigurationService $config;

    /**
     * @var callable|null
     */
    private $clientHandler;
    private ?string $token;

    public function __construct(TuitionFeeService $tuitionFeeService, ConfigurationService $config)
    {
        parent::__construct();

        $this->tuitionFeeService = $tuitionFeeService;
        $this->config = $config;
        $this->clientHandler = null;
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:mono-connector-campusonline:list-tuition-fees');
        $this->setDescription('List tuition fees for a student');
        $this->addArgument('type', InputArgument::REQUIRED, 'type');
        $this->addArgument('obfuscated-id', InputArgument::REQUIRED, 'obfuscated id');
    }

    public function setClientHandler(?callable $handler, ?string $token): void
    {
        $this->clientHandler = $handler;
        $this->token = $token;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        $obfuscatedId = $input->getArgument('obfuscated-id');
        $types = $this->config->getTypes();
        if (!in_array($type, $types, true)) {
            throw new \RuntimeException('Unknown type, must be one of: '.implode(', ', $types));
        }

        if ($this->clientHandler !== null) {
            $this->tuitionFeeService->setClientHandler($this->clientHandler, $this->token);
        }
        $api = $this->tuitionFeeService->getApiByType($type, null);
        $feeList = $api->getAllFees($obfuscatedId);
        $currentFee = $api->getCurrentFee($obfuscatedId);
        $table = new Table($output);
        $table->setHeaders(['Obfuscated ID', 'Semester', 'Amount']);
        foreach ($feeList->getItems() as $item) {
            if ($currentFee->getSemesterKey() === $item->getSemesterKey()) {
                $table->addRow(new TableSeparator());
            }
            $table->addRow([$obfuscatedId, $item->getSemesterKey(), $item->getAmount()]);
            if ($currentFee->getSemesterKey() === $item->getSemesterKey()) {
                $table->addRow(new TableSeparator());
            }
        }
        $table->addRow(new TableSeparator());
        $table->addRow(['', 'Total', $feeList->getTotalAmount()]);
        $table->render();

        return Command::SUCCESS;
    }
}
