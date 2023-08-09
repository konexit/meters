<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link type="text/css" rel="stylesheet" href="<?= base_url() . '/assets/css/style.css?ver=' . STATIC_FILES_VERSION ?>"/>
    <title>Cистема заявок "Конекс"</title>
</head>
<body>
<div class="head" id="topPan">
    <div class="headContent">
        <div id="login">
            <form action="/login" method="post">
                <input type="hidden" name="nomNak" value="" />
                <h2> Логін:
                    <input class="text" type="text" id="usl" name="log" value="<?= set_value('log') ?>" placeholder="Введіть логін" />
                    <p>
                        Пароль:
                        <input class="text" type="password" id="usp" name="pw" value="" placeholder="Введіть пароль" autocomplete="off" />
                    </p>
                </h2>
                <button id="submit" type="submit">Ввійти</button>
                <?php if (isset($validation)) : ?>
                    <div class="login-error">
                        <?= $validation->listErrors() ?>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
</body>
</html>
