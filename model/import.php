<?php

namespace Stanford\InstrumentsGenerator;

/** @var \Stanford\InstrumentsGenerator\InstrumentsGenerator $module */


try {
    if (!$_POST) {
        throw new \LogicException("You cant be here");
    }

    if (!$_FILES['file']) {
        throw new \LogicException("No File posted");
    }

    $file = fopen($_FILES['file']['tmp_name'], 'r');
    if ($file) {
        $data = $module->import($file);
    } else {
        throw new \LogicException("Uploaded file is corrupted!");
    }
} catch (\LogicException $e) {
    echo $e->getMessage();
}