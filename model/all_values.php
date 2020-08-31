<?php

namespace Stanford\InstrumentsGenerator;

/** @var \Stanford\InstrumentsGenerator\InstrumentsGenerator $module */
// hide comment.
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);

try {
    $module->generate();
} catch (\LogicException $e) {
    echo $e->getMessage();
}