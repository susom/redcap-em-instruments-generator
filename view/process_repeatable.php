<?php
/**
 * this file will do following
 * 1. add redcap_repeat_instance
 * 2. add redcap_repeat_instrument
 * 3. add instrument name to the header fields
 * 4. process checkboxes
 */
namespace Stanford\InstrumentsGenerator;

/** @var \Stanford\InstrumentsGenerator\InstrumentsGenerator $module */

use REDCap;

$generatorURL = $module->getUrl('model/process_repeatable.php', false, true);
?>
<form enctype="multipart/form-data" action="<?php echo $generatorURL ?>" method="post" name="instrument-generator"
      id="instrument-generator">
    <input type="file" name="file" id="file" required>
    <select name="instruments" id="instruments" required>
        <option value="">SELECT INSTRUMENT</option>
        <?php
        $instruments = REDCap::getInstrumentNames();
        foreach ($instruments as $id => $instrument) {
            ?>
            <option value="<?php echo $id ?>"><?php echo $instrument ?></option>
            <?php
        }
        ?>
    </select>
    <input type="submit" id="submit" name="submit" value="Submit">
</form>
