<?php

/**
 * The 52 Spanish provinces (50 provinces plus the autonomous cities of Ceuta
 * and Melilla), in INE code order — the two-digit code every administrative
 * form and the first two digits of every postal code use.
 *
 * Reference data: codes and official names follow the INE's list; `name_en`
 * is the English exonym where one is in use (Seville, Navarre, Biscay…) and
 * the official name otherwise. French follows the same rule (Séville,
 * Saragosse); Arabic uses the historical names Arabic readers know (قرطبة,
 * إشبيلية) and a transcription for the rest; Russian and Ukrainian are the
 * usual transcriptions.
 *
 * @return list<array{code: int, name_es: string, name_en: string, name_fr: string, name_ar: string, name_ru: string, name_uk: string}>
 */
return [
    ['code' => 1, 'name_es' => 'Araba/Álava', 'name_en' => 'Álava', 'name_fr' => 'Alava', 'name_ar' => 'ألافا', 'name_ru' => 'Алава', 'name_uk' => 'Алава'],
    ['code' => 2, 'name_es' => 'Albacete', 'name_en' => 'Albacete', 'name_fr' => 'Albacete', 'name_ar' => 'ألباسيتي', 'name_ru' => 'Альбасете', 'name_uk' => 'Альбасете'],
    ['code' => 3, 'name_es' => 'Alicante/Alacant', 'name_en' => 'Alicante', 'name_fr' => 'Alicante', 'name_ar' => 'أليكانتي', 'name_ru' => 'Аликанте', 'name_uk' => 'Аліканте'],
    ['code' => 4, 'name_es' => 'Almería', 'name_en' => 'Almería', 'name_fr' => 'Almería', 'name_ar' => 'ألمرية', 'name_ru' => 'Альмерия', 'name_uk' => 'Альмерія'],
    ['code' => 5, 'name_es' => 'Ávila', 'name_en' => 'Ávila', 'name_fr' => 'Ávila', 'name_ar' => 'آبلة', 'name_ru' => 'Авила', 'name_uk' => 'Авіла'],
    ['code' => 6, 'name_es' => 'Badajoz', 'name_en' => 'Badajoz', 'name_fr' => 'Badajoz', 'name_ar' => 'بطليوس', 'name_ru' => 'Бадахос', 'name_uk' => 'Бадахос'],
    ['code' => 7, 'name_es' => 'Illes Balears', 'name_en' => 'Balearic Islands', 'name_fr' => 'Îles Baléares', 'name_ar' => 'جزر البليار', 'name_ru' => 'Балеарские острова', 'name_uk' => 'Балеарські острови'],
    ['code' => 8, 'name_es' => 'Barcelona', 'name_en' => 'Barcelona', 'name_fr' => 'Barcelone', 'name_ar' => 'برشلونة', 'name_ru' => 'Барселона', 'name_uk' => 'Барселона'],
    ['code' => 9, 'name_es' => 'Burgos', 'name_en' => 'Burgos', 'name_fr' => 'Burgos', 'name_ar' => 'برغش', 'name_ru' => 'Бургос', 'name_uk' => 'Бургос'],
    ['code' => 10, 'name_es' => 'Cáceres', 'name_en' => 'Cáceres', 'name_fr' => 'Cáceres', 'name_ar' => 'قصرش', 'name_ru' => 'Касерес', 'name_uk' => 'Касерес'],
    ['code' => 11, 'name_es' => 'Cádiz', 'name_en' => 'Cádiz', 'name_fr' => 'Cadix', 'name_ar' => 'قادس', 'name_ru' => 'Кадис', 'name_uk' => 'Кадіс'],
    ['code' => 12, 'name_es' => 'Castellón/Castelló', 'name_en' => 'Castellón', 'name_fr' => 'Castellón', 'name_ar' => 'كاستيون', 'name_ru' => 'Кастельон', 'name_uk' => 'Кастельйон'],
    ['code' => 13, 'name_es' => 'Ciudad Real', 'name_en' => 'Ciudad Real', 'name_fr' => 'Ciudad Real', 'name_ar' => 'سيوداد ريال', 'name_ru' => 'Сьюдад-Реаль', 'name_uk' => 'Сьюдад-Реаль'],
    ['code' => 14, 'name_es' => 'Córdoba', 'name_en' => 'Córdoba', 'name_fr' => 'Cordoue', 'name_ar' => 'قرطبة', 'name_ru' => 'Кордова', 'name_uk' => 'Кордова'],
    ['code' => 15, 'name_es' => 'A Coruña', 'name_en' => 'A Coruña', 'name_fr' => 'La Corogne', 'name_ar' => 'لا كورونيا', 'name_ru' => 'Ла-Корунья', 'name_uk' => 'Ла-Корунья'],
    ['code' => 16, 'name_es' => 'Cuenca', 'name_en' => 'Cuenca', 'name_fr' => 'Cuenca', 'name_ar' => 'قونكة', 'name_ru' => 'Куэнка', 'name_uk' => 'Куенка'],
    ['code' => 17, 'name_es' => 'Girona', 'name_en' => 'Girona', 'name_fr' => 'Gérone', 'name_ar' => 'جيرونا', 'name_ru' => 'Жирона', 'name_uk' => 'Жирона'],
    ['code' => 18, 'name_es' => 'Granada', 'name_en' => 'Granada', 'name_fr' => 'Grenade', 'name_ar' => 'غرناطة', 'name_ru' => 'Гранада', 'name_uk' => 'Гранада'],
    ['code' => 19, 'name_es' => 'Guadalajara', 'name_en' => 'Guadalajara', 'name_fr' => 'Guadalajara', 'name_ar' => 'وادي الحجارة', 'name_ru' => 'Гвадалахара', 'name_uk' => 'Гвадалахара'],
    ['code' => 20, 'name_es' => 'Gipuzkoa', 'name_en' => 'Gipuzkoa', 'name_fr' => 'Guipuscoa', 'name_ar' => 'غيبوثكوا', 'name_ru' => 'Гипускоа', 'name_uk' => 'Гіпускоа'],
    ['code' => 21, 'name_es' => 'Huelva', 'name_en' => 'Huelva', 'name_fr' => 'Huelva', 'name_ar' => 'ولبة', 'name_ru' => 'Уэльва', 'name_uk' => 'Уельва'],
    ['code' => 22, 'name_es' => 'Huesca', 'name_en' => 'Huesca', 'name_fr' => 'Huesca', 'name_ar' => 'وشقة', 'name_ru' => 'Уэска', 'name_uk' => 'Уеска'],
    ['code' => 23, 'name_es' => 'Jaén', 'name_en' => 'Jaén', 'name_fr' => 'Jaén', 'name_ar' => 'جيان', 'name_ru' => 'Хаэн', 'name_uk' => 'Хаен'],
    ['code' => 24, 'name_es' => 'León', 'name_en' => 'León', 'name_fr' => 'León', 'name_ar' => 'ليون', 'name_ru' => 'Леон', 'name_uk' => 'Леон'],
    ['code' => 25, 'name_es' => 'Lleida', 'name_en' => 'Lleida', 'name_fr' => 'Lérida', 'name_ar' => 'لاردة', 'name_ru' => 'Льейда', 'name_uk' => 'Льєйда'],
    ['code' => 26, 'name_es' => 'La Rioja', 'name_en' => 'La Rioja', 'name_fr' => 'La Rioja', 'name_ar' => 'لا ريوخا', 'name_ru' => 'Риоха', 'name_uk' => 'Ріоха'],
    ['code' => 27, 'name_es' => 'Lugo', 'name_en' => 'Lugo', 'name_fr' => 'Lugo', 'name_ar' => 'لوغو', 'name_ru' => 'Луго', 'name_uk' => 'Луго'],
    ['code' => 28, 'name_es' => 'Madrid', 'name_en' => 'Madrid', 'name_fr' => 'Madrid', 'name_ar' => 'مدريد', 'name_ru' => 'Мадрид', 'name_uk' => 'Мадрид'],
    ['code' => 29, 'name_es' => 'Málaga', 'name_en' => 'Málaga', 'name_fr' => 'Malaga', 'name_ar' => 'مالقة', 'name_ru' => 'Малага', 'name_uk' => 'Малага'],
    ['code' => 30, 'name_es' => 'Murcia', 'name_en' => 'Murcia', 'name_fr' => 'Murcie', 'name_ar' => 'مرسية', 'name_ru' => 'Мурсия', 'name_uk' => 'Мурсія'],
    ['code' => 31, 'name_es' => 'Navarra', 'name_en' => 'Navarre', 'name_fr' => 'Navarre', 'name_ar' => 'نافارا', 'name_ru' => 'Наварра', 'name_uk' => 'Наварра'],
    ['code' => 32, 'name_es' => 'Ourense', 'name_en' => 'Ourense', 'name_fr' => 'Ourense', 'name_ar' => 'أورينسي', 'name_ru' => 'Оренсе', 'name_uk' => 'Оренсе'],
    ['code' => 33, 'name_es' => 'Asturias', 'name_en' => 'Asturias', 'name_fr' => 'Asturies', 'name_ar' => 'أستورياس', 'name_ru' => 'Астурия', 'name_uk' => 'Астурія'],
    ['code' => 34, 'name_es' => 'Palencia', 'name_en' => 'Palencia', 'name_fr' => 'Palencia', 'name_ar' => 'بالنسيا', 'name_ru' => 'Паленсия', 'name_uk' => 'Паленсія'],
    ['code' => 35, 'name_es' => 'Las Palmas', 'name_en' => 'Las Palmas', 'name_fr' => 'Las Palmas', 'name_ar' => 'لاس بالماس', 'name_ru' => 'Лас-Пальмас', 'name_uk' => 'Лас-Пальмас'],
    ['code' => 36, 'name_es' => 'Pontevedra', 'name_en' => 'Pontevedra', 'name_fr' => 'Pontevedra', 'name_ar' => 'بونتيفيدرا', 'name_ru' => 'Понтеведра', 'name_uk' => 'Понтеведра'],
    ['code' => 37, 'name_es' => 'Salamanca', 'name_en' => 'Salamanca', 'name_fr' => 'Salamanque', 'name_ar' => 'شلمنقة', 'name_ru' => 'Саламанка', 'name_uk' => 'Саламанка'],
    ['code' => 38, 'name_es' => 'Santa Cruz de Tenerife', 'name_en' => 'Santa Cruz de Tenerife', 'name_fr' => 'Santa Cruz de Tenerife', 'name_ar' => 'سانتا كروث دي تينيريفي', 'name_ru' => 'Санта-Крус-де-Тенерифе', 'name_uk' => 'Санта-Крус-де-Тенерифе'],
    ['code' => 39, 'name_es' => 'Cantabria', 'name_en' => 'Cantabria', 'name_fr' => 'Cantabrie', 'name_ar' => 'كانتابريا', 'name_ru' => 'Кантабрия', 'name_uk' => 'Кантабрія'],
    ['code' => 40, 'name_es' => 'Segovia', 'name_en' => 'Segovia', 'name_fr' => 'Ségovie', 'name_ar' => 'شقوبية', 'name_ru' => 'Сеговия', 'name_uk' => 'Сеговія'],
    ['code' => 41, 'name_es' => 'Sevilla', 'name_en' => 'Seville', 'name_fr' => 'Séville', 'name_ar' => 'إشبيلية', 'name_ru' => 'Севилья', 'name_uk' => 'Севілья'],
    ['code' => 42, 'name_es' => 'Soria', 'name_en' => 'Soria', 'name_fr' => 'Soria', 'name_ar' => 'صورية', 'name_ru' => 'Сория', 'name_uk' => 'Сорія'],
    ['code' => 43, 'name_es' => 'Tarragona', 'name_en' => 'Tarragona', 'name_fr' => 'Tarragone', 'name_ar' => 'طركونة', 'name_ru' => 'Таррагона', 'name_uk' => 'Таррагона'],
    ['code' => 44, 'name_es' => 'Teruel', 'name_en' => 'Teruel', 'name_fr' => 'Teruel', 'name_ar' => 'تيرويل', 'name_ru' => 'Теруэль', 'name_uk' => 'Теруель'],
    ['code' => 45, 'name_es' => 'Toledo', 'name_en' => 'Toledo', 'name_fr' => 'Tolède', 'name_ar' => 'طليطلة', 'name_ru' => 'Толедо', 'name_uk' => 'Толедо'],
    ['code' => 46, 'name_es' => 'Valencia/València', 'name_en' => 'Valencia', 'name_fr' => 'Valence', 'name_ar' => 'بلنسية', 'name_ru' => 'Валенсия', 'name_uk' => 'Валенсія'],
    ['code' => 47, 'name_es' => 'Valladolid', 'name_en' => 'Valladolid', 'name_fr' => 'Valladolid', 'name_ar' => 'بلد الوليد', 'name_ru' => 'Вальядолид', 'name_uk' => 'Вальядолід'],
    ['code' => 48, 'name_es' => 'Bizkaia', 'name_en' => 'Biscay', 'name_fr' => 'Biscaye', 'name_ar' => 'بيسكاي', 'name_ru' => 'Бискайя', 'name_uk' => 'Біскайя'],
    ['code' => 49, 'name_es' => 'Zamora', 'name_en' => 'Zamora', 'name_fr' => 'Zamora', 'name_ar' => 'سمورة', 'name_ru' => 'Самора', 'name_uk' => 'Самора'],
    ['code' => 50, 'name_es' => 'Zaragoza', 'name_en' => 'Zaragoza', 'name_fr' => 'Saragosse', 'name_ar' => 'سرقسطة', 'name_ru' => 'Сарагоса', 'name_uk' => 'Сарагоса'],
    ['code' => 51, 'name_es' => 'Ceuta', 'name_en' => 'Ceuta', 'name_fr' => 'Ceuta', 'name_ar' => 'سبتة', 'name_ru' => 'Сеута', 'name_uk' => 'Сеута'],
    ['code' => 52, 'name_es' => 'Melilla', 'name_en' => 'Melilla', 'name_fr' => 'Melilla', 'name_ar' => 'مليلية', 'name_ru' => 'Мелилья', 'name_uk' => 'Мелілья'],
];
