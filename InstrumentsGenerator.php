<?php

namespace Stanford\InstrumentsGenerator;

use Logging;
use REDCap;
use ZipArchive;

/**
 * Class InstrumentsGenerator
 * @package Stanford\InstrumentsGenerator
 * @property \ZipArchive $archive
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


    private $postData = array();

    public function __construct()
    {
        parent::__construct();
        $this->setArchive(new ZipArchive());

        /**
         * Open main instruments archive file for save
         */
        if ($this->getArchive()->open(self::$instruments, ZipArchive::CREATE) !== true) {
            exit("ERROR!");
        }
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
}