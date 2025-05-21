# award_criteria_social

This is a Moodle plugin.

This plugin adds additional criteria to moodle badges.

It uses the unutilized "social" criteria defined in the /moodle/badges/criteria/award_criteria.php.

It can be configured to award badges based on participation in a forum within the course the badge is located.

Currently ratings must be enabled for the forum as the criteria uses the COUNT of a posts ratings to determine awarding.

Current criteria are:<br>
    - Achieving X ratings on a single post.<br>
    - Achieving X ratings on posts across whole forum.<br>
    - Posting X posts with atleast one rating across forum.<br>
    - Creating a post that gets X unique repliers.<br>

To use this plugin add award_criteria_social.php to /moodle/badges/criteria.
You must also enable it in /moodle/badges/classes/badge.php.
In the function <b>get_accepted_criteria()</b> add <b>BADGE_CRITERIA_TYPE_SOCIAL</b> to the array of 
criteria types for badge type <b>BADGE_TYPE_COURSE</b>.
