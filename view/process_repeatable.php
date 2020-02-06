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
<div class="container">
    <form enctype="multipart/form-data" action="<?php echo $generatorURL ?>" method="post" name="instrument-generator"
          id="instrument-generator">
        <div class="form-group">
            <label for="exampleInputEmail1">Data File</label>
            <input type="file" name="file" id="file" required>
            <small id="emailHelp" class="form-text text-muted">Please upload STRIDE generated CSV file</small>
        </div>


        <div class="form-group">
            <label for="exampleFormControlSelect1">Targeted Instrument</label>
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
        </div>
        <div class="form-group">
            <label for="exampleFormControlSelect1">If your data does have record ID select the Identifier that will map
                to correct record ID</label>
            <select name="alternativePK" id="alternativePK" required>
                <option value="">SELECT INSTRUMENT</option>
                <?php
                $fields = $module->getProject()->metadata;
                foreach ($fields as $id => $array) {
                    ?>
                    <option value="<?php echo $id ?>"><?php echo $array['element_label'] ?></option>
                    <?php
                }
                ?>
            </select>
        </div>
        <input type="submit" class="btn btn-primary" id="submit" name="submit" value="Submit">
    </form>
</div>
