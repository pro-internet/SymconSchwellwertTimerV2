<?
require(__DIR__ . "\\pimodule.php");

    // Klassendefinition
    class SymconSchwellwertTimerV2 extends PISymconModule {

        public $Details = true;

        // Eigene Variablen 
        public $Status;
        public $Targets;
        public $sensoren;
        public $Events;

        public $detailsIndex = 3;

        // Der Konstruktor des Moduls
        // Überschreibt den Standard Kontruktor von IPS
        public function __construct($InstanceID) {
            // Diese Zeile nicht löschen
            parent::__construct($InstanceID);



            // Selbsterstellter Code
        }
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {

            parent::Create();

            $dtVar = $this->searchObjectByName("Details");
            $dtVal = GetValue($dtVar);

            if ($dtVal == true) {
                SetValue($dtVar, false);
            }

            $this->setExcludedHide();

            $this->show($this->searchObjectByName("Einstellungen"));

            $this->deleteObject($this->searchObjectByName("Details"));

            $this->deleteObject($this->searchObjectByRealName("Details onChange", $this->searchObjectByName("Events")));
            
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
           
            parent::ApplyChanges();

            $this->onSensorChange();

            $this->checkSensorVars();

            $this->createRealOnChangeEvents(array($this->searchObjectByName("Verzögerung") . "|onDelayVarChange", $this->searchObjectByName("Nachlauf") . "|onTrailingVarChange"), $this->searchObjectByName("Events"));

        }

        protected function onDetailsChangeShow () {

            $prnt = IPS_GetParent($this->InstanceID);
            $name = IPS_GetName($this->InstanceID);

            $this->linkFolderMobile($this->Sensoren, $name . " Sensoren", $prnt);
            $this->linkFolderMobile($this->Targets, $name . " Geräte", $prnt);

        }

        protected function onDetailsChangeHide () {

            $prnt = IPS_GetParent($this->InstanceID);
            $name = IPS_GetName($this->InstanceID);

            $this->deleteObject($this->searchObjectByName($name . " Sensoren", $prnt));
            $this->deleteObject($this->searchObjectByName($name . " Geräte", $prnt));

        }

        protected function setGlobalized () {

            return array("Targets", "Sensoren", "Status", "Events");

        }

        protected function setExcludedHide() {

            return array($this->AutomatikVar, $this->SperreVar, $this->detailsVar, $this->Status);

        }

        protected function setExcludedShow () {

            return array("script", "instance", $this->searchObjectByName("Nachlauf aktiv"));

        }

        public function CheckVariables () {

            $switches = $this->createSwitches(array("Automatik|false|0", "Sperre|false|1", "Status|false|2"));

            $verzögerung = $this->checkInteger("Verzögerung", false, "", 3, $this->secondsToTimestamp(300));
            $nachlauf = $this->checkInteger("Nachlauf", false, "", 4, $this->secondsToTimestamp(1800));

            $nachlaufAktiv = $this->checkBoolean("Nachlauf aktiv", false); // "", 0, false

            $this->activateVariableLogging($switches[0]);
            $this->activateVariableLogging($switches[1]);
            $this->activateVariableLogging($switches[2]);

            $this->activateVariableLogging($verzögerung);
            $this->activateVariableLogging($nachlauf);

            $this->addProfile($verzögerung, "~UnixTimestampTime");
            $this->addProfile($nachlauf, "~UnixTimestampTime");

            $this->addSetValue($verzögerung);
            $this->addSetValue($nachlauf);

            $this->setIcon($verzögerung, "Clock");
            $this->setIcon($nachlauf, "Clock");

            $targets = $this->checkFolder("Targets");
            $sensoren = $this->checkFolder("Sensoren");

            $this->Sensoren = $sensoren;

            $this->createOnChangeEvents(array($this->AutomatikVar . "|onAutomaticChange", $this->Status . "|onStatusChange", $this->searchObjectByName("Sperre") . "|onSperreChange"), $this->Events);

            $this->hide($targets);
            $this->hide($sensoren);
            $this->hide($nachlaufAktiv);

        }

        public function CheckScripts () {

            // Scripts checken -und erstellen
            $this->checkScript("DelayEnd", $this->prefix . "_onDelayEnd");
            $this->checkScript("Trailing", $this->prefix . "_trailing");
            $this->checkScript("onTrailingEnd", $this->prefix . "_onTrailingEnd");

        }

        public function RegisterProperties () {

            $this->RegisterPropertyInteger("Sensor1", null);
            $this->RegisterPropertyInteger("Sensor2", null);
            $this->RegisterPropertyInteger("Sensor3", null);

            $this->RegisterPropertyInteger("Sensor1Profile", 5);
            $this->RegisterPropertyInteger("Sensor2Profile", 5);
            $this->RegisterPropertyInteger("Sensor3Profile", 5);

            $this->RegisterPropertyInteger("Mode", 1);

            $this->RegisterPropertyInteger("SchwellwertMode", 1);

            $this->RegisterPropertyString("valueOn", "");
            $this->RegisterPropertyString("valueOff", "");

            $this->RegisterPropertyInteger("ScriptOn", null);
            $this->RegisterPropertyInteger("ScriptOff", null);

        }

        protected function checkSensorVars() {

            $sensor1 = $this->ReadPropertyInteger("Sensor1");
            $sensor2 = $this->ReadPropertyInteger("Sensor2");
            $sensor3 = $this->ReadPropertyInteger("Sensor3");

            $sensor1profil = $this->ReadPropertyInteger("Sensor1Profile");
            $sensor2profil = $this->ReadPropertyInteger("Sensor2Profile");
            $sensor3profil = $this->ReadPropertyInteger("Sensor3Profile");

            if ($sensor1 != null) {

                ////echo "sensoren: " . $this->sensoren . "\n";
                ////echo "Sensoren: " . $this->Sensoren . "\n";

                if (!$this->doesExist($this->searchObjectByName("Sensor 1", $this->searchObjectByName("Sensoren")))) {

                    $sensor1link = $this->linkVar($sensor1, "Sensor 1", $this->Sensoren, 0, true);

                    $sensor1schwellwert = $this->checkVar("Schwellwert 1", $this->getVarType($sensor1), "", "", 999);

                    $this->giveTresholdProfile($sensor1schwellwert, $sensor1profil, $sensor1);

                    $this->createOnChangeEvents(array($sensor1schwellwert . "|onTresholdChange", $sensor1 . "|onSensorChange"), $this->Events);

                    $sensorName = IPS_GetName($sensor1);
                    IPS_SetName($sensor1link, $sensorName);

                } else { 

                    if ($this->getTargetID($this->searchObjectByName("Sensor 1", $this->Sensoren)) != $sensor1) {

                        $this->deleteObject($this->searchObjectByName("Sensor 1", $this->Sensoren));
                        $this->deleteObject($this->searchObjectByName("Schwellwert 1"));
                        $this->deleteObject($this->searchObjectByName("onChange Sensor 1 Schwellwert", $this->Events));
                        $this->deleteObject($this->searchObjectByName("onChange " . IPS_GetName($sensor1), $this->Events));

                        $sensor1link = $this->linkVar($sensor1, "Sensor 1", $this->Sensoren, 0, true);

                        $sensor1schwellwert = $this->checkVar("Schwellwert 1", $this->getVarType($sensor1), "", "", 999); 

                        $this->addSetValue($sensor1schwellwert);

                        $this->giveTresholdProfile($sensor1schwellwert, $sensor1profil, $sensor1);

                        $this->createOnChangeEvents(array($sensor1schwellwert . "|onTresholdChange", $sensor1 . "|onSensorChange"), $this->Events);

                        $sensorName = IPS_GetName($sensor1);
                        IPS_SetName($sensor1link, $sensorName);
 
                    } else {

                        $this->giveTresholdProfile($this->searchObjectByName("Schwellwert 1"), $sensor1profil, $sensor1);   

                    } 

                }
                

            } else {


                if ($this->doesExist($this->searchObjectByName("Sensor 1", $this->searchObjectByName("Sensoren")))) {
                    
                    $this->deleteObject($this->searchObjectByName("Sensor 1", $this->searchObjectByName("Sensoren")));
                    $this->deleteObject($this->searchObjectByName("Schwellwert 1"));
                    $this->deleteObject($this->searchObjectByName("onChange Sensor 1 Schwellwert", $this->Events));
                    $this->deleteObject($this->searchObjectByName("onChange " . IPS_GetName($sensor1), $this->Events));

                }

            }

            if ($sensor2 != null) {

                if (!$this->doesExist($this->searchObjectByName("Sensor 2", $this->Sensoren))) {

                    $sensor2link = $this->linkVar($sensor2, "Sensor 2", $this->Sensoren, 0, true);

                    $sensor2schwellwert = $this->checkVar("Schwellwert 2", $this->getVarType($sensor2), "", "", 999);

                    $this->addSetValue($sensor2schwellwert);

                    $this->giveTresholdProfile($sensor2schwellwert, $sensor2profil, $sensor2);

                    $this->createOnChangeEvents(array($sensor2schwellwert . "|onTresholdChange", $sensor2 . "|onSensorChange"), $this->Events);

                    $sensorName = IPS_GetName($sensor2);
                    IPS_SetName($sensor2link, $sensorName);

                } else {

                    if ($this->getTargetID($this->searchObjectByName("Sensor 2", $this->Sensoren)) != $sensor2) {
                        
                        $this->deleteObject($this->searchObjectByName("Sensor 2", $this->Sensoren));
                        $this->deleteObject($this->searchObjectByName("Schwellwert 2"));
                        $this->deleteObject($this->searchObjectByName("onChange Sensor 2 Schwellwert", $this->Events));
                        $this->deleteObject($this->searchObjectByName("onChange " . IPS_GetName($sensor2), $this->Events));

                        $sensor2link = $this->linkVar($sensor2, "Sensor 2", $this->Sensoren, 0, true);

                        $sensor2schwellwert = $this->checkVar("Schwellwert 2", $this->getVarType($sensor2), "", "", 999);

                        $this->giveTresholdProfile($sensor2schwellwert, $sensor2profil, $sensor2);

                        $this->createOnChangeEvents(array($sensor2schwellwert . "|onTresholdChange", $sensor2 . "|onSensorChange"), $this->Events);

                        $sensorName = IPS_GetName($sensor2);
                        IPS_SetName($sensor2link, $sensorName);

                    } else {

                        $this->giveTresholdProfile($this->searchObjectByName("Schwellwert 2"), $sensor2profil, $sensor2);

                    }

                }
                

            } else {

                if ($this->doesExist($this->searchObjectByName("Sensor 2", $this->searchObjectByName("Sensoren")))) {
                    $this->deleteObject($this->searchObjectByName("Sensor 2", $this->searchObjectByName("Sensoren")));
                    $this->deleteObject($this->searchObjectByName("Schwellwert 2"));
                    $this->deleteObject($this->searchObjectByName("onChange Sensor 2 Schwellwert", $this->Events));
                    $this->deleteObject($this->searchObjectByName("onChange " . IPS_GetName($sensor2), $this->Events));
                }

            } 

            if ($sensor3 != null) {

                if (!$this->doesExist($this->searchObjectByName("Sensor 3", $this->Sensoren))) {

                    $sensor3link = $this->linkVar($sensor3, "Sensor 3", $this->Sensoren, 0, true);

                    $sensor3schwellwert = $this->checkVar("Schwellwert 3", $this->getVarType($sensor3), "", "", 999);

                    $this->giveTresholdProfile($sensor3schwellwert, $sensor3profil, $sensor3);

                    $this->createOnChangeEvents(array($sensor3schwellwert . "|onTresholdChange", $sensor3 . "|onSensorChange"), $this->Events);

                    $sensorName = IPS_GetName($sensor3);
                    IPS_SetName($sensor3link, $sensorName);

                } else {

                    if ($this->getTargetID($this->searchObjectByName("Sensor 3", $this->Sensoren)) != $sensor3) {
                        
                        $this->deleteObject($this->searchObjectByName("Sensor 3", $this->Sensoren));
                        $this->deleteObject($this->searchObjectByName("Schwellwert 3"));
                        $this->deleteObject($this->searchObjectByName("onChange Sensor 3 Schwellwert", $this->Events));
                        $this->deleteObject($this->searchObjectByName("onChange " . IPS_GetName($sensor3), $this->Events));

                        $sensor3link = $this->linkVar($sensor3, "Sensor 3", $this->Sensoren, 0, true);

                        $sensor3schwellwert = $this->checkVar("Schwellwert 3", $this->getVarType($sensor3), "", "", 999);

                        $this->addSetValue($sensor3schwellwert);

                        $this->giveTresholdProfile($sensor3schwellwert, $sensor3profil, $sensor3);

                        $this->createOnChangeEvents(array($sensor3schwellwert . "|onTresholdChange", $sensor3 . "|onSensorChange"), $this->Events);

                        $sensorName = IPS_GetName($sensor3);
                        IPS_SetName($sensor3link, $sensorName);

                    } else {

                        $this->giveTresholdProfile($this->searchObjectByName("Schwellwert 3"), $sensor3profil, $sensor3);

                    }

                }
                

            } else {


                if ($this->doesExist($this->searchObjectByName("Sensor 3", $this->searchObjectByName("Sensoren")))) {
                    $this->deleteObject($this->searchObjectByName("Sensor 3", $this->searchObjectByName("Sensoren")));
                    $this->deleteObject($this->searchObjectByName("Schwellwert 3"));
                    $this->deleteObject($this->searchObjectByName("onChange Sensor 3 Schwellwert", $this->Events));
                    $this->deleteObject($this->searchObjectByName("onChange " . IPS_GetName($sensor3), $this->Events));
                }

            }

        }

        protected function setNeededProfiles () {
            return array("Lux", "Temperature_F", "Temperature_C", "Wattage");
        }

        protected function giveTresholdProfile ($tresholdVar, $tresholdVal, $source) {


            // Grad_F
            if ($tresholdVal == 2) {

                if ($this->getVarType($tresholdVar) == $this->varTypeByName("float") && $this->getVarProfile($tresholdVar) != $this->prefix . ".Temperature_F_float") {

                    $this->addProfile($tresholdVar, $this->prefix . ".Temperature_F_float");
                    $this->setIcon($tresholdVar, "Temperature");

                }

                if ($this->getVarType($tresholdVar) == $this->varTypeByName("int") && $this->getVarProfile($tresholdVar) != $this->prefix . ".Temperature_F_int") {

                    $this->addProfile($tresholdVar, $this->prefix . ".Temperature_F_int");
                    $this->setIcon($tresholdVar, "Temperature");

                }

            }

            // Grad_C
            if ($tresholdVal == 1) {

                if ($this->getVarType($tresholdVar) == $this->varTypeByName("float") && $this->getVarProfile($tresholdVar) != $this->prefix . ".Temperature_C_float") {

                    $this->addProfile($tresholdVar, $this->prefix . ".Temperature_C_float");
                    $this->setIcon($tresholdVar, "Temperature"); 

                }

                if ($this->getVarType($tresholdVar) == $this->varTypeByName("int") && $this->getVarProfile($tresholdVar) != $this->prefix . ".Temperature_C_int") {

                    $this->addProfile($tresholdVar, $this->prefix . ".Temperature_C_int");
                    $this->setIcon($tresholdVar, "Temperature");

                }

            }

            // Lux
            if ($tresholdVal == 3) {


                if ($this->getVarType($tresholdVar) == $this->varTypeByName("float") && $this->getVarProfile($tresholdVar) != $this->prefix . ".Lux_float") {

                    $this->addProfile($tresholdVar, $this->prefix . ".Lux_float");
                    $this->setIcon($tresholdVar, "Sun");

                }

                if ($this->getVarType($tresholdVar) == $this->varTypeByName("int") && $this->getVarProfile($tresholdVar) != $this->prefix . ".Lux_int") {

                    ////echo "Give " . $tresholdVar . " Profile Nr. " . $tresholdVal . " (int)";

                    $this->addProfile($tresholdVar, $this->prefix . ".Lux_int");
                    $this->setIcon($tresholdVar, "Sun");

                }

            }

            // Wattage
            if ($tresholdVal == 4) {

                if ($this->getVarType($tresholdVar) == $this->varTypeByName("float") && $this->getVarProfile($tresholdVar) != $this->prefix . ".Wattage_float") {

                    $this->addProfile($tresholdVar, $this->prefix . ".Wattage_float");
                    $this->setIcon($tresholdVar, "Electricity");

                }

                if ($this->getVarType($tresholdVar) == $this->varTypeByName("int") && $this->getVarProfile($tresholdVar) != $this->prefix . ".Wattage_int") {

                    $this->addProfile($tresholdVar, $this->prefix . ".Wattage_int");
                    $this->setIcon($tresholdVar, "Electricity");

                }

            }

            if ($tresholdVal == 5) {

                $profile = $this->getVarProfile($source);
                $this->addProfile($tresholdVar, $profile);

            }

        }

        ###################################################################################################################################

        public function onAutomaticChange () {

            $automatik = $this->AutomatikVar;
            $automatikVal = GetValue($automatik);

            if (!$automatikVal) {

                $statusVal = GetValue($this->searchObjectByName("Status"));

                $this->deleteObject($this->searchObjectByName("Verzögerung Timer"));
                $this->deleteObject($this->searchObjectByName("Nachlauf Timer"));

                SetValue($this->searchObjectByName("Nachlauf aktiv"), false);

                if ($statusVal != false) {

                    SetValue($this->searchObjectByName("Status"), false);

                }

                IPS_SetScriptTimer($this->searchObjectByName("onTrailingEnd"), 0);
                IPS_SetScriptTimer($this->searchObjectByName("Trailing"), 0);
                IPS_SetScriptTimer($this->searchObjectByName("DelayEnd"), 0);



            } else {

                $this->onSensorChange();

            }

        }

        public function onDetailsChange () {

            $senderVar = $_IPS['VARIABLE'];
            $senderVal = GetValue($senderVar);
            $excludeHide = $this->setExcludedHide();
            $excludeShow = $this->setExcludedShow();
    
            $specialShow = $this->setSpecialShow();
            $specialHide = $this->setSpecialHide();
    
            // Wenn ausblenden
            if ($senderVal == false) {
    
                $this->hideAll($excludeHide);
    
                if (count($specialHide)) {
    
                    foreach ($specialHide as $id) {
                        $this->hide($id);
                    }
    
                }
    
                $this->onDetailsChangeHide();
    
            } else {
    
                $this->showAll($excludeShow);
    
                foreach ($specialShow as $id) {
                    $this->show($id);
                }
    
                $this->onDetailsChangeShow();
    
            }
    
        }

        public function onStatusChange () {

            //$var = $_IPS['VARIABLE'];
            //$val = GetValue($var);

            //echo "onStautschange executed \n";

            $var  = $this->searchObjectByName("Status");
            $val = GetValue($var);

            $automatikVar = $this->searchObjectByName("Automatik");
            $automatikVal = GetValue($automatikVar);

            $mode = $this->ReadPropertyInteger("Mode");
            $valueOn = $this->ReadPropertyString("valueOn");
            $valueOff = $this->ReadPropertyString("valueOff");

            $scriptOn = $this->ReadPropertyInteger("ScriptOn");
            $scriptOff = $this->ReadPropertyInteger("ScriptOff");

            $sperre = $this->searchObjectByName("Sperre");
            $sperre = GetValue($sperre);

            if (!$automatikVal || $sperre) {

                return;

            }
 
            if ($val) {

                // Bei Überschreitung
                if ($mode == 1) {

                    if ($valueOn != "") {

                        $this->setAllInLinkList($this->searchObjectByName("Targets"), intval($valueOn));

                    }

                    if ($scriptOn != null) {
                        IPS_RunScript($scriptOn);
                    } 

                // Bei Unterschreitung
                } else if ($mode == 2) {

                    if ($valueOff != "") {

                        $this->setAllInLinkList($this->searchObjectByName("Targets"), intval($valueOff));

                    }

                    if ($scriptOff != null) {
                        IPS_RunScript($scriptOff);
                    } 

                } 

            } else {

                if ($mode == 1) {

                    if ($valueOff != "") {

                        $this->setAllInLinkList($this->searchObjectByName("Targets"), intval($valueOff));

                    }

                    if ($scriptOff != null) {
                        IPS_RunScript($scriptOff);
                    } 

                } else if ($mode == 2) {

                    if ($valueOn != "") {

                        $this->setAllInLinkList($this->searchObjectByName("Targets"), intval($valueOn));

                    }

                    if ($scriptOn != null) {
                        IPS_RunScript($scriptOn);
                    } 

                }

            }

        }

        public function onTresholdChange () {

            $this->onSensorChange();

        }

        public function onSensorChange () {

            //$senderVar = $_IPS['VARIABLE'];
            //$senderVal = GetValue($senderVar);
            $automatik = GetValue($this->AutomatikVar);
            $statusVar = $this->Status;
            $statusVal = GetValue($statusVar);

            $sensor1 = $this->getValueIfPossible($this->getTargetID($this->searchObjectByName("Sensor 1", $this->Sensoren)));
            $sensor2 = $this->getValueIfPossible($this->getTargetID($this->searchObjectByName("Sensor 2", $this->Sensoren)));
            $sensor3 = $this->getValueIfPossible($this->getTargetID($this->searchObjectByName("Sensor 3", $this->Sensoren)));

            $sensor1schwellwert = $this->getValueIfPossible($this->searchObjectByName("Schwellwert 1"));
            $sensor2schwellwert = $this->getValueIfPossible($this->searchObjectByName("Schwellwert 2"));
            $sensor3schwellwert = $this->getValueIfPossible($this->searchObjectByName("Schwellwert 3"));

            // $sensor1 = $this->nullToNull($sensor1);
            // $sensor2 = $this->nullToNull($sensor2);
            // $sensor3 = $this->nullToNull($sensor3);

            // $sensor1schwellwert = $this->nullToNull($sensor1schwellwert);
            // $sensor2schwellwert = $this->nullToNull($sensor2schwellwert);
            // $sensor3schwellwert = $this->nullToNull($sensor3schwellwert);

            $trailingActive = $this->getValueIfPossible($this->searchObjectByName("Nachlauf aktiv"));

            $currentStatus = GetValue($this->searchObjectByName("Status"));

            if ($automatik) {

                $newStatus = false;

                if ($this->ReadPropertyInteger("SchwellwertMode") == 1) {

                    if ($sensor1schwellwert <= $sensor1 && $sensor2schwellwert <= $sensor2 && $sensor3schwellwert <= $sensor3) {

                        ////echo "Sensor1: " . $sensor1 . " Sensor1Schwellwert: " . $sensor1schwellwert;
                        $newStatus = true;
    
                    }

                } else if ($this->ReadPropertyInteger("SchwellwertMode") == 2){

                    $sens1valid = false;
                    $sens2valid = false;
                    $sens3valid = false;

                    if ($sensor1schwellwert != null && $sensor1 != null) {

                        if ($sensor1schwellwert <= $sensor1) {
                            $sens1valid = true;
                        }

                    }

                    if ($sensor2schwellwert != null && $sensor2 != null) {

                        if ($sensor2schwellwert <= $sensor2) {
                            $sens2valid = true;
                        }

                    }

                    if ($sensor3schwellwert != null && $sensor3 != null) {

                        if ($sensor3schwellwert <= $sensor3) {
                            $sens3valid = true;
                        }

                    }

                    if ($sens1valid || $sens2valid || $sens3valid) {

                        $newStatus = true;
    
                    }

                }

                // if (!$fromtrailing) {

                    if ($trailingActive && $newStatus) {

                        $nachlauf = GetValue($this->searchObjectByName("Nachlauf"));
                        IPS_SetScriptTimer($this->searchObjectByName("onTrailingEnd"), $this->timestampToSeconds($nachlauf));

                        return;
                    }                    

                    if ($newStatus) {

                        if (!$this->doesExist($this->searchObjectByName("Verzögerung Timer"))) {

                            $verzögerung = GetValue($this->searchObjectByName("Verzögerung"));
    
                            IPS_SetScriptTimer($this->searchObjectByName("DelayEnd"), $this->timestampToSeconds($verzögerung));
    
                            $this->setIcon($this->getFirstChildFrom($this->searchObjectByName("DelayEnd")), "Clock");
    
                            $this->linkVar($this->getFirstChildFrom($this->searchObjectByName("DelayEnd")), "Verzögerung Timer", null, "last", true);

                        }
    
                    } else {
    
                        $this->deleteObject($this->searchObjectByName("Verzögerung Timer"));
    
                        IPS_SetScriptTimer($this->searchObjectByName("DelayEnd"), 0);
    
                    }

                // } 


            }

        }

        public function onDelayEnd () {

            $nachlauf = GetValue($this->searchObjectByName("Nachlauf"));

            SetValue($this->searchObjectByName("Nachlauf aktiv"), true);

            if ($this->timestampToSeconds($nachlauf) <= 15) {

                IPS_SetScriptTimer($this->searchObjectByName("Trailing"), $this->timestampToSeconds($nachlauf) - 1);

            } else {

                IPS_SetScriptTimer($this->searchObjectByName("Trailing"), 15);

            }

            $this->deleteObject($this->searchObjectByName("Verzögerung Timer"));

            $statusVal = GetValue($this->searchObjectbyName("Status"));

            if ($statusVal != true) {

                SetValue($this->Status, true);

            }

            IPS_SetScriptTimer($this->searchObjectByName("DelayEnd"), 0);

            IPS_SetScriptTimer($this->searchObjectByName("onTrailingEnd"), $this->timestampToSeconds($nachlauf));

            $this->linkVar($this->getFirstChildFrom($this->searchObjectByName("onTrailingEnd")), "Nachlauf Timer", null, "last", true);

            $this->setIcon($this->getFirstChildFrom($this->searchObjectByName("onTrailingEnd")), "Clock");

        }

        public function trailing () {

            //$this->onSensorChange(true);

            $automatik = GetValue($this->AutomatikVar);
            $statusVar = $this->Status;
            $statusVal = GetValue($statusVar);

            $sensor1 = $this->getValueIfPossible($this->getTargetID($this->searchObjectByName("Sensor 1", $this->Sensoren)));
            $sensor2 = $this->getValueIfPossible($this->getTargetID($this->searchObjectByName("Sensor 2", $this->Sensoren)));
            $sensor3 = $this->getValueIfPossible($this->getTargetID($this->searchObjectByName("Sensor 3", $this->Sensoren)));

            $sensor1schwellwert = $this->getValueIfPossible($this->searchObjectByName("Schwellwert 1"));
            $sensor2schwellwert = $this->getValueIfPossible($this->searchObjectByName("Schwellwert 2"));
            $sensor3schwellwert = $this->getValueIfPossible($this->searchObjectByName("Schwellwert 3"));

            // $sensor1 = $this->nullToNull($sensor1);
            // $sensor2 = $this->nullToNull($sensor2);
            // $sensor3 = $this->nullToNull($sensor3);

            // $sensor1schwellwert = $this->nullToNull($sensor1schwellwert);
            // $sensor2schwellwert = $this->nullToNull($sensor2schwellwert);
            // $sensor3schwellwert = $this->nullToNull($sensor3schwellwert);

            $trailingActive = $this->getValueIfPossible($this->searchObjectByName("Nachlauf aktiv"));

            $currentStatus = GetValue($this->searchObjectByName("Status"));

            if ($automatik) {

                $newStatus = false;

                if ($this->ReadPropertyInteger("SchwellwertMode") == 1) {

                    if ($sensor1schwellwert <= $sensor1 && $sensor2schwellwert <= $sensor2 && $sensor3schwellwert <= $sensor3) {

                        $newStatus = true;
    
                    }

                } else {

                    $sens1valid = false;
                    $sens2valid = false;
                    $sens3valid = false;

                    if ($sensor1schwellwert != null && $sensor1 != null) {

                        if ($sensor1schwellwert <= $sensor1) {
                            $sens1valid = true;
                        }

                    }

                    if ($sensor2schwellwert != null && $sensor2 != null) {

                        if ($sensor2schwellwert <= $sensor2) {
                            $sens2valid = true;
                        }

                    }

                    if ($sensor3schwellwert != null && $sensor3 != null) {

                        if ($sensor3schwellwert <= $sensor3) {
                            $sens3valid = true;
                        }

                    }

                    if ($sens1valid || $sens2valid || $sens3valid) {

                        $newStatus = true;
    
                    }

                }

            $nachlaufactive = GetValue($this->searchObjectByName("Nachlauf aktiv"));

            if ($newStatus && $nachlaufactive) {

                $nachlauf = GetValue($this->searchObjectByName("Nachlauf"));
                IPS_SetScriptTimer($this->searchObjectByName("onTrailingEnd"), $this->timestampToSeconds($nachlauf));

            }

            }
        }

        public function onTrailingEnd () {

            $statusVal = GetValue($this->searchObjectByName("Status"));

            SetValue($this->searchObjectByName("Nachlauf aktiv"), false);

            if ($statusVal != false) {

                SetValue($this->searchObjectByName("Status"), false);

            }

            $this->deleteObject($this->searchObjectByName("Nachlauf Timer"));

        }

        #####################################################################################################################################

        public function onDelayVarChange () {

            if ($this->doesExist($this->searchObjectByName("Verzögerung Timer"))) {

                $verzögerung = GetValue($this->searchObjectByName("Verzögerung"));

                IPS_SetScriptTimer($this->searchObjectByName("DelayEnd"), $this->timestampToSeconds($verzögerung));

            }

        }

        public function onTrailingVarChange () {

            if ($this->doesExist($this->searchObjectByName("Nachlauf Timer"))) {

                $trailing = GetValue($this->searchObjectByName("Nachlauf"));

                IPS_SetScriptTimer($this->searchObjectByName("onTrailingEnd"), $this->timestampToSeconds($trailing));

            }

            if ($this->doesExist($this->getFirstChildFrom($this->searchObjectByName("Trailing")))) {

                $nachlauf = GetValue($this->searchObjectByName("Nachlauf"));

                if ($this->timestampToSeconds($nachlauf) <= 15) {

                    IPS_SetScriptTimer($this->searchObjectByName("Trailing"), $this->timestampToSeconds($nachlauf) - 1);

                } else {

                    IPS_SetScriptTimer($this->searchObjectByName("Trailing"), 15);

                }

            }

        }

        public function onSperreChange () {

            $sperreVar = $this->searchObjectByName("Sperre");
            $sperreVal = GetValue($sperreVar);

            //echo "onSperreChange executed \n";

            if ($sperreVal == false) {

                $this->onStatusChange();

            }

        }

}

?>