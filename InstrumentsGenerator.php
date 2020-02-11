<?php

namespace Stanford\InstrumentsGenerator;

ini_set("memory_limit", "-1");
set_time_limit(0);

use Logging;
use MetaData;
use REDCap;
use Sabre\DAV\Exception;
use ZipArchive;

define("FIELD_NAME", 0);
define("FIELD_LABEL", 1);
define("INSTRUMENT_NAME", 2);
define("CURRENT_VALUE", 3);
define("NEW_VALUE", 4);
define("CURRENT_LABEL", 5);
define("NEW_LABEL", 6);
/**
 * Class InstrumentsGenerator
 * @package Stanford\InstrumentsGenerator
 * @property \ZipArchive $archive
 * @property \Project $project
 * @property bool $autoValue
 * @property string $alternativePK
 * @property int $alternativePKIndex
 * @property array $alternativePKMap
 * @property boolean $appendInstrumentName
 */
class InstrumentsGenerator extends \ExternalModules\AbstractExternalModule
{

    private static $API_TOKEN = "CA321EE1BFF5D0B534DA1ABFF47AF3D9";

    private static $supportedDataType = [
        'text',
        'notes',
        'radio',
        'dropdown',
        'select',
        'calc',
        'file',
        'checkbox',
        'yesno',
        'truefalse',
        'descriptive',
        'slider'
    ];

    private static $instruments = 'instruments.zip';

    private $archive;

    private static $dateFormat = 'datetime_seconds_ymd';

    private $header = array();

    private $data = array();

    private $recordCounter = array();

    private $project;

    private $postData = array();

    private $updatedFields = array();

    private $autoValue;

    private $alternativePK;

    private $alternativePKIndex;

    private $alternativePKMap;

    private $appendInstrumentName;

