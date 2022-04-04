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

        //Attributes
        $this->RegisterAttributeString('LastValues', '[]');
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
                $profile = $variable['VariableProfile'];
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

        //Write updated values to attribute
        $this->WriteAttributeString('LastValues', json_encode($lastValues));

        $this->SendDebug('Result', 'Primary Delta: ' . $PrimaryDelta . ', Secondary Changes: ' . $secondaryChanges, 0);

        $this->SetValue('Result', $this->GetValue('Result') + ($PrimaryDelta + $secondaryChanges));
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
