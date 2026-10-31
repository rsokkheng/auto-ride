<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delete your ROTEH account</title>
    <style>
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans Khmer", Roboto, sans-serif;
               background: #F5F7F5; color: #1A1A1A; line-height: 1.6; }
        main { max-width: 680px; margin: 0 auto; padding: 32px 20px 48px; }
        h1 { color: #00B14F; font-size: 26px; margin: 0 0 4px; }
        h2 { font-size: 18px; margin: 28px 0 8px; }
        section { background: #fff; border-radius: 16px; padding: 20px 22px; margin-top: 16px;
                  box-shadow: 0 2px 10px rgba(0,0,0,.05); }
        ol, ul { padding-left: 22px; margin: 8px 0; }
        .muted { color: #767676; font-size: 14px; }
        a { color: #00B14F; }
    </style>
</head>
<body>
<main>
    <h1>ROTEH</h1>
    <p class="muted">លុបគណនី · Delete account · 删除账户</p>

    <section lang="km">
        <h2>របៀបលុបគណនីរបស់អ្នក</h2>
        <ol>
            <li>បើកកម្មវិធី ROTEH ហើយចូលគណនី។</li>
            <li>ចូលទៅ <b>គណនី</b> (Profile)។</li>
            <li>ចុច <b>លុបគណនី</b> ហើយបញ្ជាក់។</li>
        </ol>
        <p>យើងនឹងលុបឈ្មោះ អ៊ីមែល លេខទូរស័ព្ទ ទីកន្លែងដែលបានរក្សាទុក ទំនាក់ទំនងសង្គ្រោះបន្ទាន់ និងឧបករណ៍ដែលបានភ្ជាប់ ភ្លាមៗ។
           ប្រវត្តិដំណើរ ការបញ្ជាទិញ និងប្រតិបត្តិការកាបូប ត្រូវបានរក្សាទុកសម្រាប់គណនេយ្យ និងច្បាប់ ប៉ុន្តែលែងភ្ជាប់នឹងអត្តសញ្ញាណរបស់អ្នកទៀតហើយ។</p>
        <p>មិនអាចចូលកម្មវិធីបាន? សូមផ្ញើអ៊ីមែលពីលេខទូរស័ព្ទ ឬអ៊ីមែលគណនីរបស់អ្នកមកកាន់ <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>។</p>
    </section>

    <section lang="en">
        <h2>How to delete your account</h2>
        <ol>
            <li>Open the ROTEH app and sign in.</li>
            <li>Go to <b>Profile</b>.</li>
            <li>Tap <b>Delete account</b> and confirm.</li>
        </ol>
        <p>Your name, email, phone number, saved places, emergency contacts and linked devices are deleted immediately.
           Trip, order and wallet records are kept for accounting and legal reasons, but are no longer linked to your identity.</p>
        <p>Can't sign in? Email us from your account's email address or with your account phone number at
           <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a> and we will delete it for you.</p>
    </section>

    <section lang="zh">
        <h2>如何删除您的账户</h2>
        <ol>
            <li>打开 ROTEH 应用并登录。</li>
            <li>进入 <b>个人资料</b>。</li>
            <li>点击 <b>删除账户</b> 并确认。</li>
        </ol>
        <p>您的姓名、邮箱、手机号、已保存地点、紧急联系人和已关联设备将被立即删除。
           行程、订单和钱包记录因会计和法律原因会被保留，但不再与您的身份关联。</p>
        <p>无法登录？请使用账户邮箱或注明账户手机号发送邮件至
           <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>，我们将为您删除。</p>
    </section>
</main>
</body>
</html>
