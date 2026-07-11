<?php $__env->startSection('styles'); ?>
    <!-- إضافة دعم RTL للـ Bootstrap إذا لم يكن موجوداً بالفعل -->
    <link rel="stylesheet" href="<?php echo e(asset('dashboard/assets/css/bootstrap-rtl.min.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
    <div class="authentication-wrapper authentication-cover authentication-bg" dir="rtl">
        <div class="authentication-inner row">
            <!-- الجانب الأيسر (الصورة التوضيحية) - يظهر فقط على الشاشات الكبيرة -->
            <div class="d-none d-lg-flex col-lg-7 align-items-center p-0" style="max-height: 950px;">
                <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center w-100 h-100">
                    <img src="<?php echo e(asset('dashboard/assets/img/illustrations/auth-login-illustration-light.png')); ?>"
                        alt="غلاف تسجيل الدخول" class="img-fluid auth-illustration">

                    <img src="<?php echo e(asset('dashboard/assets/img/illustrations/bg-shape-image-light.png')); ?>" alt="خلفية النظام"
                        class="platform-bg">
                </div>
            </div>
            <!-- /الجانب الأيسر -->

            <!-- نموذج تسجيل الدخول (الجانب الأيمن في RTL) -->
            <div class="d-flex col-12 col-lg-5 align-items-center p-4 p-sm-5">
                <div class="w-px-400 mx-auto">

                    <!-- الشعار -->
                    <div class="app-brand mb-5 d-flex justify-content-center align-items-center">
                        <a href="<?php echo e(url('/')); ?>" class="app-brand-link">
                            <img height="100" width="100" src="<?php echo e(asset('dashboard/assets/img/tala.png')); ?>"
                                alt="شعار <?php echo e(env('APP_NAME')); ?>">
                        </a>
                    </div>


                    <h3 class="mb-2 text-center">مرحباً بك في <?php echo e(env('APP_NAME')); ?> 👋</h3>
                    <p class="mb-4 text-center text-muted">
                        مرحباً بك في لوحة تحكم تطبيق <?php echo e(env('APP_NAME')); ?>

                    </p>

                    <form id="formAuthentication" class="mb-3" method="POST" action="<?php echo e(route('admin.login')); ?>"
                        novalidate>
                        <?php echo csrf_field(); ?>

                        <!-- البريد الإلكتروني -->
                        <div class="mb-3">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control text-start" dir="ltr" id="email"
                                name="email" value="<?php echo e(old('email')); ?>" placeholder="name@example.com" autofocus
                                required>
                            <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <!-- كلمة المرور -->
                        <div class="mb-3 form-password-toggle">
                            <div class="d-flex justify-content-between mb-2">
                                <label class="form-label" for="password">كلمة المرور</label>
                                <a href="<?php echo e(route('admin.password.request')); ?>">
                                    <small>نسيت كلمة المرور؟</small>
                                </a>
                            </div>

                            <div class="input-group input-group-merge">
                                <input type="password" id="password" class="form-control text-start" dir="ltr"
                                    name="password" placeholder="············" aria-describedby="password" required>
                                <span class="input-group-text cursor-pointer">
                                    <i class="ti ti-eye-off"></i>
                                </span>
                            </div>
                            <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                                <div class="text-danger small mt-1"><?php echo e($message); ?></div>
                            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>

                        <!-- تذكرني -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember-me">
                                <label class="form-check-label" for="remember-me">
                                    تذكرني
                                </label>
                            </div>
                        </div>

                        <!-- زر تسجيل الدخول -->
                        <button type="submit" class="btn btn-primary d-grid w-100 mb-3">
                            تسجيل الدخول
                        </button>
                    </form>

                    <!-- التذييل -->
                    <div class="text-center">
                        <small class="text-muted">
                            تم التطوير بواسطة
                            <a href="https://nofalseo.com" target="_blank" class="text-primary fw-medium">
                                <?php echo e(env('APP_NAME')); ?>

                            </a>
                        </small>
                    </div>

                </div>
            </div>
            <!-- /نموذج تسجيل الدخول -->

        </div>
    </div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
    <!-- تفعيل زر إظهار/إخفاء كلمة المرور (إذا كان لديك ملف JS للـ template) -->
    <script>
        document.querySelectorAll('.form-password-toggle .input-group-text').forEach(el => {
            el.addEventListener('click', function() {
                const input = this.closest('.input-group').querySelector('input');
                if (input.type === 'password') {
                    input.type = 'text';
                    this.querySelector('i').classList.replace('ti-eye-off', 'ti-eye');
                } else {
                    input.type = 'password';
                    this.querySelector('i').classList.replace('ti-eye', 'ti-eye-off');
                }
            });
        });
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('Admin.auth.layouts.master', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH /home/mohamed/Videos/dashboard/Ahmed_m/flash-cardy/resources/views/Admin/auth/login.blade.php ENDPATH**/ ?>