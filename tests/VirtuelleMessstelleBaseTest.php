<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';

use PHPUnit\Framework\TestCase;

if (!defined('VARIABLETYPE_INTEGER')) {
    define('VARIABLETYPE_INTEGER', 1);
}
if (!defined('KR_READY')) {
    define('KR_READY', 10103);
}
if (!defined('VM_UPDATE')) {
    define('VM_UPDATE', 10603);
}

class VirtuelleMessstelleBaseTest extends TestCase
{
    protected $ArchiveControlID;
    protected $VirtuelleMessstelle;
    protected $Consumer;

    protected function setUp(): void
    {
        //Reset
        IPS\Kernel::reset();

        //Register our core stubs for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');

        //Register our library we need for testing
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');

        //Create instances
        $this->ArchiveControlID = IPS_CreateInstance('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        $this->VirtuelleMessstelle = IPS_CreateInstance('{3BA1D968-A160-9C01-CB21-FD09B154535A}');

        parent::setUp();
    }

    public function testBaseFunctionality()
    {

        //Variables
        $consumer1 = $this->CreateActionVariable(VARIABLETYPE_INTEGER);
        $consumer2 = $this->CreateActionVariable(VARIABLETYPE_INTEGER);
        $primary = $this->CreateActionVariable(VARIABLETYPE_INTEGER);

        //Instances
        $archiveID = $this->ArchiveControlID;
        $instanceID = $this->VirtuelleMessstelle;

        IPS_SetConfiguration($instanceID, json_encode(
            [
                'PrimaryPointID'  => $primary,
                'SecondaryPoints' => json_encode([[
                    'Operation'  => 1,
                    'VariableID' => $consumer1
                ], [
                    'Operation'  => 1,
                    'VariableID' => $consumer2
                ]])
            ]
        ));
        IPS_ApplyChanges($instanceID);

        IPS_EnableDebug($instanceID, 600);

        $this->assertEquals($primary, json_decode(IPS_GetConfiguration($instanceID), true)['PrimaryPointID']);

        //Array = Upate runs
        //0 = Main Counter value (already the delta!)
        //1 = Consumer 1 Counter value (substract)
        //2 = Consumer 2 Counter value (substract)
        //3 = Expected result
        $tests = [
            [15, 5, 8, 15],
            [5, 1, 2, 17],
            [5, 2, 1, 19],
            [10, 5, 0, 24]
        ];

        //Run test matrix
        for ($i = 0; $i < count($tests); $i++) {
            SetValue($consumer1, GetValue($consumer1) + $tests[$i][1]);
            SetValue($consumer2, GetValue($consumer2) + $tests[$i][2]);
            VM_Update($instanceID, $tests[$i][0]);
            $this->assertEquals($tests[$i][3], GetValue(IPS_GetObjectIDByIdent('Result', $instanceID)));
        }
    }

    protected function CreateActionVariable(int $VariableType)
    {
        $variableID = IPS_CreateVariable($VariableType);
        $scriptID = IPS_CreateScript(0 /* PHP */);
        IPS_SetScriptContent($scriptID, 'SetValue($_IPS[\'VARIABLE\'], $_IPS[\'VALUE\']);');
        IPS_SetVariableCustomAction($variableID, $scriptID);
        return $variableID;
    }
}
