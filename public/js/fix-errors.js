/**
 * حل مشاكل الـ Console Warnings - النسخة المحسنة
 */

(function () {
    'use strict';

    // ============================================
    // 1. حل مشكلة Passive Event Listeners
    // ============================================
    function fixPassiveListeners() {
        if (typeof window === 'undefined') return;

        // حفظ الدوال الأصلية
        const originalAddEventListener = EventTarget.prototype.addEventListener;

        // استبدال addEventListener مع passive للأحداث الحرجة
        EventTarget.prototype.addEventListener = function (type, listener, options) {
            let newOptions = options;

            // الأحداث التي تحتاج passive
            const needsPassive = [
                'touchstart', 'touchmove', 'touchend', 'touchcancel',
                'wheel', 'mousewheel', 'scroll'
            ];

            if (needsPassive.includes(type.toLowerCase())) {
                if (typeof options === 'boolean') {
                    newOptions = { capture: options, passive: true };
                } else if (typeof options === 'object' && options !== null) {
                    if (options.passive === undefined) {
                        newOptions = { ...options, passive: true };
                    }
                } else {
                    newOptions = { passive: true };
                }
            }

            return originalAddEventListener.call(this, type, listener, newOptions);
        };
    }

    // ============================================
    // 2. حل مشكلة الصور - النسخة المحسنة
    // ============================================
    function fixImageLoading() {
        // استخدم requestIdleCallback إذا متاح
        if ('requestIdleCallback' in window) {
            requestIdleCallback(processImages, { timeout: 1000 });
        } else {
            setTimeout(processImages, 100);
        }

        function processImages() {
            const images = document.querySelectorAll('img');
            if (images.length === 0) return;

            // تصفية الصور الخارجية أولاً
            const host = window.location.hostname;
            const externalImages = Array.from(images).filter(img => {
                const src = img.src || '';
                return src &&
                    (src.startsWith('http://') || src.startsWith('https://')) &&
                    !src.includes(host);
            });

            // إزالة أو استبدال الصور الخارجية المشبوهة
            externalImages.forEach(img => {
                // إذا كانت الصورة من مصادر معروفة بالمشاكل
                const badPatterns = [
                    'chrome-extension://',
                    'moz-extension://',
                    'ms-browser-extension://',
                    'blob:',
                    'data:image/svg+xml'
                ];

                const isBadImage = badPatterns.some(pattern =>
                    img.src.includes(pattern)
                );

                if (isBadImage) {
                    // استبدال بصورة افتراضية
                    img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCIgaGVpZ2h0PSIxMDAiIGZpbGw9IiNGNUY1RjUiLz48cGF0aCBkPSJNMzUgMzVMODAgODBNODAgMzVMMzUgODAiIHN0cm9rZT0iI0RERCIgc3Ryb2tlLXdpZHRoPSIyIi8+PC9zdmc+';
                    img.alt = 'صورة غير متاحة';
                    img.classList.add('img-failed');
                    img.style.maxWidth = '100%';
                    img.style.height = 'auto';
                }
            });

            // إضافة معالج للأخطاء للصور المتبقية
            images.forEach(img => {
                // تخطي الصور التي تمت معالجتها بالفعل
                if (img.dataset.errorHandled) return;

                // التحقق إذا كانت الصورة قد فشلت بالفعل
                if (!img.complete || img.naturalHeight === 0) {
                    img.addEventListener('error', handleImageError, { once: true });
                    img.dataset.errorHandled = 'true';
                }
            });
        }

        function handleImageError(e) {
            const img = e.target;

            // منع التكرار
            img.removeEventListener('error', handleImageError);

            // استبدال بصورة افتراضية SVG
            img.src = 'data:image/svg+xml;base64,' + btoa(`
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="100" height="100" fill="#F5F5F5"/>
                    <path d="M35 35L80 80M80 35L35 80" stroke="#DDD" stroke-width="2"/>
                    <text x="50" y="50" text-anchor="middle" dy=".3em" font-family="Arial" font-size="12" fill="#999">Image</text>
                </svg>
            `);
            img.alt = 'صورة غير متاحة';
            img.classList.add('img-failed');
            img.style.opacity = '0.7';

            // منع إعادة المحاولة
            img.onerror = null;
        }

        console.log('✅ Image error handling activated (optimized)');
    }

    // ============================================
    // 3. حل مشكلة Waves.js - النسخة المحسنة
    // ============================================
    function fixWavesJS() {
        if (typeof Waves === 'undefined') return;

        // حل 1: تعطيل Waves.js تماماً
        if (window.disableWavesCompletely) {
            Waves.attach = function () { return arguments[0]; };
            Waves.init = function () { return this; };
            console.log('✅ Waves.js completely disabled');
            return;
        }

        // حل 2: إصلاح setTimeout البطيئة
        const originalSetTimeout = window.setTimeout;

        window.setTimeout = function (callback, delay) {
            // إذا كانت الدالة من Waves.js، استبدلها بإصدارة محسنة
            const callbackString = callback.toString();

            if (callbackString.includes('TouchHandler') ||
                callbackString.includes('removeChild(ripple)') ||
                callbackString.includes('Waves') ||
                callbackString.includes('ripple')) {

                // استبدال بدالة محسنة
                return originalSetTimeout(function () {
                    try {
                        callback();
                    } catch (e) {
                        console.warn('Waves.js timeout error:', e.message);
                    }
                }, Math.min(delay, 100)); // تقليل التأخير لـ Waves
            }

            // لبقية الـ timeouts، استخدم الأصلية
            return originalSetTimeout(callback, delay);
        };

        // حل 3: تعطيل Waves على عناصر محددة
        const originalAttach = Waves.attach;

        Waves.attach = function (elements, classes) {
            if (!elements) return elements;

            // تحويل إلى مصفوفة
            let elementArray = [];
            if (elements instanceof NodeList || elements instanceof HTMLCollection) {
                elementArray = Array.from(elements);
            } else if (Array.isArray(elements)) {
                elementArray = elements;
            } else {
                elementArray = [elements];
            }

            // تصفية العناصر التي لا نريد Waves عليها
            const filteredElements = elementArray.filter(el => {
                // تخطي العناصر المعطوبة أو غير المرئية
                if (!el || !el.classList) return false;

                // تخطي إذا كان العنصر يحتوي على أي من هذه الكلاسات
                const skipClasses = [
                    'no-waves',
                    'ai-assist-btn',
                    'btn-outline',
                    'form-control',
                    'select2',
                    'ckeditor'
                ];

                if (skipClasses.some(cls => el.classList.contains(cls))) {
                    return false;
                }

                // تخطي إذا كان العنصر داخل container معين
                const containers = [
                    '#ai-loading-overlay',
                    '.card-body',
                    '.modal-content',
                    '.swal2-popup'
                ];

                if (containers.some(selector => el.closest(selector))) {
                    const container = el.closest(containers.join(', '));
                    if (container && container.hasAttribute('data-no-waves')) {
                        return false;
                    }
                }

                return true;
            });

            if (filteredElements.length > 0) {
                return originalAttach.call(Waves, filteredElements, classes);
            }

            return elements;
        };

        console.log('✅ Waves.js optimized with timeout fixes');
    }

    // ============================================
    // 4. تحسين الـ setTimeout العام
    // ============================================
    function optimizeTimeouts() {
        const originalSetTimeout = window.setTimeout;
        const longThreshold = 100; // 100ms بدلاً من 50ms

        window.setTimeout = function (callback, delay, ...args) {
            const start = performance.now();

            const wrappedCallback = function () {
                const end = performance.now();
                const duration = end - start;

                // تسجيل فقط إذا كان الوقت طويلاً حقاً
                if (duration > 500) { // 500ms بدلاً من 50ms
                    console.warn(`⚠️ Slow setTimeout: ${duration.toFixed(1)}ms`, {
                        callback: callback.toString().substring(0, 150),
                        delay: delay
                    });
                }

                try {
                    return callback.apply(this, args);
                } catch (e) {
                    console.error('Timeout callback error:', e);
                }
            };

            return originalSetTimeout(wrappedCallback, delay);
        };

        // نفس الشيء لـ setInterval
        const originalSetInterval = window.setInterval;

        window.setInterval = function (callback, delay, ...args) {
            const wrappedCallback = function () {
                try {
                    return callback.apply(this, args);
                } catch (e) {
                    console.error('Interval callback error:', e);
                }
            };

            return originalSetInterval(wrappedCallback, delay);
        };

        console.log('✅ Timeout optimization activated (less strict)');
    }

    // ============================================
    // 5. منع أخطاء Content.js (ملحقات Chrome)
    // ============================================
    function fixContentJSErrors() {
        // منع رسائل خطأ content.js
        const originalConsoleError = console.error;

        console.error = function (...args) {
            // تصفية رسائل content.js
            const filteredArgs = args.filter(arg => {
                if (typeof arg === 'string') {
                    const badPatterns = [
                        'HB== unexpected response',
                        'IMG_FAILED_LOAD',
                        'content.js',
                        'chrome-extension://'
                    ];
                    return !badPatterns.some(pattern => arg.includes(pattern));
                }
                return true;
            });

            if (filteredArgs.length > 0) {
                originalConsoleError.apply(console, filteredArgs);
            }
        };

        // منع تحميل الصور من الملحقات
        document.addEventListener('DOMContentLoaded', function () {
            const observer = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType === 1 && node.tagName === 'IMG') {
                            const src = node.src || '';
                            if (src.includes('chrome-extension://') ||
                                src.includes('moz-extension://')) {
                                node.style.display = 'none';
                                node.removeAttribute('src');
                            }
                        }
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        });

        console.log('✅ Content.js errors filtered');
    }

    // ============================================
    // 6. تحسين أداء الصفحة
    // ============================================
    function optimizePagePerformance() {
        // استخدام requestIdleCallback للمهام غير الحرجة
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => {
                // تأخير تحميل الصور غير المرئية
                const lazyImages = document.querySelectorAll('img[loading="lazy"]');
                lazyImages.forEach(img => {
                    if ('loading' in HTMLImageElement.prototype) {
                        img.loading = 'lazy';
                    }
                });
            }, { timeout: 2000 });
        }

        // تحسين أحداث اللمس
        document.documentElement.style.touchAction = 'manipulation';

        // منع السلوك الافتراضي للأحداث
        document.addEventListener('touchstart', function (e) {
            if (e.touches.length > 1) {
                e.preventDefault();
            }
        }, { passive: true });

        console.log('✅ Page performance optimized');
    }

    // ============================================
    // 7. تهيئة سريعة بدون تأخير
    // ============================================
    function quickInit() {
        // تشغيل الإصلاحات الأساسية فوراً
        fixPassiveListeners();
        fixContentJSErrors();

        // تأخير الإصلاحات الثقيلة
        setTimeout(fixWavesJS, 50);
        setTimeout(optimizeTimeouts, 100);

        // استخدام requestIdleCallback للإصلاحات غير الحرجة
        if ('requestIdleCallback' in window) {
            requestIdleCallback(() => {
                fixImageLoading();
                optimizePagePerformance();
            }, { timeout: 1000 });
        } else {
            setTimeout(fixImageLoading, 500);
            setTimeout(optimizePagePerformance, 1000);
        }

        console.log('🚀 Quick initialization completed');
    }

    // ============================================
    // 8. حل مشاكل محددة للموقع
    // ============================================
    function fixSpecificIssues() {
        // حل مشكلة CKEditor مع الصور
        if (typeof CKEDITOR !== 'undefined') {
            CKEDITOR.on('instanceReady', function (ev) {
                const editor = ev.editor;

                // منع تحميل الصور الخارجية في CKEditor
                editor.on('contentDom', function () {
                    const iframe = editor.document.getBody().$.querySelectorAll('img');
                    iframe.forEach(img => {
                        const src = img.src || '';
                        if (src.includes('chrome-extension://') ||
                            src.includes('blob:')) {
                            img.style.display = 'none';
                            img.src = '';
                        }
                    });
                });
            });
        }

        // حل مشكلة Select2 مع الصور
        if ($.fn.select2) {
            $(document).on('select2:open', function (e) {
                setTimeout(() => {
                    $('.select2-results__option img').each(function () {
                        const src = $(this).attr('src') || '';
                        if (src.includes('chrome-extension://')) {
                            $(this).hide();
                        }
                    });
                }, 100);
            });
        }

        console.log('✅ Specific issues fixed');
    }

    // ============================================
    // التشغيل الرئيسي - النسخة المحسنة
    // ============================================

    // خيار: تعطيل Waves.js تماماً
    window.disableWavesCompletely = true;

    // البدء فوراً
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            quickInit();
            setTimeout(fixSpecificIssues, 2000); // تأخير أكثر
        });
    } else {
        quickInit();
        setTimeout(fixSpecificIssues, 2000);
    }

    // إعادة التشغيل عند تحميل الصفحة بالكامل
    window.addEventListener('load', function () {
        // تشغيل مرة أخرى بعد تحميل كل شيء
        setTimeout(() => {
            fixImageLoading();
            optimizePagePerformance();
        }, 3000);
    });

})();