<?php

declare(strict_types=1);
class VirtuelleMessstelle extends IPSModule
{
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $defaultPrimaryPointID = 0;
        //If the module "SyncMySQL" is install, preselect the first
        if (IPS_ModuleExists('{7E122824-E4D6-4FF8-8AA1-2B7BB36D5EC9}')) {
            //Archive might not always be available during startup
            if (IPS_GetKernelRunlevel() == KR_READY) {
                //Fetch from database
                $options = $this->GetOptions();
                if (count($options) > 0) {
                    $defaultPrimaryPointID = $options[0]['value'];
                }
            }
        }

        //Properties
        $this->RegisterPropertyInteger('PrimaryPointID', $defaultPrimaryPointID);
        $this->RegisterPropertyString('SecondaryPoints', '[]');
        $this->RegisterPropertyString('StartDate', '{"year":0,"month":0,"day":0}');

        //Attributes
        $this->RegisterAttributeString('LastValues', '[]');
        $this->RegisterAttributeFloat('LastNegativValue', 0);
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $primaryPointID = $this->ReadPropertyInteger('PrimaryPointID');
        $secondaryPoints = json_decode($this->ReadPropertyString('SecondaryPoints'), true);

        //Update profile
        $profile = '';
        if (IPS_VariableExists($primaryPointID)) {
            $variable = IPS_GetVariable($primaryPointID);
            if ($variable['VariableType'] == VARIABLETYPE_FLOAT) {
                if ($variable['VariableCustomProfile'] != '') {
                    $profile = $variable['VariableCustomProfile'];
                } else {
                    $profile = $variable['VariableProfile'];
                }
            }
        }
        $this->RegisterVariableFloat('Result', $this->Translate('Result'), $profile, 0);

