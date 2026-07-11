<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>اختبار إعدادات البريد</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .success-icon {
            text-align: center;
            margin-bottom: 20px;
        }
        .success-icon i {
            font-size: 60px;
            color: #28a745;
        }
        .message {
            text-align: center;
            margin-bottom: 30px;
        }
        .message h2 {
            color: #333;
            margin-bottom: 15px;
        }
        .message p {
            color: #666;
            line-height: 1.8;
        }
        .details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .details h3 {
            color: #333;
            margin-top: 0;
        }
        .details p {
            margin: 10px 0;
            color: #666;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #dee2e6;
        }
        .footer p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px;
            }
            .header, .content, .footer {
                padding: 20px;
            }
        }
    </style>
</head>
<body dir="rtl">
    <div class="container">
        <div class="header">
            <h1>✅ اختبار إعدادات البريد الإلكتروني</h1>
        </div>
        
        <div class="content">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            
            <div class="message">
                <h2>تهانينا! تم إرسال البريد بنجاح 🎉</h2>
                <p>تم اختبار إعدادات البريد الإلكتروني (SMTP) بنجاح.</p>
                <p>تم إرسال هذا البريد من تطبيق <strong>{{ $app_name }}</strong></p>
            </div>
            
            <div class="details">
                <h3>📋 تفاصيل الإرسال:</h3>
                <p><strong>التاريخ والوقت:</strong> {{ $date }}</p>
                <p><strong>تطبيق:</strong> {{ $app_name }}</p>
                <p><strong>نوع الإرسال:</strong> اختبار إعدادات SMTP</p>
                <p><strong>الحالة:</strong> ✅ تم الإرسال بنجاح</p>
            </div>
            
            <div style="margin-top: 30px; text-align: center;">
                <p style="color: #666; font-size: 14px;">
                    هذا بريد اختباري يهدف إلى التأكد من صحة إعدادات البريد الإلكتروني.
                    لا تحتاج إلى اتخاذ أي إجراء.
                </p>
            </div>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} {{ $app_name }}. جميع الحقوق محفوظة.</p>
            <p>تم إرسال هذا البريد تلقائياً كجزء من اختبار إعدادات النظام.</p>
        </div>
    </div>
</body>
</html>