    public function __construct()
    {
        try {
            parent::__construct();
            $this->setArchive(new ZipArchive());

            if (isset($_GET['pid'])) {
                $this->setProject(new \Project(filter_var($_GET['pid'], FILTER_SANITIZE_NUMBER_INT)));
            }

            # in case the imported data does not have REDCap PK in it. user will define another record identifier (like MRN) which will allow us to pull associate record_id from it
            if (isset($_POST['alternativePK']) && $_POST['alternativePK'] != "") {
                $this->setAlternativePK(filter_var($_POST['alternativePK'], FILTER_SANITIZE_STRING));
            }

            # define wither to append instrument name or not to generated fields names
            if (isset($_POST['appendInstrumentName']) && $_POST['appendInstrumentName'] != "") {
                $this->setAppendInstrumentName(filter_var($_POST['appendInstrumentName'], FILTER_SANITIZE_STRING));
            }


            /**
             * Open main instruments archive file for save
             */
            if ($this->getArchive()->open(self::$instruments, ZipArchive::CREATE) !== true) {
                exit("ERROR!");
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @return bool
     */
    public function isAppendInstrumentName()
    {
        return $this->appendInstrumentName;
    }

    /**
     * @param bool $appendInstrumentName
     */
    public function setAppendInstrumentName($appendInstrumentName)
    {
        $this->appendInstrumentName = $appendInstrumentName;
    }

    /**
     * @return array
     */
    public function getAlternativePKMap()
    {
        return $this->alternativePKMap;
    }

    /**
     * @param array $alternativePKMap
     */
    public function setAlternativePKMap($alternativePKMap)
    {
        $this->alternativePKMap = $alternativePKMap;
    }

    /**
     * @return int
     */
    public function getAlternativePKIndex()
    {
        return $this->alternativePKIndex;
    }

    /**
     * @param int $alternativePKIndex
     */
    public function setAlternativePKIndex($alternativePKIndex)
    {
        $this->alternativePKIndex = $alternativePKIndex;
    }

    /**
     * @return string
     */
    public function getAlternativePK()
    {
        return $this->alternativePK;
    }

    /**
     * @param string $alternativePK
     */
    public function setAlternativePK($alternativePK)
    {
        $this->alternativePK = $alternativePK;
    }


    /**
     * @return bool
     */
    public function isAutoValue()
    {
        return $this->autoValue;
    }

    /**
     * @param bool $autoValue
     */
    public function setAutoValue($autoValue)
    {
        $this->autoValue = $autoValue;
    }


    /**
     * @return \Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param \Project $project
     */
    public function setProject($project)
    {
        $this->project = $project;
    }


    /**
     * @return ZipArchive
     */
    public function getArchive()
    {
        return $this->archive;
    }

    /**
     * @param ZipArchive $archive
     */
    public function setArchive($archive)
    {
        $this->archive = $archive;
    }


    public function process($file)
    {
        $data = array();
        $formName = '';
        $pointer = 0;
        $header = array();
        if ($file) {
            while (($line = fgetcsv($file, 1000, ",")) !== false) {

                if ($pointer == 0) {
                    $header = implode(",", $line);
                    $pointer++;
                    continue;
                }


                $line = $this->cleanSpecialCases($line);
                $line = $this->attachFormNameToVariableName($line);

                /**
                 * No numeric values for variable name use variable label instead
                 */
                if (preg_match('/^\d/', $line[0]) === 1) {
                    $line[0] = strtolower($line[4]);
                    $line = $this->attachFormNameToVariableName($line);
                }
                /**
                 * when form changed lets download zip file $pointer  to ignore header data
                 */
                if ($this->doesFormNameChange($formName, $line[1]) && $pointer > 0) {
                    $this->saveToMainArchive($data, $formName . '.zip');
                    $formName = $line[1];
                    $data = [];
                    $data[] = $header;
                } elseif ($formName == '' && $pointer > 0) {
                    $formName = $line[1];
                    $data[] = $header;
                }
                /**
                 * wrap each element with qoutes
                 */
                array_walk($line, function (&$x) {
                    if ($x != '') {
                        $x = '"' . $x . '"';
                    }
                });

                //$line is an array of the csv elements

                $data[] = implode(",", $line);
                $pointer++;
            }
            $this->saveToMainArchive($data, $formName . '.zip');
            fclose($file);
            $this->downloadFile(self::$instruments);
        }
    }

    private function cleanSpecialCases($line)
    {
        /**
         * special case if data type is date convert into text
         */
        if ($line[3] == 'date') {
            $line[3] = 'text';
            $line[7] = self::$dateFormat;
        }

        if ($line[3] == 'number' || $line[3] == 'calculated-age') {
            $line[3] = 'text';
            $line[7] = 'number';
        }

        if ($line[3] == 'calculated') {
            $line[3] = 'calc';
        }

        if ($line[3] == 'checkbox-group') {
            $line[3] = 'checkbox';
        }

        if ($line[3] == 'comment' || $line[3] == 'html-document') {
            $line[3] = 'text';
        }

        if ($line[3] == 'composite-select-group' || $line[3] == 'radio-group' || $line[3] == 'select' || $line[3] == 'composite-radio-group' || $line[3] == 'composite-checkbox-group' || $line[3] == 'person-list') {
            $line[3] = 'select';
        }

        /**
         * in case datatype is not of the supported REDCap datatypes change it to text and add note to importer to review it.
         */
        if (!in_array($line[3], self::$supportedDataType)) {

            $line[4] = $line[4] . "($line[3] IS NOT SUPPORTED IN REDCAP. PLEASE REVIEW)";
            $line[3] = 'text';
        }
        return $line;
    }

    /**
     * @param array $line
     * @return array
     */
    private function attachFormNameToVariableName($line)
    {
        /**
         * finally attach form name to the variable name for uniqueness
         */
        $line[0] = $line[0] . '_' . $line[1];
        return $line;
    }

    private function doesFormNameChange($currentForm, $newForm)
    {
        if ($currentForm != '' && $currentForm != $newForm) {
            return true;
        }
        return false;
    }

    private function saveToMainArchive($array, $foldername = "achieve.zip")
    {

        $output = implode("\n", $array);

        // Create zip file
        $zip = new ZipArchive;
        // Start writing to zip file
        if ($zip->open($foldername, ZipArchive::CREATE) !== true) {
            exit("ERROR!");
        }
        // Add OriginID.txt to zip file
        $zip->addFromString("OriginID.txt", SERVER_NAME);
        // Add data dictionary to zip file
        $zip->addFromString("instrument.csv", $output);

        // Done adding to zip file
        $zip->close();

        /**
         * after being saved in zip lets add it to main archive
         */
        $this->getArchive()->addFile($foldername, $foldername);
    }


    private function downloadFile($filename)
    {
        // Download file and then delete it from the server
        header('Pragma: anytextexeptno-cache', true);
        header('Content-Type: application/octet-stream"');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filename));
        ob_end_flush();
        readfile_chunked($filename);
        unlink($filename);
    }

    private function processRepeatableDataHeader($line, $formName)
    {

        $i = 0;
        $fields = REDCap::getFieldNames($formName);
        foreach ($line as $index => $field) {

            # if we do not have REDCap identifier we need to get the defined identifier index
            if ($this->getAlternativePK()) {
                if ($this->getAlternativePK() == $field) {
                    $this->setAlternativePKIndex($index);

                    # now change the field value to the REDCap PK
                    $field = REDCap::getRecordIdField();
                }
            }

            if ($i > 0) {
                if ($this->isAppendInstrumentName()) {
                    $fieldName = strtolower($field) . '_' . $formName;
                    //$fieldType = REDCap::getFieldType($fieldName);
                } else {
                    $fieldName = strtolower($field);
                }


            } else {
                $fieldName = strtolower($field);
            }
            $line[$i] = $fieldName;
            /**
             * if checkbox then add its index to listener array to add new column to its values
             */
            /*if($fieldType == "checkbox"){
                $this->checkboxIndexesForData[] = $i;
            }else{
                $this->dataHeader[] = $fieldName;
            }*/
            $i++;
        }

        $line[] = 'redcap_repeat_instance';
        $line[] = 'redcap_repeat_instrument';

        return $line;
    }

    public function processRepeatableData($file, $formName)
    {
        $pointer = 0;
        $pkField = REDCap::getRecordIdField();
        if ($this->getAlternativePK()) {
            $fields = array($pkField, $this->getAlternativePK());
            $param = array(
                'fields' => $fields,
                'events' => $this->getProject()->firstEventId
            );
            $all = REDCap::getData($param);
        }

        if ($file) {
            while (($line = fgetcsv($file, 0, ",")) !== false) {

                if ($pointer == 0) {
                    $this->data[] = implode(",", $this->processRepeatableDataHeader($line, $formName));
                    $pointer++;
                    continue;
                }

                # if we are using another identifier then make sure to replace it with correct REDCap pk
                if ($this->getAlternativePK()) {
                    $id = null;
                    $identifier = $line[$this->getAlternativePKIndex()];
                    $map = $this->getAlternativePKMap();
                    if (!isset($map[$identifier])) {
                        foreach ($all as $record) {
                            if ($record[$this->getProject()->firstEventId][$this->getAlternativePK()] == $identifier) {
                                $id = $record[$this->getProject()->firstEventId][$pkField];
                            }
                        }
                        if (is_null($id)) {
                            $id = $identifier . ' Has no PK in this project';
                        }

                        $map[$identifier] = $id;

                        $this->setAlternativePKMap($map);
                    }
                    $line[$this->getAlternativePKIndex()] = $map[$identifier];
                }

                /**
                 * track how many record appeared
                 */
                if (isset($this->recordCounter[$line[0]])) {
                    $this->recordCounter[$line[0]] = $this->recordCounter[$line[0]] + 1;
                } else {
                    $this->recordCounter[$line[0]] = 1;
                }

                /**
                 * add value for redcap_repeat_instance
                 */
                $line[] = $this->recordCounter[$line[0]];

                /**
                 * lastly add instrument name
                 */
                $line[] = $formName;
                $pointer++;
                //fputcsv($fp, $this->removeNewLines($line));
                $this->data[] = implode(",", $this->removeNewLines($line));
            }
            //fclose($fp);
            fclose($file);
            $this->downloadCSVFile($formName . '.csv', $this->data);
        }
    }

    private function removeNewLines($line)
    {
        /**
         * wrap each element with qoutes
         */
        array_walk($line, function (&$x) {
            if ($x != '') {
                $x = str_replace(array("\n", "\r"), ' ', $x);;
            }
        });
        return $line;
    }

    private function downloadCSVFile($filename, $data)
    {
        $data = implode("\n", $data);
        // Download file and then delete it from the server
        header('Pragma: anytextexeptno-cache', true);
        header('Content-Type: application/octet-stream"');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $data;
        exit();
    }


    public function import($file)
    {
        $data = array();
        $pointer = 0;
        $header = array();
        if ($file) {
            while (($line = fgetcsv($file, 0, ",")) !== false) {

                if ($pointer == 0) {
                    $this->header = $line;
                    $pointer++;
                    continue;
                }

                $i = 0;
                foreach ($this->header as $item) {
                    $final_array[$item] = $line[$i];
                    $i++;
                }
                $data = json_encode(array($final_array));

                $fields = array(
                    'token' => self::$API_TOKEN,
                    'content' => 'record',
                    'format' => 'json',
                    'type' => 'flat',
                    'data' => $data,
                );
                $this->curl($fields);
            }
            fclose($file);
        }
    }

    private function curl($fields)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, SERVER_NAME . '/api/');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set to TRUE for production use
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

