<?php

namespace App\Repositories\Contracts\Reports;

interface ChargeReportRepositoryInterface
{
    public function runMealReport(string $start_date, string $end_date);
}