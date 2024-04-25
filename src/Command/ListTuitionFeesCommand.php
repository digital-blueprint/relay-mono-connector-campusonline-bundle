<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Command;

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

    public function __construct(TuitionFeeService $tuitionFeeService)
    {
        parent::__construct();

        $this->tuitionFeeService = $tuitionFeeService;
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:mono-connector-campusonline:list-tuition-fees');
        $this->setDescription('List tuition fees for a student');
        $this->addArgument('payment-type', InputArgument::REQUIRED, 'payment type');
        $this->addArgument('obfuscated-id', InputArgument::REQUIRED, 'obfuscated id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $paymentType = $input->getArgument('payment-type');
        $obfuscatedId = $input->getArgument('obfuscated-id');
        $types = $this->tuitionFeeService->getTypes();
        if (!in_array($paymentType, $types, true)) {
            throw new \RuntimeException('Unknown payment type, must be one of: '.implode(', ', $types));
        }

        $api = $this->tuitionFeeService->getApiByType($paymentType, null);
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
