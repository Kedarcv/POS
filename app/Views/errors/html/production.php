<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">

    <title><?= lang('Errors.whoops') ?></title>

    <style>
        <?= preg_replace('#[\r\n\t ]+#', ' ', file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'debug.css')) ?>
    </style>
</head>
<body>

    <div class="container text-center">

        <h1 class="headline"><?= lang('Errors.whoops') ?></h1>

        <p class="lead"><?= lang('Errors.weHitASnag') ?></p>

        <p>
            <a href="<?= function_exists('base_url') ? base_url('home') : '/home' ?>"
               style="display:inline-block; padding:10px 20px; background:#337ab7; color:#fff; text-decoration:none; border-radius:4px;">
                &larr; Back to Home
            </a>
        </p>

    </div>

</body>

</html>
