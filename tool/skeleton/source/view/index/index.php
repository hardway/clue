<!-- TODO: 显示config.local.php配置向导 -->
<style>
    th {text-align: right; vertical-align: top;}
</style>
<div style='border: 1px solid #DDD; border-radius: 8px; width: 600px; margin: 100px auto;'>
    <h1 style='margin: 0; padding: 20px; font-style: italic; color: #FFF; background: #AAA; border-top-left-radius: 8px; border-top-right-radius: 8px'>It Works!</h1>
    <table style='padding: 10px;' width='100%' cellpadding='4'>
        <tr>
            <th>OS:</th>
            <td><?=PHP_OS?></td>
        </tr>

        <tr>
            <th>Apache:</th>
            <td>
                <?=apache_get_version()?>
                <span style='color: #AAA'><?=php_sapi_name()?></span>
            </td>
        </tr>

        <tr>
            <th>MySQL:</th>
            <td>
                <?php if($app['db']): ?>
                    <?=$db->dbh->server_info?>
                    <span style='color: #AAA'>(<?=$db->dbh->stat?>)</span>
                <?php else: ?>
                    Database not configured. (check <code>config.php</code>)
                <?php endif; ?>
            </td>
        </tr>

        <tr>
            <th>PHP:</th>
            <td>
                <?=phpversion()?>
                <span style='color: #AAA'>(Modules: <?=implode(", ", get_loaded_extensions())?>)</span>
            </td>
        </tr>
    </table>
</div>
