<?php

return [
    // Every page carries the list of the site's addresses for route() in the scripts. Members who are not
    // administrators get it without the admin pages and the file upload addresses: a lighter page on phones.
    'groups' => [
        'member' => ['!admin.*', '!storage.*'],
    ],
];
