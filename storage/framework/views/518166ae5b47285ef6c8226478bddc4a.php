<!doctype html>
<html lang="ar" class="light-style layout-navbar-fixed layout-menu-fixed layout-compact" dir="rtl"
    data-theme="theme-default" data-assets-path="#"
    data-template="vertical-menu-template-no-customizer">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>
        <?php echo $__env->yieldContent('title'); ?>
    </title>

    <meta name="description" content="" />
    <?php echo $__env->make('Admin.layout.css', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
    <!-- Page -->
    <link rel="stylesheet" href="<?php echo e(asset('dashboard/assets/vendor/css/pages/page-auth.css')); ?>" />
</head>
<?php echo $__env->yieldContent('css'); ?>

<body>
    <!-- Content -->
    <?php echo $__env->yieldContent('content'); ?>
    <!-- / Content -->

    <!-- Core JS -->
    <?php echo $__env->make('Admin.layout.js', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
</body>

</html>
<?php /**PATH /home/mohamed/Videos/dashboard/Ahmed_m/flash-cardy/resources/views/Admin/auth/layouts/master.blade.php ENDPATH**/ ?>