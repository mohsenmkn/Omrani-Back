<?php


/* Read me: First-->add Primision in file rbac.php
            Secend->run php artisan db:seed --class=PermissionSeeder
            & php artisan db:seed --class=Roleseeder
*/
return [
    'guard' => 'web',

    'modules' => [
        'dashboard' => ['view'],
        'auth' => ['manage', 'view_users'],
        'users' => ['read', 'create', 'update', 'delete'],
        'projects' => ['view','read', 'create', 'update', 'delete'],
        'tasks' => ['read', 'create', 'update', 'delete'],
        'contracts' => ['read', 'create', 'update', 'delete'],
        'contractors' => ['read', 'create', 'update', 'delete'],
        'roles' => ['read', 'create', 'update', 'delete'],
        'budget' => ['read', 'create', 'update', 'delete'],
        'pettycash'=>['read', 'create', 'update', 'delete'],
        'document'=>['read', 'create', 'update', 'delete'],
        'warehouse'=>['read', 'create', 'update', 'delete'],
        'wbs'=>['read', 'create', 'update', 'delete'],
        'permissions'=>['read', 'create', 'update', 'delete'],
        'Material'=>['read', 'create', 'update', 'delete'],
        'categories'=>['read', 'create', 'update', 'delete'],
        'transactions'=>['read', 'create', 'delete'],
        'stock'=>['view', 'create', 'update', 'delete'],
        'low-stock'=>['view', 'create', 'update', 'delete'],
        'consumption'=>['view'],
        'AdminPayroll'=>['view'],
        'Payroll'=>['view'],
        'AdminHr'=>['view'],
        'library'=>['view'],
        'librarybooks'=>['view','manage'],
        'libraryreservations'=>['view','manage','create'],
        'librarystatistics'=>['view'],
        'complaints' => ['read', 'create', 'update', 'delete', 'manage', 'reply', 'export'],
        'complaintcategories' => ['read', 'create', 'update', 'delete'],
        'complaintstatistics' => ['view'],
        'hr' => ['view', 'manage'],
        'virtual_secretariat' => ['view', 'manage', 'create_request', 'view_requests', 'manage_templates'],
        'workflow_dashboard' => [
            'view',           // مشاهده داشبورد
            'view_all',       // مشاهده همه فرآیندها (ادمین)
            'view_department',// مشاهده فرآیندهای واحد
            'export',         // خروجی Excel
        ],

    ],
    'labels' => [
        // ماژول‌ها (برای دسته‌بندی در UI)
        'module_names' => [
            'dashboard' => 'داشبورد',
            'auth'      => 'احراز هویت',
            'permissions'=>'دسترسی ها',
            'users'     => 'مدیریت کاربران',
            'projects'  => 'مدیریت پروژه‌ها',
            'tasks'     => 'مدیریت وظایف',
            'contracts' => 'قراردادها',
            'pettycash' => 'تنخواه گردان',
            'warehouse' => 'انبارداری',
            'roles'     => 'نقش‌ها و دسترسی‌ها',
            'contractors' =>'مدیریت پیمانکاران',
            'document'=>' اسناد و مدارک',
            'budget' =>'بودجه',
            'wbs'=>'ساختار شکست',
            'Material'=>'کالاها',
            'categories'=>'دسته بندی کالا',
            'transactions'=>'تراکنش های انبار',
            'stock'=>'گزارش موجودی',
            'low-stock'=>'گزارش موجودی کم',
            'consumption'=>'گزارش مصرف',
            'AdminPayroll'=>'مدیر فیش حقوقی',
            'Payroll'=>'فیش حقوقی',
            'AdminHr'=>'مدیریت کنترل تردد',
            'librarybooks'=>'مدیریت کتاب ها',
            'libraryreservations'=>'مدیریت رزروها',
            'librarystatistics'=>'مشاهده داشبورد کتابخانه',
            'library'=>'مشاهده کتابخانه',
            'complaints' => 'شکایات، انتقادات و پیشنهادات',
            'complaintcategories' => 'دسته‌بندی شکایات',
            'complaintstatistics' => 'داشبورد آماری شکایات',
            'hr'=>'منابع انسانی',
            'virtual_secretariat' => 'دبیرخانه مجازی',
            'workflow_dashboard' => 'داشبورد فرآیندها',



        ],

        // اکشن‌های عمومی و اختصاصی
        'actions' => [
            'read'       => 'مشاهده',
            'create'     => 'ایجاد',
            'update'     => 'ویرایش',
            'delete'     => 'حذف',
            'view'       => 'نمایش',
            'manage'     => 'مدیریت کل',
            'view_users' => 'مشاهده کاربران',
            'export'     => 'خروجی اکسل',
            'approve'    => 'تایید نهایی',
            'send'       => 'ارسال',
            'reply'      => 'پاسخ به شکایت',
            // در آرایه 'actions' می‌توانید اضافه کنید (اختیاری):
            'create_request' => 'ثبت درخواست',
            'view_requests' => 'مشاهده درخواست‌ها',
            'manage_templates' => 'مدیریت قالب‌ها',
            'view_all' => 'مشاهده همه فرآیندها',
            'view_department' => 'مشاهده فرآیندهای واحد',
        ],
    ]

];
