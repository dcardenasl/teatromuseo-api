<!DOCTYPE html>
<html lang="<?= service('request')->getLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= lang('Email.pendingApprovalGoogle.subject') ?></title>
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
        .notice {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= lang('Email.pendingApprovalGoogle.title') ?></h1>
        </div>

        <div class="content">
            <h2><?= lang('Email.pendingApprovalGoogle.greeting', [esc($display_name ?? 'User')]) ?></h2>

            <p><?= lang('Email.pendingApprovalGoogle.intro') ?></p>

            <div class="notice">
                <p style="margin: 0;"><?= lang('Email.pendingApprovalGoogle.nextStep') ?></p>
            </div>
        </div>

        <div class="footer">
            <p><?= lang('Email.pendingApprovalGoogle.autoMessage') ?></p>
            <p>&copy; <?= date('Y') ?> <?= esc(env('EMAIL_FROM_NAME', 'API Application')) ?>. <?= lang('Email.pendingApprovalGoogle.copyright') ?></p>
        </div>
    </div>
</body>
</html>
