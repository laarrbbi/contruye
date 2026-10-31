<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Рядки валідації
    |--------------------------------------------------------------------------
    |
    | Типові повідомлення валідатора про помилки. Деякі правила мають кілька
    | варіантів, наприклад правила розміру.
    |
    */

    'accepted' => 'Потрібно прийняти «:attribute».',
    'accepted_if' => 'Потрібно прийняти «:attribute», коли :other дорівнює :value.',
    'active_url' => 'Поле «:attribute» має містити дійсну URL-адресу.',
    'after' => 'Поле «:attribute» має містити дату після :date.',
    'after_or_equal' => 'Поле «:attribute» має містити дату не раніше :date.',
    'alpha' => 'Поле «:attribute» може містити лише літери.',
    'alpha_dash' => 'Поле «:attribute» може містити лише літери, цифри, дефіси та підкреслення.',
    'alpha_num' => 'Поле «:attribute» може містити лише літери та цифри.',
    'array' => 'Поле «:attribute» має бути списком.',
    'ascii' => 'Поле «:attribute» може містити лише однобайтові літери, цифри та символи.',
    'before' => 'Поле «:attribute» має містити дату до :date.',
    'before_or_equal' => 'Поле «:attribute» має містити дату не пізніше :date.',
    'between' => [
        'array' => 'Кількість елементів у полі «:attribute» має бути від :min до :max.',
        'file' => 'Розмір файлу «:attribute» має бути від :min до :max КБ.',
        'numeric' => 'Поле «:attribute» має бути від :min до :max.',
        'string' => 'Кількість символів у полі «:attribute» має бути від :min до :max.',
    ],
    'boolean' => 'Поле «:attribute» має мати значення «так» або «ні».',
    'can' => 'Поле «:attribute» містить недозволене значення.',
    'confirmed' => 'Підтвердження поля «:attribute» не збігається.',
    'contains' => 'У полі «:attribute» бракує обов’язкового значення.',
    'current_password' => 'Неправильний пароль.',
    'date' => 'Поле «:attribute» має містити дійсну дату.',
    'date_equals' => 'Поле «:attribute» має містити дату :date.',
    'date_format' => 'Поле «:attribute» має відповідати формату :format.',
    'decimal' => 'Кількість знаків після коми в полі «:attribute» має дорівнювати :decimal.',
    'declined' => 'Поле «:attribute» має бути відхилено.',
    'declined_if' => 'Поле «:attribute» має бути відхилено, коли :other дорівнює :value.',
    'different' => 'Поля «:attribute» і :other мають відрізнятися.',
    'digits' => 'Кількість цифр у полі «:attribute» має дорівнювати :digits.',
    'digits_between' => 'Кількість цифр у полі «:attribute» має бути від :min до :max.',
    'dimensions' => 'Зображення «:attribute» має недопустимі розміри.',
    'distinct' => 'Поле «:attribute» містить значення, що повторюється.',
    'doesnt_end_with' => 'Поле «:attribute» не повинно закінчуватися жодним зі значень: :values.',
    'doesnt_start_with' => 'Поле «:attribute» не повинно починатися з жодного зі значень: :values.',
    'email' => 'Поле «:attribute» має містити дійсну адресу електронної пошти.',
    'ends_with' => 'Поле «:attribute» має закінчуватися одним зі значень: :values.',
    'enum' => 'Вибране значення поля «:attribute» недійсне.',
    'exists' => 'Вибране значення поля «:attribute» недійсне.',
    'extensions' => 'Файл у полі «:attribute» має мати одне з розширень: :values.',
    'file' => 'Поле «:attribute» має бути файлом.',
    'filled' => 'Поле «:attribute» має бути заповнене.',
    'gt' => [
        'array' => 'Кількість елементів у полі «:attribute» має бути більшою за :value.',
        'file' => 'Розмір файлу «:attribute» має бути більшим за :value КБ.',
        'numeric' => 'Поле «:attribute» має бути більшим за :value.',
        'string' => 'Кількість символів у полі «:attribute» має бути більшою за :value.',
    ],
    'gte' => [
        'array' => 'Кількість елементів у полі «:attribute» має бути не меншою за :value.',
        'file' => 'Розмір файлу «:attribute» має бути не меншим за :value КБ.',
        'numeric' => 'Поле «:attribute» має бути не меншим за :value.',
        'string' => 'Кількість символів у полі «:attribute» має бути не меншою за :value.',
    ],
    'hex_color' => 'Поле «:attribute» має містити дійсний шістнадцятковий код кольору.',
    'image' => 'Поле «:attribute» має бути зображенням.',
    'in' => 'Вибране значення поля «:attribute» недійсне.',
    'in_array' => 'Значення поля «:attribute» має бути в :other.',
    'integer' => 'Поле «:attribute» має бути цілим числом.',
    'ip' => 'Поле «:attribute» має містити дійсну IP-адресу.',
    'ipv4' => 'Поле «:attribute» має містити дійсну IPv4-адресу.',
    'ipv6' => 'Поле «:attribute» має містити дійсну IPv6-адресу.',
    'json' => 'Поле «:attribute» має містити дійсний рядок JSON.',
    'list' => 'Поле «:attribute» має бути списком.',
    'lowercase' => 'Поле «:attribute» має бути в нижньому регістрі.',
    'lt' => [
        'array' => 'Кількість елементів у полі «:attribute» має бути меншою за :value.',
        'file' => 'Розмір файлу «:attribute» має бути меншим за :value КБ.',
        'numeric' => 'Поле «:attribute» має бути меншим за :value.',
        'string' => 'Кількість символів у полі «:attribute» має бути меншою за :value.',
    ],
    'lte' => [
        'array' => 'Кількість елементів у полі «:attribute» не може перевищувати :value.',
        'file' => 'Розмір файлу «:attribute» не може перевищувати :value КБ.',
        'numeric' => 'Поле «:attribute» має бути не більшим за :value.',
        'string' => 'Кількість символів у полі «:attribute» не може перевищувати :value.',
    ],
    'mac_address' => 'Поле «:attribute» має містити дійсну MAC-адресу.',
    'max' => [
        'array' => 'Кількість елементів у полі «:attribute» не може перевищувати :max.',
        'file' => 'Розмір файлу «:attribute» не може перевищувати :max КБ.',
        'numeric' => 'Поле «:attribute» не може бути більшим за :max.',
        'string' => 'Кількість символів у полі «:attribute» не може перевищувати :max.',
    ],
    'max_digits' => 'Кількість цифр у полі «:attribute» не може перевищувати :max.',
    'mimes' => 'Файл у полі «:attribute» має бути одного з типів: :values.',
    'mimetypes' => 'Файл у полі «:attribute» має бути одного з типів: :values.',
    'min' => [
        'array' => 'Кількість елементів у полі «:attribute» має бути не меншою за :min.',
        'file' => 'Розмір файлу «:attribute» має бути не меншим за :min КБ.',
        'numeric' => 'Поле «:attribute» має бути не меншим за :min.',
        'string' => 'Кількість символів у полі «:attribute» має бути не меншою за :min.',
    ],
    'min_digits' => 'Кількість цифр у полі «:attribute» має бути не меншою за :min.',
    'missing' => 'Поле «:attribute» має бути відсутнім.',
    'missing_if' => 'Поле «:attribute» має бути відсутнім, коли :other дорівнює :value.',
    'missing_unless' => 'Поле «:attribute» має бути відсутнім, якщо тільки :other не дорівнює :value.',
    'missing_with' => 'Поле «:attribute» має бути відсутнім, коли вказано :values.',
    'missing_with_all' => 'Поле «:attribute» має бути відсутнім, коли вказано всі :values.',
    'multiple_of' => 'Поле «:attribute» має бути кратним :value.',
    'not_in' => 'Вибране значення поля «:attribute» недійсне.',
    'not_regex' => 'Поле «:attribute» має недопустимий формат.',
    'numeric' => 'Поле «:attribute» має бути числом.',
    'password' => [
        'letters' => 'Поле «:attribute» має містити щонайменше одну літеру.',
        'mixed' => 'Поле «:attribute» має містити щонайменше одну велику й одну малу літеру.',
        'numbers' => 'Поле «:attribute» має містити щонайменше одну цифру.',
        'symbols' => 'Поле «:attribute» має містити щонайменше один символ.',
        'uncompromised' => 'Значення поля «:attribute» з’явилося у витоку даних. Виберіть інше.',
    ],
    'present' => 'Поле «:attribute» має бути присутнім.',
    'present_if' => 'Поле «:attribute» має бути присутнім, коли :other дорівнює :value.',
    'present_unless' => 'Поле «:attribute» має бути присутнім, якщо тільки :other не дорівнює :value.',
    'present_with' => 'Поле «:attribute» має бути присутнім, коли вказано :values.',
    'present_with_all' => 'Поле «:attribute» має бути присутнім, коли вказано всі :values.',
    'prohibited' => 'Поле «:attribute» заборонене.',
    'prohibited_if' => 'Поле «:attribute» заборонене, коли :other дорівнює :value.',
    'prohibited_unless' => 'Поле «:attribute» заборонене, якщо тільки :other не входить до :values.',
    'prohibits' => 'Поле «:attribute» забороняє присутність :other.',
    'regex' => 'Поле «:attribute» має недопустимий формат.',
    'required' => 'Поле «:attribute» є обов’язковим.',
    'required_array_keys' => 'Поле «:attribute» має містити записи для: :values.',
    'required_if' => 'Поле «:attribute» є обов’язковим, коли :other дорівнює :value.',
    'required_if_accepted' => 'Поле «:attribute» є обов’язковим, коли :other прийнято.',
    'required_if_declined' => 'Поле «:attribute» є обов’язковим, коли :other відхилено.',
    'required_unless' => 'Поле «:attribute» є обов’язковим, якщо тільки :other не входить до :values.',
    'required_with' => 'Поле «:attribute» є обов’язковим, коли вказано :values.',
    'required_with_all' => 'Поле «:attribute» є обов’язковим, коли вказано всі :values.',
    'required_without' => 'Поле «:attribute» є обов’язковим, коли не вказано :values.',
    'required_without_all' => 'Поле «:attribute» є обов’язковим, коли не вказано жодного з :values.',
    'same' => 'Значення полів «:attribute» і :other мають збігатися.',
    'size' => [
        'array' => 'Кількість елементів у полі «:attribute» має дорівнювати :size.',
        'file' => 'Розмір файлу «:attribute» має дорівнювати :size КБ.',
        'numeric' => 'Поле «:attribute» має дорівнювати :size.',
        'string' => 'Кількість символів у полі «:attribute» має дорівнювати :size.',
    ],
    'starts_with' => 'Поле «:attribute» має починатися з одного зі значень: :values.',
    'string' => 'Поле «:attribute» має бути рядком.',
    'timezone' => 'Поле «:attribute» має містити дійсний часовий пояс.',
    'ulid' => 'Поле «:attribute» має містити дійсний ULID.',
    'unique' => 'Таке значення поля «:attribute» уже використовується.',
    'uploaded' => 'Не вдалося завантажити файл «:attribute».',
    'uppercase' => 'Поле «:attribute» має бути у верхньому регістрі.',
    'url' => 'Поле «:attribute» має містити дійсну URL-адресу.',
    'uuid' => 'Поле «:attribute» має містити дійсний UUID.',

    /*
    |--------------------------------------------------------------------------
    | Власні рядки
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Назви полів
    |--------------------------------------------------------------------------
    |
    | Зрозумілі назви замість «email», «phone»… у повідомленнях валідації.
    |
    */

    'attributes' => [
        'name' => 'ім’я',
        'email' => 'адреса електронної пошти',
        'password' => 'пароль',
        'phone' => 'телефон',
        'locale' => 'мова',
        'receipt' => 'квитанція',
        'reason' => 'причина',
        'title_es' => 'назва (іспанська)',
        'title_en' => 'назва (англійська)',
        'description_es' => 'опис (іспанська)',
        'description_en' => 'опис (англійська)',
        'slug' => 'slug',
        'status' => 'статус',
        'price_eur' => 'ціна (EUR)',
        'code' => 'код',
        'percent_off' => 'знижка',
        'max_uses' => 'максимальна кількість використань',
        'expires_at' => 'термін дії',
        'position_seconds' => 'позиція',
        'course_id' => 'курс',
        'coupon_code' => 'промокод',
        'module_ids' => 'модулі',
        'lesson_ids' => 'уроки',
        'bunny_video_id' => 'ID відео в Bunny',
        'duration_seconds' => 'тривалість',
        'attachments' => 'вкладення',
        'is_free_preview' => 'безкоштовний перегляд',
        'method' => 'спосіб оплати',
        'currency' => 'валюта',
        'province_ids' => 'провінції',
        'province_id' => 'провінція',
        'is_national' => 'уся Іспанія',
        'iva_rate' => 'ставка IVA',
        'issued_at' => 'дата виставлення',
        'buyer_name' => 'ім’я клієнта',
        'buyer_address' => 'адреса клієнта',
        'search' => 'пошук',
        'category' => 'категорія',
    ],

];
