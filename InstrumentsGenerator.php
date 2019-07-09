<?php

namespace Stanford\InstrumentsGenerator;

use Logging;
use REDCap;
use ZipArchive;

class InstrumentsGenerator extends \ExternalModules\AbstractExternalModule
{

    public function process($file)
    {
        $data = array();
        $formName = '';
        $pointer = 0;
        $header = array();
        if ($file) {
            while (($line = fgetcsv($file, 1000, "\t")) !== false) {

                if ($pointer == 0) {
                    $header = implode(",", $line);
                    $pointer++;
                    continue;
                }

                /**
                 * when form changed lets download zip file $pointer  to ignore header data
                 */
                if ($this->doesFormNameChange($formName, $line[1]) && $pointer > 0) {
                    $this->saveAndDownload($data, $formName . '.zip');
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
            fclose($file);
            return $data;
        }
    }

    private function doesFormNameChange($currentForm, $newForm)
    {
        if ($currentForm != '' && $currentForm != $newForm) {
            return true;
        }
        return false;
    }

    private function saveAndDownload($array, $foldername = "achieve.zip")
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
        // Logging
        // Download file and then delete it from the server
        header('Pragma: anytextexeptno-cache', true);
        header('Content-Type: application/octet-stream"');
        header('Content-Disposition: attachment; filename="' . $foldername . '"');
        header('Content-Length: ' . filesize($foldername));
        ob_end_flush();
        readfile_chunked($foldername);
        unlink($foldername);
    }

}