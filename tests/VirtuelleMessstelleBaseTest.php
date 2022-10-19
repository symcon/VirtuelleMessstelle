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

    public function testInitalDelta(Type $var = null)
    {
        //Set up the variables
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

        SetValue($consumer1, 5);
        SetValue($consumer2, 3);
        VM_Update($instanceID, 9);

        $this->assertEquals(1, GetValue(IPS_GetObjectIDByIdent('Result', $instanceID)));
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
            [15, 5, 8, 2],
            [5, 1, 2, 4],
            [5, 2, 1, 6],
            [4, 3, 5, 6],
            [10, 5, 0, 7]
        ];

        //Run test matrix
        for ($i = 0; $i < count($tests); $i++) {
            SetValue($consumer1, GetValue($consumer1) + $tests[$i][1]);
            SetValue($consumer2, GetValue($consumer2) + $tests[$i][2]);
            VM_Update($instanceID, $tests[$i][0]);
            $this->assertEquals($tests[$i][3], GetValue(IPS_GetObjectIDByIdent('Result', $instanceID)));
        }
    }

    public function testSyncPointsWithResult()
    {
        //Set up
        //Variables
        $consumer1 = $this->CreateActionVariable(VARIABLETYPE_INTEGER);
        $consumer2 = $this->CreateActionVariable(VARIABLETYPE_INTEGER);
        $primary = $this->CreateActionVariable(VARIABLETYPE_INTEGER);

        //Instances
        $archiveID = $this->ArchiveControlID;
        $instanceID = $this->VirtuelleMessstelle;

        //Set logging status
        AC_SetLoggingStatus($archiveID, $consumer1, true);
        AC_SetLoggingStatus($archiveID, $consumer2, true);
        AC_SetLoggingStatus($archiveID, $primary, true);

        IPS_SetConfiguration($instanceID, json_encode(
            [
                'StartDate'       => '{"year":2022,"month":1,"day":1}',
                'PrimaryPointID'  => $primary,
                'SecondaryPoints' => json_encode([[
                    'Operation'  => 0,
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

        //Set archiv data to the mesuring points
        $primaryData = [
            [
                'Avg'       => 0,
                'Duration'  => 1 * 60 * 60,
                'Max'       => 0,
                'MaxTime'   => 0,
                'Min'       => 0,
                'MinTime'   => 0,
                'TimeStamp' => strtotime('January 27 2022 11:00:00'),
            ],
            [
                'Avg'       => 11,
                'Duration'  => 1 * 60 * 60,
                'Max'       => 0,
                'MaxTime'   => 0,
                'Min'       => 0,
                'MinTime'   => 0,
                'TimeStamp' => strtotime('January 27 2022 12:00:00'),
            ],
            [
                'Avg'       => 5,
                'Duration'  => 1 * 60 * 60,
                'Max'       => 0,
                'MaxTime'   => 0,
                'Min'       => 0,
                'MinTime'   => 0,
                'TimeStamp' => strtotime('January 27 2022 13:00:00'),
            ],
        ];
        AC_StubsAddAggregatedValues($archiveID, $primary, 0, $primaryData);

        $consumer1Data = [
            [
                'Avg'       => 0,
                'Duration'  => 1 * 60 * 60,
                'Max'       => 0,
                'MaxTime'   => 0,
                'Min'       => 0,
                'MinTime'   => 0,
                'TimeStamp' => strtotime('January 27 2022 11:00:00'),
            ],
            [
                'Avg'       => 12,
                'Duration'  => 1 * 60 * 60,
                'Max'       => 0,
                'MaxTime'   => 0,
                'Min'       => 0,
                'MinTime'   => 0,
                'TimeStamp' => strtotime('January 27 2022 12:00:00'),
            ],
            [
                'Avg'       => 3,
                'Duration'  => 1 * 60 * 60,
                'Max'       => 0,
                'MaxTime'   => 0,
                'Min'       => 0,
                'MinTime'   => 0,
                'TimeStamp' => strtotime('January 27 2022 13:00:00'),
            ],
        ];
        AC_StubsAddAggregatedValues($archiveID, $consumer1, 0, $consumer1Data);

        $consumer2Data = [
            [
                'Avg'       => 0,
                'Duration'  => 1 * 60 * 60,
                'Max'       => 0,
                'MaxTime'   => 0,
                'Min'       => 0,
                'MinTime'   => 0,
                'TimeStamp' => strtotime('January 27 2022 11:00:00'),
            ],
            [
                'Avg'       => 10,
                'Duration'  => 1 * 60 * 60,
                'Max'       => 0,
                'MaxTime'   => 0,
                'Min'       => 0,
                'MinTime'   => 0,
                'TimeStamp' => strtotime('January 27 2022 12:00:00'),
            ],
            [
                'Avg'       => 5,
                'Duration'  => 1 * 60 * 60,
                'Max'       => 0,
                'MaxTime'   => 0,
                'Min'       => 0,
                'MinTime'   => 0,
                'TimeStamp' => strtotime('January 27 2022 13:00:00'),
            ],
        ];
        AC_StubsAddAggregatedValues($archiveID, $consumer2, 0, $consumer2Data);

        try {
            VM_SyncPointsWithResult($this->VirtuelleMessstelle, '{"year":2020,"month":1,"day":1}');
        } catch (\Throwable $th) {
            //ReAggregation is the last step in the sync for the result Variable
            if ($th->getMessage() != "'ReAggregateVariable' is not yet implemented") {
                throw $th;
            }
        }

        $this->assertEquals(16, GetValue(IPS_GetObjectIDByIdent('Result', $instanceID)));

        /**
         * Schema: (previous set +) primary + consumer1 - consumer2
         * first set: 0 + 0 - 0 = 0
         * second set: (0) + 11 + 12 - 10 = 13
         * third set: (13) + 5 + 3 - 5 = 16
         */
    }

    public function testResetPrimary()
    {

        //Variables
        $primary = $this->CreateActionVariable(VARIABLETYPE_INTEGER);

        //Instances
        $instanceID = $this->VirtuelleMessstelle;

        IPS_SetConfiguration($instanceID, json_encode(
            [
                'PrimaryPointID'  => $primary,
                'SecondaryPoints' => json_encode([])
            ]
        ));
        IPS_ApplyChanges($instanceID);

        $this->assertEquals($primary, json_decode(IPS_GetConfiguration($instanceID), true)['PrimaryPointID']);

        //Array = Upate runs
        //0 = Main Counter value (already the delta!)
        //3 = Expected result
        $tests = [
            [5, 5],
            [-5, 5],
            [3, 8],
        ];

        //Run test matrix
        for ($i = 0; $i < count($tests); $i++) {
            VM_Update($instanceID, $tests[$i][0]);
            $this->assertEquals($tests[$i][1], GetValue(IPS_GetObjectIDByIdent('Result', $instanceID)));
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