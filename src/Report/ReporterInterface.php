<?php

declare(strict_types=1);

namespace Amoifr\PicklePantherBundle\Report;

interface ReporterInterface
{
    public function addStep(StepResult $result): void;
}