        $output = json_decode(curl_exec($ch));
        if ($output->error) {
            throw new \LogicException($output->error);
        }
        print $output;
        curl_close($ch);
    }

    private function reValueMetaData($values, $types)
    {
        $pointer = 0;
        foreach ($values as $key => $value) {
            if ($value == "" || !in_array($types[$key], array("dropdown", "checkbox"))) {
                continue;
            }
            $currentKeys = array_keys(parseEnum(str_replace("|", "\n", $value)));
            $currentValues = parseEnum(str_replace("|", "\n", $value));
            $string = '';
            $newPointer = 1;
            for ($i = 0; $i < count($currentKeys); $i++) {
                $string .= $newPointer . ' , ' . $currentValues[$currentKeys[$i]] . '|';
                $newPointer++;
            }
            $string = substr($string, 0, -1);
            $values[$key] = $string;
            $pointer++;
        }
        return $values;
    }

    public function re_value($file)
    {
        try {
            $pointer = 0;
            $valueUpdate = false;
            if ($file) {
                while (($line = fgetcsv($file, 0, ",")) !== false) {

                    //header of the file
                    if ($pointer == 0) {
                        $this->header = $line;
                        $pointer++;
                        continue;
                    }

                    /**
                     * if field type not dropdown or checkbox ignore this field
                     */
                    if (!in_array($this->getProject()->metadata[$line[FIELD_NAME]]['element_type'],
                        array('select', 'checkbox'))) {
                        $pointer++;
                        continue;
                    }

                    //if no field name them abort
                    if ($line[FIELD_NAME] == "") {
                        throw new \LogicException("No field name define for line " . $pointer);
                    }

                    //check we already processed this field before
                    if (!$this->data[$line[FIELD_NAME]]) {

                        $this->data[$line[FIELD_NAME]]['enum'] = $this->getProject()->metadata[$line[FIELD_NAME]]['element_enum'];
                        $this->data[$line[FIELD_NAME]]['labels'] = parseEnum($this->data[$line[FIELD_NAME]]['enum']);
                        $this->data[$line[FIELD_NAME]]['keys'] = array_keys(parseEnum($this->data[$line[FIELD_NAME]]['enum']));

                        //escape one time for database insert.
                        $this->data[$line[FIELD_NAME]]['enum'] = db_escape($this->getProject()->metadata[$line[FIELD_NAME]]['element_enum']);
                    }


                    //if instrument name changed then lets get the event name
                    if ($line[INSTRUMENT_NAME] == '') {
                        throw new \LogicException("No instrument defined for " . $line[FIELD_NAME]);
                    } else {
                        $eventId = $this->getEventName($line[INSTRUMENT_NAME]);
                    }


                    //if passed current value does not exist as a value in data dictionary.
                    if ($line[CURRENT_VALUE] != '' && !in_array($line[CURRENT_VALUE],
                            $this->data[$line[FIELD_NAME]]['keys'])) {
                        throw new \LogicException("Current Value " . $line[CURRENT_VALUE] . " does not exist in " . $line[FIELD_NAME]);
                    }

                    //if passed current label does not exist as a label in data dictionary.
                    if ($line[CURRENT_LABEL] != '' && $line[NEW_LABEL] != '' && !in_array($line[CURRENT_LABEL],
                            $this->data[$line[FIELD_NAME]]['labels'])) {
                        throw new \LogicException("Current Label " . $line[CURRENT_LABEL] . " does not exist in " . $line[FIELD_NAME]);
                    }

                    //new values MUST be numeric
                    if ($line[NEW_VALUE] != '' && !is_numeric($line[NEW_VALUE])) {
                        throw new \LogicException("New Value " . $line[NEW_VALUE] . " for field " . $line[FIELD_NAME] . " MUST be numeric");
                    }

                    /**
                     * if field label changed we can update it directly
                     */
                    if ($line[FIELD_LABEL] != $this->getProject()->metadata[$line[FIELD_NAME]]['description']) {
                        $this->updateFieldLabel($line);
                    }

                    //if the value for field got changed.
                    if ($line[NEW_VALUE] != '' && $line[NEW_VALUE] != $line[CURRENT_VALUE]) {

                        //regex to search for current value in the element_enum then replace it with new one
                        $regex = '/' . $line[CURRENT_VALUE] . '\,\s*/i';
                        $this->data[$line[FIELD_NAME]]['enum'] = preg_replace($regex, $line[NEW_VALUE] . ", ",
                            $this->data[$line[FIELD_NAME]]['enum']);

                        //then we need to update the data
                        $valueUpdate = true;
                    }

                    //if the label for field got changed.
                    if ($line[NEW_LABEL] != '' && $line[NEW_LABEL] != $line[CURRENT_LABEL]) {
                        //regex to search for current label in the element_enum then replace it with new one
                        $regex = '/\,\s' . $line[CURRENT_LABEL] . '/i';
                        $this->data[$line[FIELD_NAME]]['enum'] = preg_replace($regex, ", " . $line[NEW_LABEL],
                            $this->data[$line[FIELD_NAME]]['enum']);

                    }

                    //if something update for values or label then update field in the database
                    if ($this->getProject()->metadata[$line[FIELD_NAME]]['element_enum'] != $this->data[$line[FIELD_NAME]]['enum']) {
                        $this->updateFieldEnum($line, $this->data[$line[FIELD_NAME]]['enum']);

                        if ($valueUpdate) {
                            //update records that has the information.
                            $this->updateFieldData($eventId, $line[FIELD_NAME], $line[CURRENT_VALUE], $line[NEW_VALUE]);

                            //update survey records that has current field information.
                            $this->updateFieldSurveyLogic($eventId, $line[FIELD_NAME], $line[CURRENT_VALUE],
                                $line[NEW_VALUE]);

                            //update branching logic for other fields
                            $this->updateBranchingLogic($line);
                        }
                    }

                    $pointer++;
                }

                fclose($file);

                echo "update completed successfully!";
            }
        } catch (\LogicException $e) {
            echo $e->getMessage();
            die();
        }
    }

    private function updateFieldEnum($field, $enum)
    {
        // Set up all actions as a transaction to ensure everything is done here
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        // Save data dictionary in metadata table
        $sql_errors = db_query("UPDATE redcap_metadata set element_enum = '" . $enum . "' WHERE field_name = '" . $field[FIELD_NAME] . "' AND project_id = '" . $this->getProjectId() . "'");

        // Display any failed queries to Super Users, but only give minimal info of error to regular users
        if (!$sql_errors) {

            // ERRORS OCCURRED, so undo any changes made
            db_query("ROLLBACK");
            // Set back to previous value
            db_query("SET AUTOCOMMIT=1");

            throw new \LogicException(implode(",", $sql_errors));

        } else {
            // COMMIT CHANGES
            db_query("COMMIT");
            // Set back to previous value
            db_query("SET AUTOCOMMIT=1");

        }
    }

    private function updateBranchingLogic($field)
    {
        $sql = "SELECT branching_logic, field_name FROM redcap_metadata where branching_logic LIKE '%" . $field[FIELD_NAME] . "%" . $field[CURRENT_VALUE] . "%' AND project_id = '" . $this->getProjectId() . "'";

        $result = db_query($sql);
        $count = db_num_rows($result);

        if ($count > 0) {
            while ($row = db_fetch_assoc($result)) {
                $logic = str_replace($field[CURRENT_VALUE], '"' . $field[NEW_VALUE] . '"', $row['branching_logic']);
                $sql = "UPDATE redcap_metadata set branching_logic = '$logic' WHERE event_id = '" . $row['field_name'] . "' AND project_id = '" . $this->getProjectId() . "'";
                $result = db_query($sql);
                if (!$result) {
                    throw new \LogicException("Cant update branching logic for " . $row['field_name']);
                }
            }
        }
    }

    private function getEventName($instrument)
    {
        $events = $this->getProject()->eventsForms;
        foreach ($events as $id => $event) {
            if (in_array($instrument, $event)) {
                return $id;
            }
        }
        return false;
    }

    private function updateFieldSurveyLogic($eventId, $name, $old, $new)
    {
        $sql = "SELECT ss_id, survey_id, condition_logic FROM redcap_surveys_scheduler where event_id = $eventId AND condition_logic LIKE '%$name%$old%'";

        $result = db_query($sql);
        $count = db_num_rows($result);

        if ($count > 0) {
            while ($row = db_fetch_assoc($result)) {
                $logic = str_replace($old, '"' . $new . '"', $row['condition_logic']);
                $ss_id = $row['ss_id'];
                $survey_id = $row['survey_id'];
                $sql = "UPDATE redcap_surveys_scheduler set condition_logic = '$logic' WHERE event_id = '$eventId' AND ss_id = '$ss_id' AND survey_id = '$survey_id'";
                $result = db_query($sql);
                if (!$result) {
                    throw new \LogicException("Cant update conditional logic in survey scheduler for " . $name);
                }
            }
        }

    }

    /**
     * update field saved data from old value to the new one.
     * @param $eventId
     * @param $name
     * @param $old
     * @param $new
     */
    private function updateFieldData($eventId, $name, $old, $new)
    {
        $param = array(
            'filterLogic' => "[$name] = '$old'",
            'return_format' => 'array',
            'events' => $eventId
        );
        $data = REDCap::getData($param);

        if ($data) {
            foreach ($data as $id => $record) {
                $d[REDCap::getRecordIdField()] = $id;
                $d[$name] = $new;
                //$d['redcap_event_name'] = $eventId;
                $response = \REDCap::saveData('json', json_encode(array($d)));
                if ($response['errors']) {
                    if (is_array($response['errors'])) {
                        $error = implode(", ", $response['errors']);
                    } else {
                        $error = $response['errors'];
                    }
                    throw new \LogicException($error);
                }
            }
        }
    }

    private function updateFieldLabel($field)
    {
        // Set up all actions as a transaction to ensure everything is done here
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");

        // Save data dictionary in metadata table
        $sql_errors = db_query("UPDATE redcap_metadata set element_lable = " . $field[FIELD_LABEL] . " WHERE field_name = " . $field[FIELD_NAME] . " AND project_id = " . $this->getProjectId());

        // Display any failed queries to Super Users, but only give minimal info of error to regular users
        if (count($sql_errors) > 0) {

            // ERRORS OCCURRED, so undo any changes made
            db_query("ROLLBACK");
            // Set back to previous value
            db_query("SET AUTOCOMMIT=1");

            echo implode(",", $sql_errors);

        } else {
            // COMMIT CHANGES
            db_query("COMMIT");
            // Set back to previous value
            db_query("SET AUTOCOMMIT=1");

        }
    }

    /**
     * generate csv file for all dropdown and checkboxes values in a project.
     *
     */
    public function generate()
    {

        $data[] = 'field_name,field_label,instrument_name,current_value,new_value,current_label,new_label';
        foreach ($this->getProject()->metadata as $name => $field) {
            $pointer = 0;
            if (!in_array($field['element_type'],
                array('select', 'checkbox'))) {
                continue;
            }

            $instrument = $field['form_name'];
            $labels = parseEnum($field['element_enum']);
            foreach ($labels as $key => $label) {
                $data[] = '' . $name . ',,' . $instrument . ',' . $key . ',,"' . $label . '",';
            }
        }
        $this->downloadCSVFile('sample_data.csv', $data);
        header('Location: ' . $_SERVER['HTTP_REFERER']);
    }
}