# award_criteria_social

This is a Moodle plugin.

This plugin adds additional criteria to moodle badges.

It uses the unutilized "social" criteria defined in the /moodle/badges/criteria/award_criteria.php.

It can be configured to award badges based on participation in a forum within the course the badge is located.

Currently ratings must be enabled for the forum as the criteria uses the COUNT of a posts ratings to determine awarding.

Current criteria are:
    Achieving X ratings on a single post.
    Achieving X ratings on posts across whole forum.
    Posting X posts with atleast one rating across forum.
    Creating a post that gets X unique repliers.

To use this plugin add award_criteria_social.php to /moodle/badges/criteria.
You must also enable it in /moodle/badges/classes/badge.php.
In the function get_accepted_criteria add BADGE_CRITERIA_TYPE_SOCIAL to the array of 
criteria types for badge type BADGE_TYPE_COURSE.