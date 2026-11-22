<!doctype html><html lang="ar" dir="rtl"><body style="font-family:Arial,sans-serif">
<h2>إعادة تعيين كلمة مرور لوحة التحكم</h2>
<p>تم طلب إعادة تعيين كلمة المرور. اضغط على الرابط التالي لإكمال العملية:</p>
<p><a href="{{ $resetUrl }}">إعادة تعيين كلمة المرور</a></p>
<p>الرابط صالح لمدة {{ config('auth.passwords.admins.expire', 30) }} دقيقة، ويُستخدم مرة واحدة فقط.</p>
<p>إذا لم تطلب ذلك، تجاهل الرسالة.</p>
</body></html>
