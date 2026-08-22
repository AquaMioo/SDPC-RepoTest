<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Upload Ceilings
    |--------------------------------------------------------------------------
    |
    | The largest file each kind of upload will accept, in kilobytes, because
    | that is the unit Laravel's `max` rule speaks.
    |
    | These must stay BELOW php.ini's upload_max_filesize. PHP discards an
    | over-sized file before validation ever runs, so a rule that allows more
    | than PHP does can never fire — the request fails on the `uploaded` rule
    | instead, whose default wording ("failed to upload") reads like a broken
    | server rather than a file that is too big.
    |
    | A logo is drawn on the dashboard, the business profile and every search
    | result, so its weight is paid on nearly every page a client loads. A
    | permit is opened once, by one administrator. They share a ceiling here
    | only because that is what was asked for; lowering the logo is the first
    | thing to try if those screens feel slow.
    |
    */

    'max_image_kilobytes' => (int) env('UPLOAD_MAX_IMAGE_KILOBYTES', 102400),

    'max_document_kilobytes' => (int) env('UPLOAD_MAX_DOCUMENT_KILOBYTES', 102400),

];
