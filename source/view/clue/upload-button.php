<?php
    // @param $field input名称
    // @param $text 按钮默认显示

    $label_id="label-$field";
    $input_id="input-$field";
?>
<input type="file" name='<?=$field?>' onchange='document.querySelector("#<?=$label_id?>").textContent=this.files.length > 0 ? this.files.length+" file(s)" : "<?=$text?>";' id='<?=$input_id?>' hidden='1' />
<label class='btn' for='<?=$input_id?>' id='<?=$label_id?>'><?=$text?></label>
