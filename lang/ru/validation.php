<?php

return [
    'required' => 'Поле :attribute обязательно.',
    'email' => 'Поле :attribute должно быть адресом эл. почты.',
    'unique' => 'Такое значение поля :attribute уже занято.',
    'confirmed' => 'Поле :attribute не совпадает с подтверждением.',
    'string' => 'Поле :attribute должно быть строкой.',
    'lowercase' => 'Поле :attribute должно быть в нижнем регистре.',
    'current_password' => 'Неверный пароль.',
    'min' => [
        'string' => 'Поле :attribute должно быть не короче :min символов.',
    ],
    'max' => [
        'string' => 'Поле :attribute должно быть не длиннее :max символов.',
    ],
    'password' => [
        'letters' => 'Поле :attribute должно содержать буквы.',
        'mixed' => 'Поле :attribute должно содержать заглавные и строчные буквы.',
        'numbers' => 'Поле :attribute должно содержать цифры.',
        'symbols' => 'Поле :attribute должно содержать символы.',
        'uncompromised' => 'Этот :attribute встречался в утечках данных. Выберите другой.',
    ],
    'attributes' => [
        'email' => 'эл. почта',
        'password' => 'пароль',
        'name' => 'имя',
        'body' => 'текст',
    ],
];
