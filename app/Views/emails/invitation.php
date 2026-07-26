<!DOCTYPE html>
<html lang="<?= service('request')->getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= lang('Email.invitation.subject') ?></title>
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
            background-color: #2c80b4;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #7f8c8d;
        }
        .notice {
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
            <h1><?= lang('Email.invitation.title') ?></h1>
        </div>

        <div class="content">
            <h2><?= lang('Email.invitation.greeting', [esc($display_name ?? 'User')]) ?></h2>

            <p><?= lang('Email.invitation.intro') ?></p>
            <p><?= lang('Email.invitation.googleOption') ?></p>

            <div style="text-align: center;">
                <a href="<?= esc($reset_link) ?>" class="button"><?= lang('Email.invitation.buttonText') ?></a>
            </div>

            <p><?= lang('Email.invitation.linkIntro') ?></p>
            <p style="word-break: break-all; color: #3498db;">
                <?= esc($reset_link) ?>
            </p>

            <div class="notice">
                <p style="margin: 0;">
                    <strong><?= lang('Email.invitation.expiration', [esc($expires_in ?? '60 minutes')]) ?></strong>
                </p>
            </div>
        </div>

        <div class="footer">
            <p><?= lang('Email.invitation.autoMessage') ?></p>
            <p>&copy; <?= date('Y') ?> <?= esc(env('EMAIL_FROM_NAME', 'API Application')) ?>. <?= lang('Email.invitation.copyright') ?></p>
        </div>
    </div>
</body>
</html>
