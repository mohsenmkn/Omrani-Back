<?php

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
        ],
    ]

];
