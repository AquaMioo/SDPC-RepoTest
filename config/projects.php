<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Skip Posting Review
    |--------------------------------------------------------------------------
    |
    | A posting normally lands in "pending review" and only reaches the student
    | board once an administrator approves it on the Postings screen. That is
    | the real workflow and the default.
    |
    | Turn this on locally and publishing opens the posting immediately, so one
    | person can walk the whole client-to-student path without switching to the
    | admin portal in between. It is a demo convenience, not a feature: leave it
    | off anywhere real, or unscreened work goes straight in front of students.
    |
    */

    'auto_approve' => (bool) env('PROJECTS_AUTO_APPROVE', false),

];
