<?php

/*
 * Author and Developer: Nikolay Reyzvikh (Николай Рейзвих).
 * Copyright (c) 2026 Nikolay Reyzvikh. All rights reserved.
 * Developed with the use of Claude.
 */

return [
    // Every page carries the list of the site's addresses for route() in the scripts. Members who are not
    // administrators get it without the admin pages and the file upload addresses: a lighter page on phones.
    'groups' => [
        'member' => ['!admin.*', '!storage.*'],
    ],
];