        //References
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }
        if (IPS_VariableExists($primaryPointID)) {
            $this->RegisterReference($primaryPointID);
        }
        foreach ($secondaryPoints as $line) {
            if (IPS_VariableExists($line['VariableID'])) {
                $this->RegisterReference($line['VariableID']);
            }
        }

        //Unregister all messages
        $messageList = array_keys($this->GetMessageList());
        foreach ($messageList as $message) {
            $this->UnregisterMessage($message, VM_UPDATE);
        }

        //Register message for primary point
        $this->RegisterMessage($primaryPointID, VM_UPDATE);

        $lastValues = json_decode($this->ReadAttributeString('LastValues'), true);
        foreach ($secondaryPoints as $point) {
            if (!array_key_exists($point['VariableID'], $lastValues)) {
                $lastValues[$point['VariableID']] = GetValue($point['VariableID']);
            }
        }
        $this->WriteAttributeString('LastValues', json_encode($lastValues));
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        //Structure of $Data
        //[$newValue, $isChange, $oldValue, $timeStampUpdate, $timeStampLastUpdate, $timeStampLastChange]
        if ($Message == VM_UPDATE) {
            if ($Data[1]) {
                //Pass the changed value and the last update timestampt to the update function
                $this->Update($Data[0] - $Data[2]);
            }
        }
    }

    public function Update(float $PrimaryDelta)
    {
        // Do not assume negative changes if the primary counter is reset
        if ($PrimaryDelta < 0) {
            $PrimaryDelta = 0;
        }
        $secondaryPoints = json_decode($this->ReadPropertyString('SecondaryPoints'), true);

        //Do nothing if any participating variables are missing
        if (!IPS_VariableExists($this->ReadPropertyInteger('PrimaryPointID'))) {
            return;
        }
        foreach ($secondaryPoints as $point) {
            if (!IPS_VariableExists($point['VariableID'])) {
                return;
            }
        }

        $lastValues = json_decode($this->ReadAttributeString('LastValues'), true);

        $secondaryChanges = 0;
        foreach ($secondaryPoints as $point) {
            $value = GetValue($point['VariableID']);
            $delta = 0;

            if (array_key_exists($point['VariableID'], $lastValues)) {
                $delta = $value - $lastValues[$point['VariableID']];
            }

            if ($delta < 0) {
                $delta = 0;
            }

            //Update the last value for all variables
            $lastValues[$point['VariableID']] = $value;

            // Set operator
            switch ($point['Operation']) {
                case 0:
                    $secondaryChanges += $delta;
                    break;
                case 1:
                    $secondaryChanges -= $delta;
                    break;
            }

            $this->SendDebug('Delta for ' . $point['VariableID'], strval($delta), 0);
        }

        if ($this->ReadAttributeFloat('LastNegativValue') != 0) {
            $secondaryChanges -= $this->ReadAttributeFloat('LastNegativValue');
            $this->WriteAttributeFloat('LastNegativValue', 0);
        }

        //Write updated values to attribute
        $this->WriteAttributeString('LastValues', json_encode($lastValues));

        $this->SendDebug('Result', 'Primary Delta: ' . $PrimaryDelta . ', Secondary Changes: ' . $secondaryChanges, 0);

        if (($PrimaryDelta + $secondaryChanges) < 0) {
            $this->SendDebug($this->Translate('The changes are negative'), $this->Translate('The changes are negative') . ': ' . ($PrimaryDelta + $secondaryChanges) . "\n", 0);
            $value = ($PrimaryDelta + $secondaryChanges) * -1;
            $this->WriteAttributeFloat('LastNegativValue', $value);
        } else {
            $this->SetValue('Result', $this->GetValue('Result') + ($PrimaryDelta + $secondaryChanges));
        }
    }

    public function GetConfigurationForm()
    {
        //Add options to form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        //If the module "SyncMySQL" is install, get other options
        if (IPS_ModuleExists('{7E122824-E4D6-4FF8-8AA1-2B7BB36D5EC9}')) {
            $options = $this->GetOptions();
            $jsonForm['elements'][0]['type'] = 'Select';
            $jsonForm['elements'][0]['options'] = $options;
            $jsonForm['elements'][0]['width'] = '700px';
            unset($jsonForm['elements'][0]['requiredLogging']);

            $jsonForm['elements'][1]['columns'][1]['edit']['type'] = 'Select';
            $jsonForm['elements'][1]['columns'][1]['edit']['options'] = $options;
            $jsonForm['elements'][1]['columns'][0]['edit']['width'] = '700px';
            $jsonForm['elements'][1]['columns'][1]['edit']['width'] = '700px';
            unset($jsonForm['elements'][1]['colums'][1]['edit']['requiredLogging']);
            if (count($options) > 0) {
                $jsonForm['elements'][1]['columns'][1]['add'] = $options[0]['value'];
            }
        }

        return json_encode($jsonForm);
    }

    public function SyncPointsWithResult(string $startDate, string $strategy)
    {
        $archivID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];

        $startDate = json_decode($startDate, true);
        //Look if the startDate is set
        if ($startDate['year'] != 0) {
            $startUnix = mktime(0, 0, 0, $startDate['month'], $startDate['day'], $startDate['year']);
        } else {
            echo $this->Translate('The date is not set.');
            return;
        }

        //Look if the Result is logged as counter
        $resultID = $this->GetIDForIdent('Result');
        if (!AC_GetLoggingStatus($archivID, $resultID) || AC_GetAggregationType($archivID, $resultID) !== 1) {
            AC_SetLoggingStatus($archivID, $resultID, true);
            AC_SetAggregationType($archivID, $resultID, 1);
        }

        //Look how many potential dataset where are
        switch ($strategy) {
            case 'FirstLogged':
                $archiveVariables = AC_GetAggregationVariables($archivID, true);
                $key = array_search($resultID, array_column($archiveVariables, 'VariableID'));
                $currentUnixTime = $archiveVariables[$key]['FirstTime'] === 0 ? time() : $archiveVariables[$key]['FirstTime'] - 1;
                break;
            case 'Full':
            default:
                $currentUnixTime = time();
                AC_DeleteVariableData($archivID, $resultID, 0, $currentUnixTime);
                break;
        }

        $potentialDataSets = ceil(($currentUnixTime - $startUnix) / 3600);
        $loops = ceil($potentialDataSets / 10000);

        $this->SendDebug('Count of Datasets', strval($potentialDataSets), 0);
        $this->SendDebug('Count of Loops', strval($loops), 0);

        $this->UpdateFormField('progressBar', 'visible', true);
        $this->UpdateFormField('syncButton', 'enable', false);
        if ($loops > 1) {
            $this->UpdateFormField('progressBarSections', 'maximum', $loops);
            $this->UpdateFormField('progressBarSections', 'visible', true);
        }

        //Look if the Points are set
        $primary = $this->ReadPropertyInteger('PrimaryPointID');
        $secondaryPoints = json_decode($this->ReadPropertyString('SecondaryPoints'), true);
        foreach ($secondaryPoints as $key => $point) {
            $secondaryPoints[$key]['lastValue'] = 0;
        }

        if (IPS_VariableExists($primary) && count($secondaryPoints) != 0) {
            $result = 0;
            $negatives = 0;
            //Structure ['TimeStamp' => $entry['TimeStamp'], 'Value' => $result],
            $resultLoggedValues = [];
            //Structure ['VariableID' => [ Result of GetAggregatedValues ]],
            $secondaryAggregatedValues = [];
            $secondaryChanges = 0;
            $endTime = 0;
            //Split the periods in 10000 steps
            for ($i = 0; $i < $loops; $i++) {
                $endTime = $startUnix + 36000000; //endunix = startUnix + 10000 h
                if ($endTime > $currentUnixTime) {
                    $endTime = $currentUnixTime;
                }

                $primaryData = AC_GetAggregatedValues($archivID, $primary, 0, $startUnix, $endTime, 0);
                if (count($primaryData) == 0) {
                    $startUnix = $endTime;
                    continue;
                }
                //Revers we want to start with the oldest
                $primaryData = array_reverse($primaryData);
                $primaryFirstTimeStamp = $primaryData[0]['TimeStamp'];

                //Get the logged values of the secondary points of this
                foreach ($secondaryPoints as $key => $point) {
                    $secondaryAggregatedValues[$point['VariableID']] = array_reverse(AC_GetAggregatedValues($archivID, $point['VariableID'], 0, $startUnix, $endTime, 0));
                    //need the same count on data sets
                    $variableValues = $secondaryAggregatedValues[$point['VariableID']];
                    while (floor($primaryFirstTimeStamp / 3600) < floor($variableValues[0]['TimeStamp'] / 3600)) { //The different between the timestamps are more than an hour
                        $this->SendDebug('Prepare Secondary', 'Need to adjust set');
                        array_unshift($variableValues,
                            [
                                'Avg'       => 0,
                                'Duration'  => 1 * 60 * 60,
                                'Max'       => 0,
                                'MaxTime'   => 0,
                                'Min'       => 0,
                                'MinTime'   => 0,
                                'TimeStamp' => strtotime('-1 hour', $variableValues[0]['TimeStamp']),
                            ]);
                    }
                    $secondaryAggregatedValues[$point['VariableID']] = $variableValues;
                }

                //Start Calculate
                foreach ($primaryData as $primaryKey => $entry) {
                    // Always start at 0, so we can properly log the increase, should there be previous values, this is a counter reset
                    if (($i === 0) && ($primaryKey === 0)) {
                        $resultLoggedValues[] = ['TimeStamp' => $entry['TimeStamp'], 'Value' => 0];
                    }

                    //Set the result
                    $PrimaryDelta = $entry['Avg'];
                    $secondaryChanges = 0;

                    foreach ($secondaryPoints as $key => $point) {
                        $value = $secondaryAggregatedValues[$point['VariableID']][$primaryKey]['Avg'];

                        // Set operator
                        switch ($point['Operation']) {
                            case 0:
                                $secondaryChanges += $value;
                                break;
                            case 1:
                                $secondaryChanges -= $value;
                                break;
                        }
                    }

                    if ($negatives != 0) {
                        $secondaryChanges -= $negatives;
                        $negatives = 0;
                    }

                    if (($PrimaryDelta + $secondaryChanges) < 0) {
                        $negatives = ($PrimaryDelta + $secondaryChanges) * -1;
                    } else {
                        $result += ($PrimaryDelta + $secondaryChanges);
                    }
                    $resultLoggedValues[] = ['TimeStamp' => $entry['TimeStamp'], 'Value' => $result];
                    $this->SendDebug('Date', date('H:i:s d.m.Y', $entry['TimeStamp']), 0);
                    $this->SendDebug('Delta And Changes', 'Primary Delta: ' . $PrimaryDelta . ', Secondary Changes: ' . $secondaryChanges, 0);
                    $this->SendDebug('Negatives', strval($negatives), 0);
                    $this->SendDebug('Result Past', strval($result), 0);
                }
                $this->SendDebug('Result', strval($result), 0);
                AC_AddLoggedValues($archivID, $resultID, $resultLoggedValues);
                $resultLoggedValues = [];

                $this->UpdateFormField('progressBarSections', 'current', $i);
                $this->UpdateFormField('progressBarSections', 'caption', (($i / $loops) * 100) . '%');
                $startUnix = $endTime;
            }
            //Set the Attribute
            $lastValues = json_decode($this->ReadAttributeString('LastValues'), true);
            foreach ($secondaryPoints as $key => $point) {
                if (count($secondaryAggregatedValues) > 0) {
                    $lastValues[$point['VariableID']] = end($secondaryAggregatedValues[$point['VariableID']])['Avg'];
                }
            }
            $this->WriteAttributeString('LastValues', json_encode($lastValues));
            $this->WriteAttributeFloat('LastNegativValue', $negatives);

            switch ($strategy) {
                case 'FirstLogged':
                    # Not nesseccary to set
                    break;
                case 'Full':
                default:
                    $this->SetValue('Result', $result);
                    break;
            }
        }

        $this->UpdateFormField('progressBar', 'visible', false);
        $this->UpdateFormField('syncButton', 'enable', true);
        $this->UpdateFormField('progressBarSections', 'visible', false);

        AC_ReAggregateVariable($archivID, $resultID);
    }

    //Get all logged variables as options
    private function GetOptions($filter = '')
    {
        $mysqlSyncIDs = IPS_GetInstanceListByModuleID('{7E122824-E4D6-4FF8-8AA1-2B7BB36D5EC9}');
        $idents = [];
        foreach ($mysqlSyncIDs as $mysqlSyncID) {
            if (IPS_GetInstance($mysqlSyncID)['InstanceStatus'] == IS_ACTIVE) {
                $idents = array_merge(SSQL_GetIdentList($mysqlSyncID));
            }
        }

        $addIdentifier = function ($variableID) use ($idents)
        {
            foreach ($idents as $ident) {
                if ($ident['variableid'] == $variableID) {
                    return $ident['ident'];
                }
            }
            return '';
        };

        $archiveControlID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')[0];
        $aggregationVariables = AC_GetAggregationVariables($archiveControlID, false);
        $options = [];
        $resultID = @$this->GetIDForIdent('Result');
        foreach ($aggregationVariables as $aggregationVariable) {
            if (IPS_VariableExists($aggregationVariable['VariableID']) && $aggregationVariable['VariableID'] != $resultID) {
                $jsonString['caption'] = $addIdentifier($aggregationVariable['VariableID']) . ' (' . IPS_GetName($aggregationVariable['VariableID']) . ')';
                $jsonString['value'] = $aggregationVariable['VariableID'];

                if ($filter == '' || strpos($jsonString['caption'], $filter) !== false) {
                    $options[] = $jsonString;
                }
            }
        }
        usort($options, function ($a, $b)
        {
            return strcmp($a['caption'], $b['caption']);
        });
        return $options;
    }
}
