<?php

namespace Stanford\InstrumentsGenerator;

/** @var \Stanford\InstrumentsGenerator\InstrumentsGenerator $module */

use REDCap;

$generatorURL = $module->getUrl('model/import.php', false, true);
?>
<form enctype="multipart/form-data" action="<?php echo $generatorURL ?>" method="post" name="instrument-generator"
      id="instrument-generator">
    <input type="file" name="file" id="file">
    <input type="submit" id="submit" name="submit" value="Submit">
</form>
