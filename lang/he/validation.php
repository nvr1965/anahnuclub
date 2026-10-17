<?php

return [
    'required' => 'השדה :attribute הוא חובה.',
    'email' => 'השדה :attribute חייב להיות כתובת דוא"ל.',
    'unique' => 'הערך בשדה :attribute כבר תפוס.',
    'confirmed' => 'אימות השדה :attribute אינו תואם.',
    'string' => 'השדה :attribute חייב להיות טקסט.',
    'lowercase' => 'השדה :attribute חייב להיות באותיות קטנות.',
    'current_password' => 'הסיסמה שגויה.',
    'min' => [
        'string' => 'השדה :attribute חייב להכיל לפחות :min תווים.',
    ],
    'max' => [
        'string' => 'השדה :attribute לא יכול להכיל יותר מ-:max תווים.',
    ],
    'password' => [
        'letters' => 'השדה :attribute חייב לכלול אותיות.',
        'mixed' => 'השדה :attribute חייב לכלול אותיות גדולות וקטנות.',
        'numbers' => 'השדה :attribute חייב לכלול ספרות.',
        'symbols' => 'השדה :attribute חייב לכלול סימנים.',
        'uncompromised' => 'ה:attribute הופיע בדליפת מידע. בחרו אחר.',
    ],
    'attributes' => [
        'email' => 'דוא"ל',
        'password' => 'סיסמה',
        'name' => 'שם',
        'body' => 'טקסט',
    ],
];
