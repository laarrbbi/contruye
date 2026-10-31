<?php

return [

    /*
    |--------------------------------------------------------------------------
    | رسائل التحقق من صحة البيانات
    |--------------------------------------------------------------------------
    |
    | تحتوي الأسطر التالية على رسائل الخطأ الافتراضية لقواعد التحقق.
    | لبعض القواعد عدة صيغ، كقواعد الحجم. يمكنك تعديل أي رسالة هنا.
    |
    */

    'accepted' => 'يجب قبول حقل :attribute.',
    'accepted_if' => 'يجب قبول حقل :attribute عندما تكون قيمة :other هي :value.',
    'active_url' => 'يجب أن يكون حقل :attribute رابطاً صالحاً.',
    'after' => 'يجب أن يكون حقل :attribute تاريخاً لاحقاً لتاريخ :date.',
    'after_or_equal' => 'يجب أن يكون حقل :attribute تاريخاً لاحقاً لتاريخ :date أو مساوياً له.',
    'alpha' => 'يجب أن يحتوي حقل :attribute على حروف فقط.',
    'alpha_dash' => 'يجب أن يحتوي حقل :attribute على حروف وأرقام وشرطات فقط.',
    'alpha_num' => 'يجب أن يحتوي حقل :attribute على حروف وأرقام فقط.',
    'array' => 'يجب أن يكون حقل :attribute مصفوفة.',
    'ascii' => 'يجب أن يحتوي حقل :attribute على أحرف ورموز أحادية البايت فقط.',
    'before' => 'يجب أن يكون حقل :attribute تاريخاً سابقاً لتاريخ :date.',
    'before_or_equal' => 'يجب أن يكون حقل :attribute تاريخاً سابقاً لتاريخ :date أو مساوياً له.',
    'between' => [
        'array' => 'يجب أن يحتوي حقل :attribute على عدد من العناصر بين :min و:max.',
        'file' => 'يجب أن يكون حجم ملف :attribute بين :min و:max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute بين :min و:max.',
        'string' => 'يجب أن يكون عدد أحرف حقل :attribute بين :min و:max.',
    ],
    'boolean' => 'يجب أن تكون قيمة حقل :attribute صحيحة أو خاطئة.',
    'can' => 'يحتوي حقل :attribute على قيمة غير مسموح بها.',
    'confirmed' => 'تأكيد حقل :attribute غير مطابق.',
    'contains' => 'حقل :attribute لا يحتوي على قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'يجب أن يكون حقل :attribute تاريخاً صالحاً.',
    'date_equals' => 'يجب أن يكون حقل :attribute تاريخاً مساوياً لتاريخ :date.',
    'date_format' => 'يجب أن يطابق حقل :attribute الصيغة :format.',
    'decimal' => 'يجب أن يحتوي حقل :attribute على :decimal من الأرقام العشرية.',
    'declined' => 'يجب رفض حقل :attribute.',
    'declined_if' => 'يجب رفض حقل :attribute عندما تكون قيمة :other هي :value.',
    'different' => 'يجب أن يكون حقلا :attribute و:other مختلفين.',
    'digits' => 'يجب أن يتكون حقل :attribute من :digits أرقام.',
    'digits_between' => 'يجب أن يتكون حقل :attribute من عدد من الأرقام بين :min و:max.',
    'dimensions' => 'أبعاد صورة :attribute غير صالحة.',
    'distinct' => 'حقل :attribute يحتوي على قيمة مكررة.',
    'doesnt_end_with' => 'يجب ألا ينتهي حقل :attribute بإحدى القيم التالية: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ حقل :attribute بإحدى القيم التالية: :values.',
    'email' => 'يجب أن يكون حقل :attribute بريداً إلكترونياً صالحاً.',
    'ends_with' => 'يجب أن ينتهي حقل :attribute بإحدى القيم التالية: :values.',
    'enum' => 'القيمة المحددة لحقل :attribute غير صالحة.',
    'exists' => 'القيمة المحددة لحقل :attribute غير صالحة.',
    'extensions' => 'يجب أن يحمل ملف :attribute أحد الامتدادات التالية: :values.',
    'file' => 'يجب أن يكون حقل :attribute ملفاً.',
    'filled' => 'يجب أن يحتوي حقل :attribute على قيمة.',
    'gt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون حجم ملف :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أكبر من :value.',
        'string' => 'يجب أن يحتوي حقل :attribute على أكثر من :value حرف.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :value عنصر على الأقل.',
        'file' => 'يجب أن يكون حجم ملف :attribute مساوياً لـ :value كيلوبايت أو أكبر.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute مساوية لـ :value أو أكبر.',
        'string' => 'يجب أن يحتوي حقل :attribute على :value حرف على الأقل.',
    ],
    'hex_color' => 'يجب أن يكون حقل :attribute لوناً سداسياً عشرياً صالحاً.',
    'image' => 'يجب أن يكون حقل :attribute صورة.',
    'in' => 'القيمة المحددة لحقل :attribute غير صالحة.',
    'in_array' => 'يجب أن يكون حقل :attribute موجوداً في :other.',
    'integer' => 'يجب أن يكون حقل :attribute عدداً صحيحاً.',
    'ip' => 'يجب أن يكون حقل :attribute عنوان IP صالحاً.',
    'ipv4' => 'يجب أن يكون حقل :attribute عنوان IPv4 صالحاً.',
    'ipv6' => 'يجب أن يكون حقل :attribute عنوان IPv6 صالحاً.',
    'json' => 'يجب أن يكون حقل :attribute نصاً بصيغة JSON صالحة.',
    'list' => 'يجب أن يكون حقل :attribute قائمة.',
    'lowercase' => 'يجب أن يكون حقل :attribute بأحرف صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أقل من :value عنصر.',
        'file' => 'يجب أن يكون حجم ملف :attribute أصغر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أصغر من :value.',
        'string' => 'يجب أن يحتوي حقل :attribute على أقل من :value حرف.',
    ],
    'lte' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون حجم ملف :attribute مساوياً لـ :value كيلوبايت أو أصغر.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute مساوية لـ :value أو أصغر.',
        'string' => 'يجب ألا يتجاوز عدد أحرف حقل :attribute :value حرفاً.',
    ],
    'mac_address' => 'يجب أن يكون حقل :attribute عنوان MAC صالحاً.',
    'max' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عنصر.',
        'file' => 'يجب ألا يتجاوز حجم ملف :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تتجاوز قيمة حقل :attribute :max.',
        'string' => 'يجب ألا يتجاوز عدد أحرف حقل :attribute :max حرفاً.',
    ],
    'max_digits' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max رقم.',
    'mimes' => 'يجب أن يكون حقل :attribute ملفاً من نوع: :values.',
    'mimetypes' => 'يجب أن يكون حقل :attribute ملفاً من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :min عنصر على الأقل.',
        'file' => 'يجب ألا يقل حجم ملف :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة حقل :attribute عن :min.',
        'string' => 'يجب ألا يقل عدد أحرف حقل :attribute عن :min حرف.',
    ],
    'min_digits' => 'يجب أن يحتوي حقل :attribute على :min رقم على الأقل.',
    'missing' => 'يجب أن يكون حقل :attribute غير موجود.',
    'missing_if' => 'يجب أن يكون حقل :attribute غير موجود عندما تكون قيمة :other هي :value.',
    'missing_unless' => 'يجب أن يكون حقل :attribute غير موجود إلا إذا كانت قيمة :other هي :value.',
    'missing_with' => 'يجب أن يكون حقل :attribute غير موجود عند وجود :values.',
    'missing_with_all' => 'يجب أن يكون حقل :attribute غير موجود عند وجود :values جميعها.',
    'multiple_of' => 'يجب أن تكون قيمة حقل :attribute من مضاعفات :value.',
    'not_in' => 'القيمة المحددة لحقل :attribute غير صالحة.',
    'not_regex' => 'صيغة حقل :attribute غير صالحة.',
    'numeric' => 'يجب أن يكون حقل :attribute رقماً.',
    'password' => [
        'letters' => 'يجب أن يحتوي حقل :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي حقل :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي حقل :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي حقل :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'ظهرت قيمة حقل :attribute ضمن بيانات مسرّبة. يرجى اختيار قيمة أخرى.',
    ],
    'present' => 'يجب أن يكون حقل :attribute موجوداً.',
    'present_if' => 'يجب أن يكون حقل :attribute موجوداً عندما تكون قيمة :other هي :value.',
    'present_unless' => 'يجب أن يكون حقل :attribute موجوداً إلا إذا كانت قيمة :other هي :value.',
    'present_with' => 'يجب أن يكون حقل :attribute موجوداً عند وجود :values.',
    'present_with_all' => 'يجب أن يكون حقل :attribute موجوداً عند وجود :values جميعها.',
    'prohibited' => 'حقل :attribute محظور.',
    'prohibited_if' => 'حقل :attribute محظور عندما تكون قيمة :other هي :value.',
    'prohibited_unless' => 'حقل :attribute محظور إلا إذا كانت قيمة :other ضمن :values.',
    'prohibits' => 'حقل :attribute يمنع وجود :other.',
    'regex' => 'صيغة حقل :attribute غير صالحة.',
    'required' => 'حقل :attribute إجباري.',
    'required_array_keys' => 'يجب أن يحتوي حقل :attribute على مدخلات لـ: :values.',
    'required_if' => 'حقل :attribute إجباري عندما تكون قيمة :other هي :value.',
    'required_if_accepted' => 'حقل :attribute إجباري عند قبول :other.',
    'required_if_declined' => 'حقل :attribute إجباري عند رفض :other.',
    'required_unless' => 'حقل :attribute إجباري إلا إذا كانت قيمة :other ضمن :values.',
    'required_with' => 'حقل :attribute إجباري عند وجود :values.',
    'required_with_all' => 'حقل :attribute إجباري عند وجود :values جميعها.',
    'required_without' => 'حقل :attribute إجباري عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute إجباري عند عدم وجود أي من :values.',
    'same' => 'يجب أن يتطابق حقل :attribute مع :other.',
    'size' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :size عنصر.',
        'file' => 'يجب أن يكون حجم ملف :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute مساوية لـ :size.',
        'string' => 'يجب أن يحتوي حقل :attribute على :size حرف.',
    ],
    'starts_with' => 'يجب أن يبدأ حقل :attribute بإحدى القيم التالية: :values.',
    'string' => 'يجب أن يكون حقل :attribute نصاً.',
    'timezone' => 'يجب أن يكون حقل :attribute منطقة زمنية صالحة.',
    'ulid' => 'يجب أن يكون حقل :attribute معرّف ULID صالحاً.',
    'unique' => 'قيمة حقل :attribute مستخدمة من قبل.',
    'uploaded' => 'تعذّر رفع ملف :attribute.',
    'uppercase' => 'يجب أن يكون حقل :attribute بأحرف كبيرة.',
    'url' => 'يجب أن يكون حقل :attribute رابطاً صالحاً.',
    'uuid' => 'يجب أن يكون حقل :attribute معرّف UUID صالحاً.',

    /*
    |--------------------------------------------------------------------------
    | رسائل تحقق مخصصة
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | أسماء الحقول
    |--------------------------------------------------------------------------
    |
    | أسماء حقول مقروءة تُستخدم بدلاً من «email» و«phone»… في رسائل التحقق.
    |
    */

    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'phone' => 'رقم الهاتف',
        'locale' => 'اللغة',
        'receipt' => 'الإيصال',
        'reason' => 'السبب',
        'title_es' => 'العنوان (بالإسبانية)',
        'title_en' => 'العنوان (بالإنجليزية)',
        'description_es' => 'الوصف (بالإسبانية)',
        'description_en' => 'الوصف (بالإنجليزية)',
        'slug' => 'المعرّف في الرابط',
        'status' => 'الحالة',
        'price_eur' => 'السعر (يورو)',
        'code' => 'الرمز',
        'percent_off' => 'نسبة الخصم',
        'max_uses' => 'الحد الأقصى لعدد الاستخدامات',
        'expires_at' => 'تاريخ انتهاء الصلاحية',
        'position_seconds' => 'موضع التشغيل',
        'course_id' => 'الدورة',
        'coupon_code' => 'رمز الخصم',
        'module_ids' => 'الوحدات',
        'lesson_ids' => 'الدروس',
        'bunny_video_id' => 'معرّف فيديو Bunny',
        'duration_seconds' => 'المدة',
        'attachments' => 'المرفقات',
        'is_free_preview' => 'المعاينة المجانية',
        'method' => 'طريقة الدفع',
        'currency' => 'العملة',
        'province_ids' => 'المقاطعات',
        'province_id' => 'المقاطعة',
        'is_national' => 'التغطية الوطنية',
        'iva_rate' => 'نسبة ضريبة القيمة المضافة',
        'issued_at' => 'تاريخ الإصدار',
        'buyer_name' => 'اسم العميل',
        'buyer_address' => 'عنوان العميل',
        'search' => 'البحث',
        'category' => 'الفئة',
    ],

];
