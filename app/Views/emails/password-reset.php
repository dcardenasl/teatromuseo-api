<!DOCTYPE html>
<html lang="<?= service('request')->getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= lang('Email.passwordReset.subject') ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f4f4f4;
            padding: 30px;
            border-radius: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
        }
        .content {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background-color: #e74c3c;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #c0392b;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #7f8c8d;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
        }
        .security-notice {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= lang('Email.passwordReset.title') ?></h1>
        </div>

        <div class="content">
            <h2><?= lang('Email.passwordReset.greeting') ?></h2>

            <p><?= lang('Email.passwordReset.intro') ?></p>

            <div style="text-align: center;">
                <a href="<?= esc($reset_link) ?>" class="button"><?= lang('Email.passwordReset.buttonText') ?></a>
            </div>

            <p><?= lang('Email.passwordReset.linkIntro') ?></p>
            <p style="word-break: break-all; color: #e74c3c;">
                <?= esc($reset_link) ?>
            </p>

            <div class="warning">
                <p style="margin: 0;">
                    <strong><?= lang('Email.passwordReset.expiration', [esc($expires_in ?? '60 minutes')]) ?></strong>
                </p>
            </div>

            <div class="security-notice">
                <p style="margin: 0;">
                    <strong><?= lang('Email.passwordReset.securityTitle') ?></strong>
                </p>
                <p style="margin: 5px 0 0 0;">
                    <?= lang('Email.passwordReset.securityNotice') ?>
                </p>
            </div>
        </div>

        <div class="footer">
            <p><?= lang('Email.passwordReset.autoMessage') ?></p>
            <p>&copy; <?= date('Y') ?> <?= esc(env('EMAIL_FROM_NAME', 'API Application')) ?>. <?= lang('Email.passwordReset.copyright') ?></p>
        </div>
    </div>
</body>
</html>
