<div class="modal active" id="<?=$id?>">
    <div class="modal-overlay" aria-label="关闭" hx-on:click="closeModal(this)"></div>
    <div class="modal-container">
        <div class="modal-header">
            <button type="button" class="btn btn-clear float-right" aria-label="关闭" hx-on:click="closeModal(this)"></button>
            <div class="modal-title h5"><?=$title?></div>
        </div>
        <div class="modal-body">
            <div class="content">
                <?=$body?>
            </div>
        </div>
        <?php if(isset($footer) && $footer): ?>
        <div class="modal-footer">
            <?=$footer?>
        </div>
        <?php endif; ?>
    </div>
</div>
