<?php

namespace Stanford\InstrumentsGenerator;

/** @var \Stanford\InstrumentsGenerator\InstrumentsGenerator $module */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require __DIR__ . "/../../../redcap_v9.2.5/Design/functions.php";
try {
    if (!$_POST) {
        throw new \LogicException("You cant be here");
    }

    if (!$_FILES['file']) {
        throw new \LogicException("No File posted");
    }

    $file = fopen($_FILES['file']['tmp_name'], 'r');
    if ($file) {
        $data = $module->re_value($file);
    } else {
        throw new \LogicException("Uploaded file is corrupted!");
    }
} catch (\LogicException $e) {
    echo $e->getMessage();
}