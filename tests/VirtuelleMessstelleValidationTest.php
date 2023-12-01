<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class VirtuelleMessstelleValidationTest extends TestCaseSymconValidation
{
    public function testValidateVirtuelleMessstelle(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateCalculatedCounterModule(): void
    {
        $this->validateModule(__DIR__ . '/../CalculatedCounter');
    }
}
