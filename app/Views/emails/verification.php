<!DOCTYPE html>
<html lang="<?= service('request')->getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= lang('Email.verification.title') ?></title>
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
            background-color: #3498db;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .button:hover {
            background-color: #2980b9;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= lang('Email.verification.welcome', [esc(env('EMAIL_FROM_NAME', 'API Application'))]) ?></h1>
        </div>

        <div class="content">
            <h2><?= lang('Email.verification.greeting', [esc($display_name ?? 'User')]) ?></h2>

            <p><?= lang('Email.verification.intro') ?></p>

            <div style="text-align: center;">
                <a href="<?= esc($verification_link) ?>" class="button"><?= lang('Email.verification.buttonText') ?></a>
            </div>

            <p><?= lang('Email.verification.linkIntro') ?></p>
            <p style="word-break: break-all; color: #3498db;">
                <?= esc($verification_link) ?>
            </p>

            <div class="warning">
                <p style="margin: 0;">
                    <strong><?= lang('Email.verification.expiration', [esc($expires_at ?? 'soon')]) ?></strong>
                </p>
                <p style="margin: 5px 0 0 0;">
                    <?= lang('Email.verification.footer') ?>
                </p>
            </div>
        </div>

        <div class="footer">
            <p><?= lang('Email.verification.autoMessage') ?></p>
            <p>&copy; <?= date('Y') ?> <?= esc(env('EMAIL_FROM_NAME', 'API Application')) ?>. <?= lang('Email.verification.copyright') ?></p>
        </div>
    </div>
</body>
</html>
