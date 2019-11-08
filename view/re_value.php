<?php

namespace Stanford\InstrumentsGenerator;

/** @var \Stanford\InstrumentsGenerator\InstrumentsGenerator $module */

use REDCap;

$generatorURL = $module->getUrl('model/re_value.php', false, true);
?>
<!doctype html>
<html lang="en">
<head>
    <title>Re-Value Data Dictionary options</title>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
          integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.15/css/jquery.dataTables.min.css">

    <style>
        body {
            word-wrap: break-word;
        }
    </style>
</head>
<body>

<div id="app" class="container">

    <div class="row p-1">
        <h1>Re-Value Data Dictionary options</h1>
    </div>
    <div class="row ">
        <div class="col-lg-12">
            <a href="<?php echo $module->getUrl('sample/import_sample.csv') ?>"
               class=" text-center btn btn-primary btn-lg btn-block">First Download Sample Import file</a>
        </div>
    </div>
    <div id="filters-row" class="row p-1">

        <div class="col-lg-12">
            <!-- Correlated Report form -->
            <div class="alert-info" role="alert">
                <h4 class="alert-heading p-1">Note!</h4>
                <p>When updating the sample import file please note:</p>
                <ul class="list-group p-1">
                    <li class="list-group-item">Each line represents Dropdown/checkbox value and label.</li>
                    <li class="list-group-item">This EM will NOT ADD new Fields or DELETE existing ones.</li>
                    <li class="list-group-item">New values MUST be numeric</li>
                </ul>
            </div>
            <form enctype="multipart/form-data" action="<?php echo $generatorURL ?>" method="post">
                <div class="row p-1">
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" name="file" id="file" lang="es">
                        <label class="custom-file-label" for="customFileLang">Upload Modified Data Dictionary</label>
                    </div>
                </div>
                <div class="row p-1">
                    <div class="col text-center">
                        <button type="submit" name="correlated-report-submit" class="btn btn-primary"
                                id="correlated-report-submit">Generate
                        </button>
                    </div>
                </div>
            </form>
            <!-- END Correlated Report form -->
        </div>
    </div>
</div>
<div class="loader"><!-- Place at bottom of page --></div>
</body>
</html>