<?php

namespace Stanford\InstrumentsGenerator;

use Logging;
use MetaData;
use REDCap;
use Sabre\DAV\Exception;
use ZipArchive;

/**
 * Class InstrumentsGenerator
 * @package Stanford\InstrumentsGenerator
 * @property \ZipArchive $archive
 * @property \Project $project
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

    public function __construct()
    {
        try {
            parent::__construct();
            $this->setArchive(new ZipArchive());

            if (isset($_GET['pid'])) {
                $this->setProject(new \Project(filter_var($_GET['pid'], FILTER_SANITIZE_NUMBER_INT)));
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
        foreach ($line as $field) {
            if ($i > 0) {
                $fieldName = strtolower($field) . '_' . $formName;
                //$fieldType = REDCap::getFieldType($fieldName);

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
        $data = array();
        $pointer = 0;
        $header = array();
        if ($file) {
            while (($line = fgetcsv($file, 0, ",")) !== false) {

                if ($pointer == 0) {
                    $this->data[] = implode(",", $this->processRepeatableDataHeader($line, $formName));
                    $pointer++;
                    continue;
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
                $this->data[] = implode(",", $this->removeNewLines($line));

            }
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


    public function re_value($file)
    {

        try {
            $pointer = 0;
            $data = excel_to_array($_FILES['file']['tmp_name']);
            list ($errors_array, $warnings_array, $dictionary_array) = MetaData::error_checking($data);
            $this->updateMetaData($data);
            $instrumentName = '';
            if ($file) {
                while (($line = fgetcsv($file, 0, ",")) !== false) {

                    //if instrument name changed then lets get the event name
                    if ($line[1] != $instrumentName) {
                        $instrumentName = $line[1];
                        $eventId = $this->getEventName($instrumentName);
                    }
                    //header of the file
                    if ($pointer == 0) {
                        $this->header = $line;
                        $pointer++;
                        continue;
                    }

                    /**
                     * if field type not dropdown or checkbox ignore this field
                     */
                    if (!in_array($line[3], array('dropdown', 'checkbox'))) {
                        $pointer++;
                        continue;
                    }

                    /**
                     * get current enum value of field
                     */
                    $c = $this->getProject()->metadata[$line[0]]['element_enum'];
                    $currentKeys = array_keys(parseEnum($c));
                    $currentValues = (parseEnum($c));

                    //get new field values posted via uploaded file
                    $newKeys = array_keys(parseEnum(str_replace("|", "\n", $line[5])));
                    $newValues = parseEnum(str_replace("|", "\n", $line[5]));

                    //compare two arrays of keys and their corresponding  values and check if anything got changed
                    $diff = array_diff($currentKeys, $newKeys);;
                    $diffValue = array_diff_key($currentValues, $newValues);;

                    //if both are empty nothing changed ignore this field.
                    if (empty($diff) && empty($diffValue)) {
                        continue;
                    }

                    //if number of values changed the map wont work abort
                    if (count($newValues) != count($currentValues)) {
                        throw new \LogicException("Number of posted values do not equal existing values, please review uploaded data dictionary for field: " . $line[0]);
                    }

                    //update all data related to this field.
                    $pointer = 0;
                    foreach ($newKeys as $key => $new) {
                        $old = $currentKeys[$key];

                        //values are different
                        if ($old != $new) {
                            $var = "[" . $line[0] . "]";
                            $this->updatedFields[$var][] = array($old => $new);
                            $this->updateFieldData($eventId, $line[0], $old, $new);
                        }
                        $pointer++;
                    }

                    //todo update calculated fields for new values;

                }

                //update branching logic for other fields
                $logic = $this->updateBranchingLogic($data["L"]);
                //check if logic changed update meta data again;
                $diff = array_diff($data["L"], $logic);;
                if (!empty($diff)) {
                    $data["L"] = $logic;
                    $this->updateMetaData($data);
                }

                fclose($file);

                echo "update completed successfully!";
            }
        } catch (\LogicException $e) {
            echo $e->getMessage();
            die();
        }
    }

    private function updateBranchingLogic($logic)
    {
        if (!empty($this->updatedFields)) {
            foreach ($this->updatedFields as $field => $map) {
                foreach ($logic as $key => $row) {
                    if ($row == "") {
                        continue;
                    } else {
                        //if field is in branching logic
                        if (strpos($row, $field) !== false) {
                            foreach ($map as $old => $new) {
                                //if the value is in branching logic
                                if (strpos($row, key($new)) !== false) {
                                    $x = key($new);
                                    $string = str_replace($x, $new[$x], $row);
                                    $logic[$key] = $string;
                                }
                            }
                        }
                    }
                }
            }
        }
        return $logic;
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

    private function updateFieldData($eventId, $name, $old, $new)
    {
        $param = array(
            'filterLogic' => "[$name] = '$old'",
            'return_format' => 'array',
            'events' => $eventId
        );
        $data = REDCap::getData($param);

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

    private function updateMetaData($data)
    {
        // Set up all actions as a transaction to ensure everything is done here
        db_query("SET AUTOCOMMIT=0");
        db_query("BEGIN");


        // Save data dictionary in metadata table
        $sql_errors = MetaData::save_metadata($data);

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
}