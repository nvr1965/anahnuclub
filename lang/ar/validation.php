<?php

return [
    'required' => 'حقل :attribute مطلوب.',
    'email' => 'يجب أن يكون :attribute بريداً إلكترونياً صالحاً.',
    'unique' => 'قيمة :attribute مستخدمة بالفعل.',
    'confirmed' => 'تأكيد :attribute غير مطابق.',
    'string' => 'يجب أن يكون :attribute نصاً.',
    'lowercase' => 'يجب أن يكون :attribute بأحرف صغيرة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'min' => [
        'string' => 'يجب ألا يقل :attribute عن :min حرفاً.',
    ],
    'max' => [
        'string' => 'يجب ألا يزيد :attribute عن :max حرفاً.',
    ],
    'password' => [
        'letters' => 'يجب أن يحتوي :attribute على أحرف.',
        'mixed' => 'يجب أن يحتوي :attribute على أحرف كبيرة وصغيرة.',
        'numbers' => 'يجب أن يحتوي :attribute على أرقام.',
        'symbols' => 'يجب أن يحتوي :attribute على رموز.',
        'uncompromised' => 'ظهر :attribute في تسريب بيانات. اختر غيره.',
    ],
    'attributes' => [
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'name' => 'الاسم',
        'body' => 'النص',
    ],
];
