<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>

<div id="geo-search-form">
    <?php if (!empty($arResult['ERROR'])) : ?>
        <div class="alert alert-danger"><?= $arResult['ERROR'] ?></div>
    <?php endif; ?>

    <form id="geo-search-form" method="post">
        <?= bitrix_sessid_post() ?>
        <div class="form-group">
            <label for="ip"><?= GetMessage('IP_ADDRESS') ?></label>
            <input type="text" name="ip" id="ip" class="form-control">
        </div>
        <button type="button" id="submit-btn" class="btn btn-primary"><?= GetMessage('SEARCH') ?></button>
    </form>

    <div id="geo-data" class="mt-3"></div>
</div>

<?php
CJSCore::Init(['ajax', 'window']);
?>

<script>
    BX.ready(function () {
        document.getElementById('submit-btn').addEventListener('click', function () {
            var ip = document.getElementById('ip').value;
            if (ip.trim() === '') {
                alert('Please enter an IP address.');
                return;
            }

            var requestData = {
                sessid: '<?= bitrix_sessid() ?>',
                ip: ip,
                arParams: <?= json_encode($arParams) ?>
            };

            BX.ajax({
                url: '<?= $component->getPath() ?>/res.php',
                method: 'POST',
                data: requestData,
                start: true,
                dataType: 'json',
                onsuccess: function (data) {
                    document.getElementById('geo-data').innerHTML = data;
                },
                onfailure: function (error) {
                    console.error('Request failed:', error);
                }
            });
        });
    });
</script>